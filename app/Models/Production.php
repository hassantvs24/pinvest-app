<?php

namespace App\Models;

use App\EntryStatus;
use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'extra_cost', 'note', 'entry_date', 'status', 'confirmed_by', 'confirmed_at'])]
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
            'extra_cost' => 'decimal:2',
            'entry_date' => 'date',
            'status' => EntryStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Confirmed->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Pending->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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
