<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateSubscriptionOrders extends Command
{
    protected $signature = 'subscriptions:generate-orders';

    protected $description = 'Queue one job per subscription delivery due tomorrow (each job creates and confirms the order)';

    public function handle(SubscriptionService $subscriptions): int
    {
        $result = $subscriptions->dispatchDueDeliveries(CarbonImmutable::today());

        $this->info("Queued {$result['dispatched']} delivery job(s); {$result['skipped']} skipped (paused packages).");

        return self::SUCCESS;
    }
}
