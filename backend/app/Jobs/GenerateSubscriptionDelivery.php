<?php

namespace App\Jobs;

use App\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Creates the order for one delivery date of a subscription (Tuần 7: heavy work on the queue).
 * The nightly command only dispatches these jobs, one per due delivery.
 */
class GenerateSubscriptionDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryScheduleId) {}

    public function handle(SubscriptionService $subscriptions): void
    {
        $subscriptions->createDeliveryOrder($this->deliveryScheduleId);
    }
}
