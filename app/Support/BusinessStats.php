<?php

namespace App\Support;

use App\EntryStatus;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the business calculation logic.
 *
 * Totals count CONFIRMED entries only. All money values are floats for
 * display; storage stays in decimal columns.
 *
 * @phpstan-type StatsArray array{investment: float, purchase: float, expense: float, sales: float, commission: float, payout: float, net_profit: float, cash_in_hand: float}
 */
class BusinessStats
{
    /**
     * Get the full set of business totals, optionally scoped to one partner.
     *
     * @return StatsArray
     */
    public static function all(?int $userId = null): array
    {
        $investment = (float) Investment::query()->sum('amount');
        $purchase = self::sum(Purchase::query(), 'total', $userId);
        $expense = self::sum(Expense::query(), 'amount', $userId);
        $sales = self::sum(Sale::query(), 'total', $userId);
        $commission = self::sum(Sale::query(), 'commission_amount', $userId);
        $payout = (float) Payout::query()
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->sum('amount');

        $netProfit = $sales - $purchase - $expense - $commission;
        $cashInHand = $investment + $sales - $purchase - $expense - $payout;

        return [
            'investment' => $investment,
            'purchase' => $purchase,
            'expense' => $expense,
            'sales' => $sales,
            'commission' => $commission,
            'payout' => $payout,
            'net_profit' => $netProfit,
            'cash_in_hand' => $cashInHand,
        ];
    }

    /**
     * Confirmed-only sum on an entry query, optionally for one user.
     */
    private static function sum(Builder $query, string $column, ?int $userId): float
    {
        return (float) $query
            ->where('status', EntryStatus::Confirmed->value)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->sum($column);
    }
}
