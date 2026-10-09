<?php

namespace App\Models;

use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'sale_item_id', 'quantity', 'extra_cost', 'note', 'entry_date'])]
class Production extends Model
{
    /** @use HasFactory<ProductionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'extra_cost' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    /**
     * Scope: entries in a date range (by entry date, inclusive).
     */
    public function scopeDateBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('entry_date', '<=', $to));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The finished goods this run produced (a run may yield several,
     * e.g. melting separates one ornament into gold plus scrap).
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    /**
     * Raw materials consumed by this production.
     */
    public function components(): HasMany
    {
        return $this->hasMany(ProductionComponent::class);
    }
}
