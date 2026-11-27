<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Complaint about a delivered order, with mandatory photo evidence (Tuần 2, Tuần 6). */
class Complaint extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public const REASONS = [
        'damaged' => 'Dập nát',
        'spoiled' => 'Hư hỏng, héo úa',
        'wrong_item' => 'Giao sai sản phẩm',
        'missing' => 'Thiếu số lượng',
        'other' => 'Lý do khác',
    ];

    // How Sales settles an accepted complaint: money back, or send the goods again.
    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_REDELIVERY = 'redelivery';

    protected $fillable = [
        'user_id',
        'order_id',
        'reason',
        'subject',
        'content',
        'image_url',
        'status',
        'resolution',
        'admin_reply',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
