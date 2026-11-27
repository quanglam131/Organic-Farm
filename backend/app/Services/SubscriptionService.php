<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\GenerateSubscriptionDelivery;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prepaid monthly packages (Tuần 2 use case 2).
 *
 * The customer chooses a plan and one or more weekdays (e.g. Tuesday and Friday) and pays for
 * 28 days up front. Every delivery date is stored in delivery_schedules. The day before each date
 * a queued job creates the order and takes its stock (FEFO). If the farm is short of stock the
 * delivery is marked "short" so Sales can call the customer and offer an equivalent product.
 */
class SubscriptionService
{
    /** Orders are created this many days before the delivery date, so the farm can pick and pack. */
    public const LEAD_DAYS = 1;

    public function __construct(private OrderService $orders) {}

    /**
     * @param  array<int, int>  $days  weekdays, 0 = Sunday
     * @return array{per_delivery: int, dates: array<int, string>, total: int}
     */
    public function quote(SubscriptionPlan $plan, array $days, ?CarbonImmutable $from = null): array
    {
        $plan->loadMissing('items.product');

        $perDelivery = (int) $plan->items->sum(function ($item) use ($plan) {
            $price = $item->product?->price ?? 0;

            return intdiv($price * (100 - $plan->discount_percent), 100) * $item->quantity;
        });

        $dates = $this->deliveryDates($days, $from ?? CarbonImmutable::today()->addDay());

        return ['per_delivery' => $perDelivery, 'dates' => $dates, 'total' => $perDelivery * count($dates)];
    }

