<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Order lifecycle.
 *
 *   place()    customer checks out: prices and stock are re-read from the database (never
 *              trusted from the browser) but no stock is taken yet. Status: pending.
 *   confirm()  Sales confirms a COD order, or the online payment succeeds (VNPay IPN):
 *              stock is taken from batches with FEFO and every order line records the batch
 *              it came from (3 kg from lot A + 7 kg from lot B = two lines). Status: confirmed.
 *              If the batches cannot cover the order the status becomes stock_issue so Sales
 *              can call the customer, and nothing is taken.
 *   shipping -> delivered, or cancelled (stock and points are given back).
 */
class OrderService
{
    public function __construct(
        private InventoryService $inventory,
        private ShippingService $shipping,
        private PointsService $points,
    ) {}

    /**
     * @param  array<int, array{product: Product, quantity: int}>  $lines
     * @param  array{
     *     shipping_name: string, shipping_phone: string, shipping_address: string,
     *     payment_method?: string, points_to_use?: int, distance_km?: int, note?: ?string,
     *     subscription?: ?Subscription, discount_percent?: int, delivery_date?: ?string
     * }  $data
     */
    public function place(User $user, array $lines, array $data): Order
    {
        if ($lines === []) {
            throw new DomainException('Giỏ hàng đang trống.');
        }

        $paymentMethod = $data['payment_method'] ?? Order::PAYMENT_COD;
        $allowed = [Order::PAYMENT_COD, Order::PAYMENT_VNPAY, Order::PAYMENT_SUBSCRIPTION];
        if (! in_array($paymentMethod, $allowed, true)) {
            throw new DomainException('Phương thức thanh toán không hợp lệ.');
        }

        $prepaid = $paymentMethod === Order::PAYMENT_SUBSCRIPTION;
        $discountPercent = (int) ($data['discount_percent'] ?? 0);

        return DB::transaction(function () use ($user, $lines, $data, $paymentMethod, $prepaid, $discountPercent) {
            $subtotal = 0;
            $prepared = [];

            foreach ($lines as $line) {
                // Re-read the product so the price always comes from the database.
                $product = Product::whereKey($line['product']->id)->firstOrFail();
                $quantity = (int) $line['quantity'];

                if (! $product->is_active) {
                    throw new DomainException("Sản phẩm \"{$product->name}\" đã ngừng bán.");
                }
                if ($quantity < 1) {
                    throw new DomainException('Số lượng không hợp lệ.');
                }

                $available = $this->inventory->available($product);
                if ($quantity > $available) {
                    throw new InsufficientStockException(
                        "Sản phẩm \"{$product->name}\" chỉ còn {$available} {$product->unit} trong các lô còn hạn."
                    );
                }

                $unitPrice = intdiv($product->price * (100 - $discountPercent), 100);
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;

                $prepared[] = compact('product', 'quantity', 'unitPrice', 'lineTotal');
            }

            $distanceKm = (int) ($data['distance_km'] ?? 0);
            $shippingFee = $prepaid ? 0 : $this->shipping->fee($subtotal, $distanceKm);

            $pointsToUse = $prepaid ? 0 : (int) ($data['points_to_use'] ?? 0);
            $discount = 0;
            $pointsUsed = 0;

            if ($pointsToUse > 0) {
                if ($user->fresh()->points_balance < $pointsToUse) {
                    throw new DomainException('Bạn không đủ điểm để sử dụng.');
                }
                $discount = $this->points->discountFor($pointsToUse, $subtotal);
                $pointsUsed = $this->points->pointsRequiredFor($discount);
            }

            $order = Order::create([
                'code' => $this->newCode(),
                'user_id' => $user->id,
                'subscription_id' => ($data['subscription'] ?? null)?->id,
                'status' => Order::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                // A subscription delivery was paid for when the package was bought.
                'payment_status' => $prepaid ? 'paid' : 'unpaid',
                'paid_at' => $prepaid ? now() : null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'points_discount' => $discount,
                'total' => $subtotal + $shippingFee - $discount,
                'points_used' => $pointsUsed,
                'points_earned' => 0,
                'distance_km' => $distanceKm,
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_address' => $data['shipping_address'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($prepared as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'unit_price' => $line['unitPrice'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['lineTotal'],
                ]);
            }

            if ($pointsUsed > 0) {
                $this->points->debit($user, $pointsUsed, 'Dùng điểm cho đơn '.$order->code, $order->id);
            }

            return $order->load('items');
        });
    }

    /**
     * Take the order's stock from batches (FEFO) and mark it confirmed.
     *
     * @throws InsufficientStockException when the batches cannot cover it (order becomes stock_issue)
     */
    public function confirm(Order $order): Order
    {
        try {
            DB::transaction(fn () => $this->allocate($order));
        } catch (InsufficientStockException $e) {
            Order::whereKey($order->id)
                ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_STOCK_ISSUE])
                ->update(['status' => Order::STATUS_STOCK_ISSUE, 'issue_note' => $e->getMessage()]);

            throw $e;
        }

        SendOrderConfirmation::dispatch($order->id)->afterCommit();

        return $order->refresh()->load('items');
    }

    /** Like confirm(), but reports a shortage instead of throwing (used by payment callbacks and jobs). */
    public function tryConfirm(Order $order): bool
    {
        try {
            $this->confirm($order);

            return true;
        } catch (InsufficientStockException) {
            return false;
        }
    }

    /** Mark an order as paid (idempotent), award loyalty points once, then take its stock. */
    public function markPaid(Order $order, ?string $paymentRef = null): void
    {
        if ($order->paid_at === null) {
            DB::transaction(function () use ($order, $paymentRef) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->paid_at !== null) {
                    return;
                }

                $earned = $this->points->earnFor($locked->total);
                $locked->update([
                    'payment_status' => 'paid',
                    'payment_ref' => $paymentRef ?? $locked->payment_ref,
                    'paid_at' => now(),
                    'points_earned' => $earned,
                ]);

                $this->points->credit($locked->user, $earned, 'Tích điểm đơn '.$locked->code, $locked->id);
            });
        }

        // Online payment received: start the FEFO stock-out straight away (Tuần 6).
        $order->refresh();
        if ($order->status === Order::STATUS_PENDING) {
            $this->tryConfirm($order);
        }
    }

    /** Move an order through its lifecycle (Sales / Admin). */
    public function updateStatus(Order $order, string $status): Order
    {
        match ($status) {
            Order::STATUS_CONFIRMED => $this->confirm($order),
            Order::STATUS_SHIPPING => $this->moveToShipping($order),
            Order::STATUS_DELIVERED => $this->markDelivered($order),
            Order::STATUS_CANCELLED => $this->cancel($order),
            default => throw new DomainException('Trạng thái không hợp lệ.'),
        };

        return $order->refresh()->load('items');
    }

    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $cancellable = [Order::STATUS_PENDING, Order::STATUS_STOCK_ISSUE, Order::STATUS_CONFIRMED];
            if (! in_array($order->status, $cancellable, true)) {
                throw new DomainException('Đơn đang giao hoặc đã kết thúc, không thể hủy.');
            }

            // Only confirmed orders took stock; restoreForOrder finds nothing otherwise.
            $this->inventory->restoreForOrder($order);

            if ($order->points_used > 0) {
                $this->points->credit($order->user, $order->points_used, 'Hoàn điểm do hủy đơn '.$order->code, $order->id);
            }

            // Take back points that were earned on a payment that is now reversed.
            if ($order->points_earned > 0) {
                $reclaim = min($order->points_earned, $order->user->fresh()->points_balance);
                $this->points->debit($order->user, $reclaim, 'Thu hồi điểm do hủy đơn '.$order->code, $order->id);
            }

            $update = ['status' => Order::STATUS_CANCELLED];
            if ($order->isPaid() && $order->payment_method === Order::PAYMENT_VNPAY) {
                $update['payment_status'] = 'refund_pending';
                $update['issue_note'] = 'Khách đã thanh toán online: cần hoàn tiền.';
            }

            $order->update($update);
        });
    }

    /** Runs inside a transaction: replace each product line with one line per batch used. */
    private function allocate(Order $order): void
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_STOCK_ISSUE], true)) {
            throw new DomainException('Chỉ xác nhận được đơn đang chờ xử lý.');
        }

        $lines = $order->items()->whereNull('product_batch_id')->with('product')->get();

        foreach ($lines as $line) {
            if ($line->product === null) {
                throw new DomainException("Sản phẩm \"{$line->product_name}\" không còn tồn tại.");
            }

            $plan = $this->inventory->consume($line->product, $line->quantity, $order->id);

            foreach ($plan as $part) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line->product_id,
                    'product_batch_id' => $part['batch']->id,
                    'batch_code' => $part['batch']->batch_code,
                    'product_name' => $line->product_name,
                    'unit_price' => $line->unit_price,
                    'quantity' => $part['quantity'],
                    'line_total' => $line->unit_price * $part['quantity'],
                ]);
            }

            $line->delete();
        }

        $order->update([
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'issue_note' => null,
        ]);
    }

    private function moveToShipping(Order $order): void
    {
        if ($order->status !== Order::STATUS_CONFIRMED) {
            throw new DomainException('Chỉ đơn đã xác nhận (đã trừ kho) mới chuyển sang đang giao.');
        }

        $order->update(['status' => Order::STATUS_SHIPPING]);
    }

    private function markDelivered(Order $order): void
    {
        if ($order->status !== Order::STATUS_SHIPPING) {
            throw new DomainException('Đơn phải ở trạng thái đang giao trước khi xác nhận đã giao.');
        }

        // Cash on delivery: money is collected at the door, so the order counts as paid now.
        if ($order->paid_at === null) {
            $this->markPaid($order);
        }

        $order->update(['status' => Order::STATUS_DELIVERED, 'delivered_at' => now()]);
    }

    private function newCode(): string
    {
        do {
            $code = 'FN'.now()->format('ymd').strtoupper(Str::random(5));
        } while (Order::where('code', $code)->exists());

        return $code;
    }
}
