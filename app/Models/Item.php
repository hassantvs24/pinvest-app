<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unified item master: the SAME list is used for buying, selling and
 * production (both components and outputs). Stock flows in from
 * purchases and production outputs, and out through sales, production
 * components and stock losses — one weighted-average pool per item.
 */
#[Fillable(['name', 'unit', 'default_price', 'is_active'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * Scope: only active items (shown in dropdowns).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function productionComponents(): HasMany
    {
        return $this->hasMany(ProductionComponent::class);
    }

    public function productionOutputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    public function stockLosses(): HasMany
    {
        return $this->hasMany(StockLoss::class);
    }
}