    public function subscribe(User $user, SubscriptionPlan $plan, array $data): Subscription
    {
        if (! $plan->is_active) {
            throw new DomainException('Gói này hiện không mở đăng ký.');
        }
        if ($plan->items()->doesntExist()) {
            throw new DomainException('Gói này chưa có sản phẩm.');
        }

        $days = collect($data['delivery_days'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
        $quote = $this->quote($plan, $days);

        if ($quote['dates'] === []) {
            throw new DomainException('Không có ngày giao nào trong gói, hãy chọn lại thứ giao hàng.');
        }

        return DB::transaction(function () use ($user, $plan, $data, $days, $quote) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                // Prepaid online: the package starts once the payment arrives.
                'status' => Subscription::STATUS_PENDING_PAYMENT,
                'delivery_weekday' => $days[0],
                'delivery_days' => $days,
                'starts_on' => $quote['dates'][0],
                'ends_on' => end($quote['dates']),
                'next_order_on' => $quote['dates'][0],
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_address' => $data['shipping_address'],
                'payment_method' => Order::PAYMENT_VNPAY,
                'total_price' => $quote['total'],
                'payment_status' => 'unpaid',
            ]);

            foreach ($quote['dates'] as $date) {
                DeliverySchedule::create([
                    'subscription_id' => $subscription->id,
                    'delivery_date' => $date,
                    'status' => DeliverySchedule::STATUS_SCHEDULED,
                ]);
            }

            return $subscription;
        });
    }

    /** The prepayment arrived (idempotent): the package becomes active. */
    public function markPaid(Subscription $subscription, ?string $paymentRef = null): void
    {
        Subscription::whereKey($subscription->id)
            ->whereNull('paid_at')
            ->update([
                'payment_status' => 'paid',
                'payment_ref' => $paymentRef,
                'paid_at' => now(),
                'status' => Subscription::STATUS_ACTIVE,
            ]);
    }

    /**
     * Queue one job per delivery that is due (date <= today + LEAD_DAYS).
     * Deliveries of paused packages that fall in that window are skipped.
     *
     * @return array{dispatched: int, skipped: int}
     */
    public function dispatchDueDeliveries(CarbonImmutable $today): array
    {
        $until = $today->addDays(self::LEAD_DAYS)->toDateString();

        $skipped = DeliverySchedule::query()
            ->where('status', DeliverySchedule::STATUS_SCHEDULED)
            ->whereDate('delivery_date', '<=', $until)
            ->whereHas('subscription', fn ($q) => $q->where('status', Subscription::STATUS_PAUSED))
            ->update(['status' => DeliverySchedule::STATUS_SKIPPED, 'note' => 'Khách tạm dừng gói']);

        $due = DeliverySchedule::query()
            ->where('status', DeliverySchedule::STATUS_SCHEDULED)
            ->whereDate('delivery_date', '<=', $until)
            ->whereHas('subscription', fn ($q) => $q->where('status', Subscription::STATUS_ACTIVE)->whereNotNull('paid_at'))
            ->pluck('id');

        foreach ($due as $id) {
            GenerateSubscriptionDelivery::dispatch($id);
        }

        return ['dispatched' => $due->count(), 'skipped' => $skipped];
    }

    /** Create and confirm the order for one delivery. Safe to call twice: only a "scheduled" row is processed. */
    public function createDeliveryOrder(int $scheduleId): ?Order
    {
        // Claim the row atomically so two workers never create the same delivery.
        $claimed = DeliverySchedule::whereKey($scheduleId)
            ->where('status', DeliverySchedule::STATUS_SCHEDULED)
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            return null;
        }

        $schedule = DeliverySchedule::with('subscription.user', 'subscription.plan.items.product')->findOrFail($scheduleId);
        $subscription = $schedule->subscription;

        $lines = $subscription->plan->items
            ->filter(fn ($item) => $item->product !== null && $item->product->is_active)
            ->map(fn ($item) => ['product' => $item->product, 'quantity' => $item->quantity])
            ->values()
            ->all();

        try {
            $order = $this->orders->place($subscription->user, $lines, [
                'shipping_name' => $subscription->shipping_name,
                'shipping_phone' => $subscription->shipping_phone,
                'shipping_address' => $subscription->shipping_address,
                'payment_method' => Order::PAYMENT_SUBSCRIPTION,
                'subscription' => $subscription,
                'discount_percent' => $subscription->plan->discount_percent,
                'delivery_date' => $schedule->delivery_date->toDateString(),
                'note' => 'Giao theo gói định kỳ #'.$subscription->id,
            ]);
        } catch (DomainException $e) {
            $this->markShort($schedule, $e->getMessage());

            return null;
        }

        $confirmed = $this->orders->tryConfirm($order);

        if ($confirmed) {
            $schedule->update(['status' => DeliverySchedule::STATUS_ORDERED, 'order_id' => $order->id, 'note' => null]);
        } else {
            $this->markShort($schedule, $order->fresh()->issue_note ?? 'Không đủ hàng', $order->id);
        }

        $this->advance($subscription);

        return $order->refresh();
    }

    /** Sales retries a short delivery after restocking or swapping products. */
    public function retry(DeliverySchedule $schedule): ?Order
    {
        if ($schedule->status !== DeliverySchedule::STATUS_SHORT) {
            throw new DomainException('Chỉ thử lại được lịch giao đang thiếu hàng.');
        }

        // A previous attempt may have left a stock_issue order: cancel it before trying again.
        if ($schedule->order && $schedule->order->status === Order::STATUS_STOCK_ISSUE) {
            $this->orders->cancel($schedule->order);
        }

        $schedule->update(['status' => DeliverySchedule::STATUS_SCHEDULED, 'order_id' => null]);

        $order = $this->createDeliveryOrder($schedule->id);

        if ($order === null || $order->status !== Order::STATUS_CONFIRMED) {
            throw new InsufficientStockException($schedule->fresh()->note ?? 'Vẫn chưa đủ hàng để giao.');
        }

        return $order;
    }

    /** Pause, resume or cancel a package. */
    public function changeStatus(Subscription $subscription, string $status): Subscription
    {
        if (in_array($subscription->status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_COMPLETED], true)) {
            throw new DomainException('Gói đã kết thúc, không thể thay đổi.');
        }
        if ($subscription->status === Subscription::STATUS_PENDING_PAYMENT && $status !== Subscription::STATUS_CANCELLED) {
            throw new DomainException('Gói chưa được thanh toán.');
        }

        DB::transaction(function () use ($subscription, $status) {
            if ($status === Subscription::STATUS_CANCELLED) {
                $subscription->schedules()
                    ->where('status', DeliverySchedule::STATUS_SCHEDULED)
                    ->update(['status' => DeliverySchedule::STATUS_CANCELLED]);
            }

            $subscription->update(['status' => $status]);
        });

        return $subscription->refresh();
    }

    /**
     * Every date in [from, from + PERIOD_DAYS) that falls on one of the weekdays.
     *
     * @param  array<int, int>  $days
     * @return array<int, string>
     */
    public function deliveryDates(array $days, CarbonImmutable $from): array
    {
        $dates = [];

        for ($i = 0; $i < Subscription::PERIOD_DAYS; $i++) {
            $date = $from->addDays($i);
            if (in_array((int) $date->dayOfWeek, $days, true)) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }

    private function markShort(DeliverySchedule $schedule, string $reason, ?int $orderId = null): void
    {
        $schedule->update([
            'status' => DeliverySchedule::STATUS_SHORT,
            'order_id' => $orderId,
            'note' => $reason,
        ]);

        Log::warning('Subscription delivery short of stock', ['schedule' => $schedule->id, 'reason' => $reason]);
    }

    /** Point next_order_on at the next open delivery, or close the package when none is left. */
    private function advance(Subscription $subscription): void
    {
        $next = $subscription->schedules()->where('status', DeliverySchedule::STATUS_SCHEDULED)->first();

        if ($next === null) {
            $subscription->update(['status' => Subscription::STATUS_COMPLETED]);
        } else {
            $subscription->update(['next_order_on' => $next->delivery_date]);
        }
    }
}
