<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_SALE = 'sale';
    public const TYPE_CANCEL_RESTORE = 'cancel_restore';
    public const TYPE_WASTE = 'waste';
    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'product_batch_id',
        'order_id',
        'user_id',
        'type',
        'quantity',
        'reason',
        'media_url',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
