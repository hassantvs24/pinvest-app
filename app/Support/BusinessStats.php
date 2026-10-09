<?php

namespace App\Support;

use App\EntryStatus;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\OwnerWithdrawal;
use App\Models\Payout;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the business calculation logic.
 *
 * Totals count CONFIRMED entries only. Commission is settlement-based
 * (period net profit x partner rate, see CommissionSettlementService) —
 * NOT the per-sale estimate stored on sale rows. Cash in hand subtracts
 * partner payouts and owner profit withdrawals.
 *
 * @phpstan-type StatsArray array{investment: float, purchase: float, expense: float, sales: float, commission: float, payout: float, withdrawal: float, net_profit: float, cash_in_hand: float}
 */
class BusinessStats
{
    /**
     * Get the full set of business totals, optionally scoped to one partner
     * and/or a date period (inclusive from/to dates, Y-m-d strings).
     *
     * @return StatsArray
     */
    public static function all(?int $userId = null, ?string $from = null, ?string $to = null): array
    {
        $investment = (float) Investment::query()
            ->when($from, fn (Builder $q) => $q->whereDate('invested_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('invested_at', '<=', $to))
            ->sum('amount');

        $purchase = self::entrySum(Purchase::query(), 'total', $userId, $from, $to);
        $expense = self::entrySum(Expense::query(), 'amount', $userId, $from, $to);
        $sales = self::entrySum(Sale::query(), 'total', $userId, $from, $to);

        $commission = (float) CommissionSettlement::query()
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->periodBetween($from, $to)
            ->sum('amount');

        $payout = (float) Payout::query()
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($from, fn (Builder $q) => $q->whereDate('payout_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('payout_date', '<=', $to))
            ->sum('amount');

        $withdrawal = (float) OwnerWithdrawal::query()
            ->dateBetween($from, $to)
            ->sum('amount');

        $netProfit = $sales - $purchase - $expense - $commission;
        $cashInHand = $investment + $sales - $purchase - $expense - $payout - $withdrawal;

        return [
            'investment' => $investment,
            'purchase' => $purchase,
            'expense' => $expense,
            'sales' => $sales,
            'commission' => $commission,
            'payout' => $payout,
            'withdrawal' => $withdrawal,
            'net_profit' => $netProfit,
            'cash_in_hand' => $cashInHand,
        ];
    }

    /**
     * Confirmed-only sum on an entry query, optionally for one user
     * and/or a date period on entry_date.
     */
    private static function entrySum(Builder $query, string $column, ?int $userId, ?string $from = null, ?string $to = null): float
    {
        return (float) $query
            ->where('status', EntryStatus::Confirmed->value)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('entry_date', '<=', $to))
            ->sum($column);
    }
}
