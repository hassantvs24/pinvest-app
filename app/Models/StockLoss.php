<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Database\Factories\StockLossFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'item_id', 'quantity', 'note', 'entry_date', 'status', 'confirmed_by', 'confirmed_at'])]
class StockLoss extends Model
{
    /** @use HasFactory<StockLossFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
