<?php

namespace App\Models;

use App\EntryStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'item_id', 'quantity', 'unit_price', 'total', 'note', 'entry_date', 'status', 'confirmed_by', 'confirmed_at'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'entry_date' => 'date',
            'status' => EntryStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Scope: only this user's entries.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Pending->value);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Confirmed->value);
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

    /**
     * Formatted total for display, e.g. "৳1,200".
     */
    public function formattedTotal(): string
    {
        return '৳'.number_format((float) $this->total, 2);
    }
}
