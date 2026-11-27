<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payments\VnpayGateway;
use App\Services\SubscriptionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customer side of the prepaid monthly packages. */
class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private VnpayGateway $vnpay,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->subscriptions()->with(['plan', 'schedules.order:id,code,status'])->latest('id')->get()
        );
    }

    /** Price and delivery dates of a plan for the chosen weekdays, before subscribing. */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate($this->dayRules() + [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

        return response()->json($this->subscriptions->quote($plan, $data['delivery_days']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->dayRules() + [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'shipping_address' => ['required', 'string', 'max:255'],
        ]);

        if (! $this->vnpay->isConfigured() && ! config('payments.mock_enabled')) {
            throw new DomainException('Cổng thanh toán chưa được cấu hình, chưa thể đăng ký gói.');
        }

        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $subscription = $this->subscriptions->subscribe($request->user(), $plan, $data);

        $paymentUrl = $this->vnpay->isConfigured()
            ? $this->vnpay->paymentUrl($subscription->paymentCode(), $subscription->total_price, 'Thanh toan goi dinh ky '.$subscription->paymentCode(), $request->ip())
            : rtrim(config('payments.frontend_url'), '/').'/payment/mock/'.$subscription->paymentCode();

        return response()->json([
            'subscription' => $subscription->load(['plan', 'schedules']),
            'payment_url' => $paymentUrl,
        ], 201);
    }

    /** Pause, resume or cancel a package. */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:active,paused,cancelled'],
        ]);

        return response()->json(
            $this->subscriptions->changeStatus($subscription, $data['status'])->load(['plan', 'schedules'])
        );
    }

    private function dayRules(): array
    {
        return [
            'delivery_days' => ['required', 'array', 'min:1', 'max:3'],
            'delivery_days.*' => ['integer', 'between:0,6', 'distinct'],
        ];
    }
}
