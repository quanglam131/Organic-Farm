<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sales: confirm orders (this takes the stock with FEFO), ship, deliver, cancel. */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['user:id,name,email,phone', 'items'])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        // Today's orders, served by the (status, created_at) index.
        if ($request->boolean('today')) {
            $query->where('created_at', '>=', now()->startOfDay());
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$term}%")
                ->orWhere('shipping_phone', 'like', "%{$term}%")
                ->orWhere('shipping_name', 'like', "%{$term}%"));
        }

        return response()->json($query->paginate(20));
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,shipping,delivered,cancelled'],
        ]);

        return response()->json($orders->updateStatus($order, $data['status']));
    }
}
