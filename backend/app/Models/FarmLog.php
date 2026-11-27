<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmLog extends Model
{
    public const TYPES = ['planting', 'watering', 'fertilizing', 'pest_control', 'harvesting', 'other'];

    protected $fillable = [
        'farm_plot_id',
        'product_id',
        'product_batch_id',
        'type',
        'description',
        'logged_on',
        'media_url',
        'media_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['logged_on' => 'date'];
    }

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /** The farm staff member who wrote the entry. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
