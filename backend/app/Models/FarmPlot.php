<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmPlot extends Model
{
    protected $fillable = [
        'code',
        'name',
        'location',
        'area_m2',
        'certification',
        'description',
    ];

    protected function casts(): array
    {
        return ['area_m2' => 'decimal:2'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FarmLog::class)->latest('logged_on');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
