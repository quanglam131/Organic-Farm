<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One delivery date of a prepaid subscription (Lịch giao). */
class DeliverySchedule extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ORDERED = 'ordered';
    // Not enough stock on the day: Sales must contact the customer to swap products.
    public const STATUS_SHORT = 'short';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['subscription_id', 'delivery_date', 'status', 'order_id', 'note'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
