<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customers review products they received. */
class ReviewController extends Controller
{
    public function store(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $request->user()->orders()->where('code', $code)->firstOrFail();

        if ($order->status !== Order::STATUS_DELIVERED) {
            throw new DomainException('Bạn chỉ đánh giá được sản phẩm của đơn đã giao.');
        }
        if ($order->items()->where('product_id', $data['product_id'])->doesntExist()) {
            throw new DomainException('Sản phẩm này không có trong đơn hàng.');
        }

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $data['product_id'], 'order_id' => $order->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null],
        );

        return response()->json($review, 201);
    }
}
