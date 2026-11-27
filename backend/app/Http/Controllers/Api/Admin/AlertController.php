<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\ProductBatch;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Red alerts for the back office (Tuần 2 use case 5):
 *  - batches within N days of expiry (N is set by the admin): sell them first / flash sale;
 *  - orders that could not be confirmed for lack of stock;
 *  - subscription deliveries that are short of stock (call the customer to swap products);
 *  - open complaints.
 * Farm staff only receive the batch alerts: they never see orders or customers.
 */
class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = (int) Setting::get('expiry_alert_days');

        $expiring = ProductBatch::query()
            ->sellable()
            ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString())
            ->with(['product:id,name,unit,price', 'farmPlot:id,name'])
            ->orderBy('expires_on')
            ->get()
            ->map(fn (ProductBatch $b) => [
                'id' => $b->id,
                'batch_code' => $b->batch_code,
                'product' => $b->product,
                'farm_plot' => $b->farmPlot?->name,
                'quantity_on_hand' => $b->quantity_on_hand,
                'expires_on' => $b->expires_on->toDateString(),
                'days_left' => (int) now()->startOfDay()->diffInDays($b->expires_on, false),
            ]);

        $alerts = [
            'expiry_alert_days' => $days,
            'flash_sale_discount_percent' => (int) Setting::get('flash_sale_discount_percent'),
            'expiring_batches' => $expiring,
        ];

        if ($request->user()->hasRole('sales')) {
            $alerts['stock_issue_orders'] = Order::query()
                ->where('status', Order::STATUS_STOCK_ISSUE)
                ->with('user:id,name,phone')
                ->latest('id')
                ->get(['id', 'code', 'user_id', 'total', 'issue_note', 'payment_status', 'shipping_name', 'shipping_phone', 'created_at']);

            $alerts['short_deliveries'] = DeliverySchedule::query()
                ->where('status', DeliverySchedule::STATUS_SHORT)
                ->with(['subscription:id,user_id,shipping_name,shipping_phone,subscription_plan_id', 'subscription.plan:id,name'])
                ->orderBy('delivery_date')
                ->get();

            $alerts['open_complaints'] = Complaint::whereIn('status', [Complaint::STATUS_OPEN, Complaint::STATUS_IN_PROGRESS])->count();
        }

        $alerts['total'] = $expiring->count()
            + count($alerts['stock_issue_orders'] ?? [])
            + count($alerts['short_deliveries'] ?? []);

        return response()->json($alerts);
    }
}
