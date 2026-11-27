<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => $user->points_balance,
            'value_vnd_per_point' => config('shop.points.value_vnd'),
            'earn_rule' => 'Mỗi '.number_format(config('shop.points.earn_per_vnd')).' VND thanh toán = 1 điểm',
            'transactions' => $user->pointTransactions()->latest('id')->limit(50)->get(),
        ]);
    }
}
