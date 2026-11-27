<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\OrderService;
use App\Services\Payments\VnpayGateway;
use App\Services\SubscriptionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Online payments for orders and for subscription prepayments.
 * A payment reference is an order code (FN...) or SUB{id} for a subscription.
 * When an order is paid its stock is taken immediately with FEFO (OrderService::markPaid).
 */
class PaymentController extends Controller
{
    public function __construct(
        private VnpayGateway $vnpay,
        private OrderService $orders,
        private SubscriptionService $subscriptions,
    ) {}

    /** Browser is redirected here by VNPay after the customer pays. */
    public function vnpayReturn(Request $request): RedirectResponse
    {
        $front = rtrim(config('payments.frontend_url'), '/');
        $input = $request->query();
        $payable = $this->find((string) ($input['vnp_TxnRef'] ?? ''));

        if (! $payable || ! $this->vnpay->verifySignature($input)) {
            return redirect($front.'/orders?payment=invalid');
        }

        $page = $payable instanceof Subscription ? $front.'/subscriptions' : $front.'/orders/'.$payable->code;

        if ($this->vnpay->isSuccessful($input)) {
            $this->settle($payable, $input['vnp_TransactionNo'] ?? null);

            return redirect($page.'?payment=success');
        }

        return redirect($page.'?payment=failed');
    }

    /** Server-to-server notification from VNPay (IPN). Must answer with RspCode. */
    public function vnpayIpn(Request $request): JsonResponse
    {
        $input = $request->query();

        if (! $this->vnpay->verifySignature($input)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        $payable = $this->find((string) ($input['vnp_TxnRef'] ?? ''));
        if (! $payable) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if ((int) ($input['vnp_Amount'] ?? 0) !== $this->amountOf($payable) * 100) {
            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        if ($payable->paid_at !== null) {
            return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        }

        if ($this->vnpay->isSuccessful($input)) {
            $this->settle($payable, $input['vnp_TransactionNo'] ?? null);
        } else {
            $payable->update(['payment_status' => 'failed']);
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }

    /**
     * Mock gateway for local development and demos. Simulates a successful payment
     * without any merchant account. Disabled unless PAYMENT_MOCK_ENABLED=true.
     */
    public function mockComplete(Request $request, string $code): JsonResponse
    {
        abort_unless(config('payments.mock_enabled'), 404);

        $payable = $this->find($code);
        abort_unless($payable && $payable->user_id === $request->user()->id, 404);

        if ($payable instanceof Order && $payable->status === Order::STATUS_CANCELLED) {
            throw new DomainException('Đơn đã bị hủy.');
        }
        if ($payable instanceof Subscription && $payable->status === Subscription::STATUS_CANCELLED) {
            throw new DomainException('Gói đã bị hủy.');
        }

        $this->settle($payable, 'MOCK-'.Str::upper(Str::random(10)));

        return response()->json(
            $payable instanceof Order
                ? ['type' => 'order', 'order' => $payable->refresh()->load('items')]
                : ['type' => 'subscription', 'subscription' => $payable->refresh()->load('plan')]
        );
    }

    /** Payment page data for the mock gateway: what is being paid and how much. */
    public function mockShow(Request $request, string $code): JsonResponse
    {
        abort_unless(config('payments.mock_enabled'), 404);

        $payable = $this->find($code);
        abort_unless($payable && $payable->user_id === $request->user()->id, 404);

        return response()->json([
            'code' => $code,
            'type' => $payable instanceof Order ? 'order' : 'subscription',
            'amount' => $this->amountOf($payable),
            'paid' => $payable->paid_at !== null,
        ]);
    }

    private function find(string $code): Order|Subscription|null
    {
        if (preg_match('/^SUB(\d+)$/', $code, $m)) {
            return Subscription::find((int) $m[1]);
        }

        return Order::where('code', $code)->first();
    }

    private function amountOf(Order|Subscription $payable): int
    {
        return $payable instanceof Order ? $payable->total : $payable->total_price;
    }

    private function settle(Order|Subscription $payable, ?string $reference): void
    {
        if ($payable instanceof Order) {
            $this->orders->markPaid($payable, $reference);
        } else {
            $this->subscriptions->markPaid($payable, $reference);
        }
    }
}
