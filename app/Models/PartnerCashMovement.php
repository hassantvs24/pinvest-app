<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Database\Factories\PartnerCashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'type', 'amount', 'note', 'occurred_at', 'commission_settlement_id'])]
class PartnerCashMovement extends Model
{
    /** @use HasFactory<PartnerCashMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Scope: movements within the given moments (exact instants — the
     * same boundary convention as entries: BusinessStats normalizes
     * whole-day strings before calling).
     */
    public function scopeOccurredBetween(Builder $query, Carbon|string|null $from, Carbon|string|null $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('occurred_at', '<=', $to));
    }

    /**
     * Net cash currently held by one partner (or all partners when no
     * user is given). Holds add; returns/spent/adjustments subtract.
     * Optional exact-moment range on occurred_at.
     */
    public static function outstanding(?int $userId = null, ?Carbon $from = null, ?Carbon $to = null): float
    {
        $rows = self::query()
            ->select('type')
            ->selectRaw('SUM(amount) as total')
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->occurredBetween($from, $to)
            ->groupBy('type')
            ->pluck('total', 'type');

        $held = (float) ($rows[CashMovementType::Hold->value] ?? 0);
        $out = (float) ($rows[CashMovementType::Return->value] ?? 0)
            + (float) ($rows[CashMovementType::Spent->value] ?? 0)
            + (float) ($rows[CashMovementType::CommissionAdjustment->value] ?? 0);

        return $held - $out;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissionSettlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }
}
