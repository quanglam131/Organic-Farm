<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\VnpayGateway;
use App\Services\ShippingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private VnpayGateway $vnpay,
        private ShippingService $shipping,
    ) {}

    /**
     * Shipping fee for a subtotal. With an address the distance from the farm is looked up;
     * distance_km is only used when the address cannot be found.
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subtotal' => ['required', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'integer', 'min:0', 'max:200'],
        ]);

        [$distance, $source] = $this->distance($data['address'] ?? '', $data['distance_km'] ?? null);

        return response()->json([
            'distance_km' => $distance,
            'source' => $source,
            'shipping_fee' => $this->shipping->fee((int) $data['subtotal'], $distance),
            'farm' => ['name' => config('farm.name'), 'address' => config('farm.address')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'in:'.Order::PAYMENT_COD.','.Order::PAYMENT_VNPAY],
            'points_to_use' => ['nullable', 'integer', 'min:0'],
            'distance_km' => ['nullable', 'integer', 'min:0', 'max:200'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->ensurePaymentAvailable($data['payment_method']);

        // Distance (and so the fee) is computed on the server from the address.
        [$data['distance_km'], $source] = $this->distance($data['shipping_address'], $data['distance_km'] ?? null);
        if ($source === 'manual') {
            $data['note'] = trim(($data['note'] ?? '').' [Khoảng cách do khách nhập: '.$data['distance_km'].' km]');
        }

        $user = $request->user();
        // Only product ids and quantities come from the cart: prices and stock are re-read in place().
        $lines = $user->cartItems()->with('product')->get()
            ->map(fn ($item) => ['product' => $item->product, 'quantity' => $item->quantity])
            ->values()
            ->all();

        $order = $this->orders->place($user, $lines, $data);
        $user->cartItems()->delete();

        return response()->json([
            'order' => $order,
            'payment_url' => $this->paymentUrl($order, $request),
            'message' => $order->payment_method === Order::PAYMENT_COD
                ? 'Đặt hàng thành công. Nhân viên sẽ gọi xác nhận đơn trước khi giao.'
                : 'Đã tạo đơn, vui lòng hoàn tất thanh toán.',
        ], 201);
    }

    /** @return array{0: int, 1: string} distance in km and where it came from: google | osm | manual */
    private function distance(string $address, ?int $typedKm): array
    {
        $found = $this->shipping->distanceTo($address);

        if ($found !== null) {
            return [min($found['distance_km'], (int) config('shop.shipping.max_km')), $found['source']];
        }

        return [(int) ($typedKm ?? 0), 'manual'];
    }

    private function ensurePaymentAvailable(string $method): void
    {
        if ($method === Order::PAYMENT_VNPAY && ! $this->vnpay->isConfigured() && ! config('payments.mock_enabled')) {
            throw new DomainException('Cổng thanh toán VNPay chưa được cấu hình.');
        }
    }

    private function paymentUrl(Order $order, Request $request): ?string
    {
        if ($order->payment_method !== Order::PAYMENT_VNPAY) {
            return null;
        }

        if ($this->vnpay->isConfigured()) {
            return $this->vnpay->paymentUrl($order->code, $order->total, 'Thanh toan don hang '.$order->code, $request->ip());
        }

        // Mock gateway: the frontend shows a simulated payment page for this order.
        return rtrim(config('payments.frontend_url'), '/').'/payment/mock/'.$order->code;
    }
}
