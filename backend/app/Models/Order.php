<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    // Placed, waiting for Sales to confirm (COD) or for the online payment (VNPay).
    public const STATUS_PENDING = 'pending';
    // Stock has been taken from batches (FEFO); ready to pack.
    public const STATUS_CONFIRMED = 'confirmed';
    // Confirmation failed because the batches could not cover the order: Sales must act.
    public const STATUS_STOCK_ISSUE = 'stock_issue';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_COD = 'cod';
    public const PAYMENT_VNPAY = 'vnpay';
    // Delivery of a prepaid monthly subscription.
    public const PAYMENT_SUBSCRIPTION = 'subscription';

    /** Hours after delivery during which the customer may complain (fresh produce spoils fast). */
    public const COMPLAINT_WINDOW_HOURS = 24;

    protected $fillable = [
        'code',
        'user_id',
        'subscription_id',
        'status',
        'payment_method',
        'payment_status',
        'payment_ref',
        'subtotal',
        'shipping_fee',
        'points_discount',
        'total',
        'points_used',
        'points_earned',
        'distance_km',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'delivery_date',
        'note',
        'issue_note',
        'paid_at',
        'confirmed_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_fee' => 'integer',
            'points_discount' => 'integer',
            'total' => 'integer',
            'points_used' => 'integer',
            'points_earned' => 'integer',
            'distance_km' => 'integer',
            'delivery_date' => 'date',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** Whether the customer can still complain about this order. */
    public function complaintWindowOpen(): bool
    {
        return $this->status === self::STATUS_DELIVERED
            && $this->delivered_at !== null
            && $this->delivered_at->gt(now()->subHours(self::COMPLAINT_WINDOW_HOURS));
    }
}
