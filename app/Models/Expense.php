<?php

namespace App\Models;

use App\EntryStatus;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'expense_head_id', 'purchase_item_id', 'amount', 'note', 'entry_date', 'status', 'confirmed_by', 'confirmed_at'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    public function expenseHead(): BelongsTo
    {
        return $this->belongsTo(ExpenseHead::class);
    }

    /**
     * Optional purchase item this product cost applies to.
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
