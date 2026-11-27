<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->orders()->with('items')->latest('id')->paginate(10)
        );
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $order = $request->user()->orders()
            // Each line shows the batch it came from, with its QR trace code.
            ->with(['items.batch:id,trace_code,harvested_on,expires_on', 'complaints:id,order_id,status,reason,created_at'])
            ->where('code', $code)
            ->firstOrFail();

        $order->setAttribute('can_complain', $order->complaintWindowOpen());
        $order->setAttribute('complaint_deadline', $order->delivered_at?->copy()->addHours(Order::COMPLAINT_WINDOW_HOURS));
        $order->setAttribute('my_reviews', $request->user()->reviews()->where('order_id', $order->id)->get(['id', 'product_id', 'rating', 'comment']));

        return response()->json($order);
    }

    public function cancel(Request $request, string $code, OrderService $orders): JsonResponse
    {
        $order = $request->user()->orders()->where('code', $code)->firstOrFail();
        $orders->cancel($order);

        return response()->json($order->refresh()->load('items'));
    }
}
