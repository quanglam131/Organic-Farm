<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'description', 'discount_percent', 'is_active'];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionPlanItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'subscription_plan_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
