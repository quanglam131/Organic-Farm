<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Loyalty points: earn on paid orders, spend as a discount at checkout. */
class PointsService
{
    public function earnFor(int $orderTotal): int
    {
        return intdiv($orderTotal, config('shop.points.earn_per_vnd'));
    }

    /** Discount (VND) that `$points` can buy on a subtotal, capped by max_discount_percent. */
    public function discountFor(int $points, int $subtotal): int
    {
        $value = config('shop.points.value_vnd');
        $cap = intdiv($subtotal * config('shop.points.max_discount_percent'), 100);

        return min($points * $value, $cap);
    }

    /** Fewest points needed to cover a given discount. */
    public function pointsRequiredFor(int $discount): int
    {
        return (int) ceil($discount / config('shop.points.value_vnd'));
    }

    public function credit(User $user, int $points, string $reason, ?int $orderId = null): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $points, $reason, $orderId) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->increment('points_balance', $points);
            PointTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'reason' => $reason,
                'order_id' => $orderId,
            ]);
        });
    }

    public function debit(User $user, int $points, string $reason, ?int $orderId = null): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $points, $reason, $orderId) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->points_balance < $points) {
                throw new DomainException('Không đủ điểm tích lũy.');
            }

            $user->decrement('points_balance', $points);
            PointTransaction::create([
                'user_id' => $user->id,
                'points' => -$points,
                'reason' => $reason,
                'order_id' => $orderId,
            ]);
        });
    }
}
