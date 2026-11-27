<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBatch;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batch (lô) based stock management.
 *
 * Stock is never tracked as a single number per product: every sale is taken
 * from specific batches using FEFO (First-Expired, First-Out), and every change
 * is written to inventory_movements for audit.
 */
class InventoryService
{
    /**
     * Plan which batches to take `$quantity` from, earliest expiry first.
     * Must be called inside a DB transaction (batches are row-locked).
     *
     * @return array<int, array{batch: ProductBatch, quantity: int}>
     */
    public function allocateFefo(Product $product, int $quantity): array
    {
        $batches = ProductBatch::query()
            ->sellable()
            ->where('product_id', $product->id)
            ->orderBy('expires_on')
            ->orderBy('harvested_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $plan = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch->quantity_on_hand, $remaining);
            $plan[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new InsufficientStockException(
                "Sản phẩm \"{$product->name}\" không đủ hàng (thiếu {$remaining} {$product->unit})."
            );
        }

        return $plan;
    }

    /** Sellable stock of a product: the sum of its valid batches. */
    public function available(Product $product): int
    {
        return (int) $product->batches()->sellable()->sum('quantity_on_hand');
    }

    /**
     * Take stock for an order using FEFO and log a sale movement per batch.
     * Must be called inside a DB transaction.
     *
     * @return array<int, array{batch: ProductBatch, quantity: int}> what was taken from each batch
     */
    public function consume(Product $product, int $quantity, int $orderId): array
    {
        $plan = $this->allocateFefo($product, $quantity);

        foreach ($plan as $line) {
            /** @var ProductBatch $batch */
            $batch = $line['batch'];
            $batch->decrement('quantity_on_hand', $line['quantity']);

            if ($batch->quantity_on_hand === 0) {
                $batch->update(['status' => ProductBatch::STATUS_DEPLETED]);
            }

            InventoryMovement::create([
                'product_batch_id' => $batch->id,
                'order_id' => $orderId,
                'type' => InventoryMovement::TYPE_SALE,
                'quantity' => -$line['quantity'],
                'reason' => 'Bán theo đơn hàng (FEFO)',
            ]);
        }

        return $plan;
    }

    /** Put back the stock an order consumed (used when an order is cancelled). */
    public function restoreForOrder(Order $order): void
    {
        $sales = InventoryMovement::query()
            ->where('order_id', $order->id)
            ->where('type', InventoryMovement::TYPE_SALE)
            ->with('batch')
            ->get();

        foreach ($sales as $movement) {
            $batch = $movement->batch;
            $restored = -$movement->quantity;
            $batch->increment('quantity_on_hand', $restored);

            if ($batch->status === ProductBatch::STATUS_DEPLETED && $batch->expires_on->gte(now()->startOfDay())) {
                $batch->update(['status' => ProductBatch::STATUS_AVAILABLE]);
            }

            InventoryMovement::create([
                'product_batch_id' => $batch->id,
                'order_id' => $order->id,
                'user_id' => null,
                'type' => InventoryMovement::TYPE_CANCEL_RESTORE,
                'quantity' => $restored,
                'reason' => 'Hoàn kho do hủy đơn '.$order->code,
            ]);
        }
    }

    /** Record internal waste / damaged / destroyed stock (xuất hủy, hao hụt). */
    public function recordWaste(ProductBatch $batch, int $quantity, string $reason, int $userId, ?string $mediaUrl = null): ProductBatch
    {
        return DB::transaction(function () use ($batch, $quantity, $reason, $userId, $mediaUrl) {
            $batch = ProductBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($quantity <= 0 || $quantity > $batch->quantity_on_hand) {
                throw new DomainException('Số lượng hủy không hợp lệ (tồn hiện tại: '.$batch->quantity_on_hand.').');
            }

            $batch->decrement('quantity_on_hand', $quantity);
            $batch->increment('quantity_wasted', $quantity);
            $batch->refresh();

            if ($batch->quantity_on_hand === 0) {
                $batch->update(['status' => ProductBatch::STATUS_WRITTEN_OFF]);
            }

            InventoryMovement::create([
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'type' => InventoryMovement::TYPE_WASTE,
                'quantity' => -$quantity,
                'reason' => $reason,
                'media_url' => $mediaUrl,
            ]);

            return $batch;
        });
    }

    /** Create a new batch (lô) received from a harvest, with its own expiry date. */
    public function receive(array $data, int $userId): ProductBatch
    {
        return DB::transaction(function () use ($data, $userId) {
            $batch = ProductBatch::create([
                ...$data,
                'quantity_on_hand' => $data['quantity_received'],
                'status' => $data['expires_on'] <= now()->toDateString()
                    ? ProductBatch::STATUS_EXPIRED
                    : ProductBatch::STATUS_AVAILABLE,
                'trace_code' => $this->newTraceCode(),
                'batch_code' => $data['batch_code'] ?? $this->newBatchCode(),
            ]);

            InventoryMovement::create([
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'type' => InventoryMovement::TYPE_RECEIVE,
                'quantity' => $batch->quantity_received,
                'reason' => 'Nhập lô từ nông trại',
            ]);

            return $batch;
        });
    }

    /** Mark batches whose expiry date has arrived as expired (they are not sellable on that day). */
    public function expireDueBatches(): int
    {
        return ProductBatch::query()
            ->where('status', ProductBatch::STATUS_AVAILABLE)
            ->whereDate('expires_on', '<=', now()->toDateString())
            ->update(['status' => ProductBatch::STATUS_EXPIRED]);
    }

    private function newTraceCode(): string
    {
        do {
            $code = 'TR'.strtoupper(bin2hex(random_bytes(5)));
        } while (ProductBatch::where('trace_code', $code)->exists());

        return $code;
    }

    private function newBatchCode(): string
    {
        return 'LO-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
    }
}
