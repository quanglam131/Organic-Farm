<?php

namespace App\Jobs;

use App\Mail\OrderConfirmed;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Emails the invoice once an order is confirmed. Runs on the queue so checkout never waits for SMTP (Tuần 7). */
class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['user', 'items'])->find($this->orderId);

        if ($order === null || $order->user === null) {
            return;
        }

        Mail::to($order->user->email)->send(new OrderConfirmed($order));
    }
}
