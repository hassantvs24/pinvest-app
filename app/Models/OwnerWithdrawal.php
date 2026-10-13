<?php

namespace App\Models;

use Database\Factories\OwnerWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['amount', 'note', 'withdrawn_at'])]
class OwnerWithdrawal extends Model
{
    /** @use HasFactory<OwnerWithdrawalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'withdrawn_at' => 'date',
        ];
    }

    /**
     * Scope: withdrawals within the given dates.
     */
    public function scopeDateBetween(Builder $query, Carbon|string|null $from, Carbon|string|null $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('withdrawn_at', '>=', $from instanceof Carbon ? $from->toDateString() : $from))
            ->when($to, fn (Builder $q) => $q->where('withdrawn_at', '<=', $to instanceof Carbon ? $to->toDateString() : $to));
    }
}
