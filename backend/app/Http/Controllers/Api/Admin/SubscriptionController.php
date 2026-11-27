<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliverySchedule;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sales view of the monthly packages and their delivery calendar. */
class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query()
            ->with(['user:id,name,email,phone', 'plan:id,name', 'schedules.order:id,code,status'])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /** Deliveries in a date range (default: the next 7 days), for planning harvests. */
    public function schedules(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfDay();
        $to = $request->date('to') ?? now()->addDays(7);

        return response()->json(
            DeliverySchedule::query()
                ->whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])
                ->with(['subscription:id,user_id,shipping_name,shipping_phone,shipping_address,subscription_plan_id', 'subscription.plan:id,name', 'order:id,code,status'])
                ->orderBy('delivery_date')
                ->get()
        );
    }

    /** Try a short delivery again after the farm restocked or the customer accepted a swap. */
    public function retry(DeliverySchedule $schedule, SubscriptionService $subscriptions): JsonResponse
    {
        $order = $subscriptions->retry($schedule);

        return response()->json(['schedule' => $schedule->fresh(), 'order' => $order]);
    }
}
