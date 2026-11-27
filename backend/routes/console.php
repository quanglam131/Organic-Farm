<?php

use App\Services\InventoryService;
use Illuminate\Support\Facades\Schedule;

// Flag batches past their expiry date every night so they stop being sold.
Schedule::call(fn () => app(InventoryService::class)->expireDueBatches())
    ->name('batches.expire-due')
    ->dailyAt('00:05');

// Generate weekly orders for subscription packages.
Schedule::command('subscriptions:generate-orders')
    ->name('subscriptions.generate-orders')
    ->dailyAt('06:00');
