<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductBatch;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $revenueSince = fn ($from) => (int) Order::query()
            ->whereNotNull('paid_at')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where('paid_at', '>=', $from)
            ->sum('total');

        $expiringDays = (int) Setting::get('expiry_alert_days');
        $expiringBatches = ProductBatch::query()->expiringWithin($expiringDays);

        return response()->json([
            'expiry_alert_days' => $expiringDays,
            'orders_today' => Order::where('created_at', '>=', now()->startOfDay())->count(),
            'stock_issue_orders' => Order::where('status', Order::STATUS_STOCK_ISSUE)->count(),
            'wasted_this_month' => (int) InventoryMovement::where('type', InventoryMovement::TYPE_WASTE)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum(DB::raw('-quantity')),
            'revenue_today' => $revenueSince(now()->startOfDay()),
            'revenue_month' => $revenueSince(now()->startOfMonth()),
            'orders_by_status' => Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'open_complaints' => Complaint::whereIn('status', [Complaint::STATUS_OPEN, Complaint::STATUS_IN_PROGRESS])->count(),
            'expiring_batches' => (clone $expiringBatches)->count(),
            'expiring_value_vnd' => (int) (clone $expiringBatches)
                ->join('products', 'products.id', '=', 'product_batches.product_id')
                ->selectRaw('SUM(product_batches.quantity_on_hand * products.price) as value')
                ->value('value'),
            'expired_batches_unhandled' => ProductBatch::where('status', ProductBatch::STATUS_EXPIRED)
                ->where('quantity_on_hand', '>', 0)
                ->count(),
        ]);
    }

    /** Monthly revenue and top products over the last N months (paid orders only). */
    public function revenue(Request $request): JsonResponse
    {
        $months = min(max((int) $request->query('months', 6), 1), 24);
        $from = now()->startOfMonth()->subMonths($months - 1);

        $paidOrders = Order::query()
            ->whereNotNull('paid_at')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where('paid_at', '>=', $from)
            ->get(['paid_at', 'total']);

        $byMonth = [];
        for ($i = 0; $i < $months; $i++) {
            $byMonth[$from->copy()->addMonths($i)->format('Y-m')] = ['orders' => 0, 'revenue' => 0];
        }
        foreach ($paidOrders as $order) {
            $key = $order->paid_at->format('Y-m');
            if (isset($byMonth[$key])) {
                $byMonth[$key]['orders']++;
                $byMonth[$key]['revenue'] += $order->total;
            }
        }

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.paid_at', '>=', $from)
            ->select('order_items.product_name as name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'months' => collect($byMonth)->map(fn ($v, $month) => ['month' => $month] + $v)->values(),
            'top_products' => $topProducts,
        ]);
    }

    /** Waste (xuất hủy / hao hụt) per product over the last 30 days, to measure spoilage. */
    public function waste(): JsonResponse
    {
        return response()->json(
            InventoryMovement::query()
                ->where('inventory_movements.type', InventoryMovement::TYPE_WASTE)
                ->where('inventory_movements.created_at', '>=', now()->subDays(30))
                ->join('product_batches', 'product_batches.id', '=', 'inventory_movements.product_batch_id')
                ->join('products', 'products.id', '=', 'product_batches.product_id')
                ->select('products.name')
                ->selectRaw('SUM(-inventory_movements.quantity) as quantity')
                ->selectRaw('SUM(-inventory_movements.quantity * products.price) as value_vnd')
                ->groupBy('products.name')
                ->orderByDesc('value_vnd')
                ->get()
        );
    }
}
