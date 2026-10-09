<?php

namespace App\Models;

use Database\Factories\CommissionSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'commission_period_id', 'period_start', 'period_end', 'business_profit', 'commission_rate', 'amount', 'status'])]
class CommissionSettlement extends Model
{
    /** @use HasFactory<CommissionSettlementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'business_profit' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: settlements whose period starts within the given dates.
     */
    public function scopePeriodBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('period_start', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('period_start', '<=', $to));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(CommissionPeriod::class, 'commission_period_id');
    }
}
