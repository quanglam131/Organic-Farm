<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prepaid one-month package. The customer picks one or more delivery weekdays;
 * each delivery date is a row in delivery_schedules.
 */
class Subscription extends Model
{
    // Waiting for the online prepayment.
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    /** Length of one package. */
    public const PERIOD_DAYS = 28;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'delivery_weekday',
        'delivery_days',
        'starts_on',
        'ends_on',
        'next_order_on',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'payment_method',
        'total_price',
        'payment_status',
        'payment_ref',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_weekday' => 'integer',
            'delivery_days' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_order_on' => 'date',
            'total_price' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** Payment reference sent to the gateway, e.g. SUB12. */
    public function paymentCode(): string
    {
        return 'SUB'.$this->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DeliverySchedule::class)->orderBy('delivery_date');
    }
}
