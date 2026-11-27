<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SubscriptionPlan::with('items.product:id,name')->latest('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, creating: true);

        $plan = DB::transaction(function () use ($data) {
            $plan = SubscriptionPlan::create(collect($data)->except('items')->all());
            $plan->items()->createMany($data['items']);

            return $plan;
        });

        return response()->json($plan->load('items.product:id,name'), 201);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $data = $this->validated($request, creating: false);

        DB::transaction(function () use ($subscriptionPlan, $data) {
            $subscriptionPlan->update(collect($data)->except('items')->all());

            if (isset($data['items'])) {
                $subscriptionPlan->items()->delete();
                $subscriptionPlan->items()->createMany($data['items']);
            }
        });

        return response()->json($subscriptionPlan->fresh()->load('items.product:id,name'));
    }

    /** Plans with subscribers are switched off instead of deleted. */
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (Subscription::where('subscription_plan_id', $subscriptionPlan->id)->exists()) {
            $subscriptionPlan->update(['is_active' => false]);

            return response()->json(['message' => 'Gói đã có khách đăng ký nên được tạm ngừng mở bán.']);
        }

        $subscriptionPlan->delete();

        return response()->json(['message' => 'Đã xóa gói đăng ký.']);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'integer', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => [$required, 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
