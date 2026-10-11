<?php

namespace App\Support;

use App\Enums\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\OwnerWithdrawal;
use App\Models\Payout;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the business calculation logic.
 *
 * Totals count CONFIRMED entries only. Net profit is accrual-based:
 * sales − COGS (cost of goods sold, via InventoryService) − general
 * expenses; product costs (transport, labour) stay in stock value
 * until the goods are sold. Cash in hand stays cash-based and
 * subtracts partner payouts and owner profit withdrawals.
 *
 * Boundaries are exact moments: a `Y-m-d` string still means that
 * whole day (start/end of day), a Carbon means the exact instant —
 * so cycle dashboards can scope from a previous close moment
 * (closed_at + 1s) without re-counting the closing day's earlier
 * entries of the closed cycle.
 *
 * @phpstan-type StatsArray array{investment: float, purchase: float, cogs: float, expense: float, general_expense: float, product_expense: float, stock_loss: float, stock_value: float, sales: float, commission: float, payout: float, withdrawal: float, net_profit: float, cash_in_hand: float}
 */
class BusinessStats
{
    /**
     * Get the full set of business totals, optionally scoped to one partner
     * and/or a moment range (inclusive). Strings are whole days; Carbons
     * are exact instants.
     *
     * @return StatsArray
     */
    public static function all(?int $userId = null, Carbon|string|null $from = null, Carbon|string|null $to = null): array
    {
        $from = self::boundary($from, false);
        $to = self::boundary($to, true);

        $investment = (float) Investment::query()
            ->when($from, fn (Builder $q) => $q->where('invested_at', '>=', $from->toDateString()))
            ->when($to, fn (Builder $q) => $q->where('invested_at', '<=', $to->toDateString()))
            ->sum('amount');

        $purchase = self::entrySum(Purchase::query(), 'total', $userId, $from, $to);
        $expense = self::entrySum(Expense::query(), 'amount', $userId, $from, $to);
        $sales = self::entrySum(Sale::query(), 'total', $userId, $from, $to);

        // COGS / stock are business-wide concepts: partner-scoped views
        // keep their simple cash-basis figures.
        $cogs = $userId === null ? InventoryService::cogs(
            $from ?? Carbon::parse('1970-01-01'),
            $to ?? now(),
        ) : 0.0;
        $generalExpense = $userId === null ? (float) Expense::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->whereHas('expenseHead', fn (Builder $q) => $q->where('cost_type', ExpenseCostType::General->value))
            ->when($from, fn (Builder $q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('entry_date', '<=', $to))
            ->sum('amount') : $expense;
        $stockValue = $userId === null
            ? InventoryService::stockValue($to ?? now())
            : 0.0;

        $commission = (float) CommissionSettlement::query()
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->periodBetween($from, $to)
            ->sum('amount');

        $payout = (float) Payout::query()
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($from, fn (Builder $q) => $q->where('payout_date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $q) => $q->where('payout_date', '<=', $to->toDateString()))
            ->sum('amount');

        $withdrawal = (float) OwnerWithdrawal::query()
            ->dateBetween($from, $to)
            ->sum('amount');

        // Production labour is paid in cash as the run happens.
        $productionLabour = (float) Production::query()
            ->confirmed()
            ->when($from, fn (Builder $q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('entry_date', '<=', $to))
            ->sum('extra_cost');

        // Lost stock costs profit (non-cash: stock value shrinks instead).
        $stockLoss = $userId === null ? InventoryService::stockLossCost(
            $from ?? Carbon::parse('1970-01-01'),
            $to ?? now(),
        ) : 0.0;

        $netProfit = $sales - $cogs - $generalExpense - $stockLoss - $commission;
        $cashInHand = $investment + $sales - $purchase - $expense - $productionLabour - $payout - $withdrawal;

        return [
            'investment' => $investment,
            'purchase' => $purchase,
            'cogs' => $cogs,
            'expense' => $expense,
            'general_expense' => $generalExpense,
            'product_expense' => $expense - $generalExpense,
            'stock_loss' => $stockLoss,
            'stock_value' => $stockValue,
            'sales' => $sales,
            'commission' => $commission,
            'payout' => $payout,
            'withdrawal' => $withdrawal,
            'net_profit' => $netProfit,
            'cash_in_hand' => $cashInHand,
        ];
    }

    /**
     * Normalize a boundary: null stays null, a string means that whole
     * day (start or end of it), a Carbon is already an exact moment.
     */
    private static function boundary(Carbon|string|null $value, bool $isEnd): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        $parsed = Carbon::parse($value);

        return $isEnd ? $parsed->endOfDay() : $parsed->startOfDay();
    }

    /**
     * Confirmed-only sum on an entry query, optionally for one user
     * and/or a moment range on entry_date (inclusive).
     */
    private static function entrySum(Builder $query, string $column, ?int $userId, ?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) $query
            ->where('status', EntryStatus::Confirmed->value)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($from, fn (Builder $q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('entry_date', '<=', $to))
            ->sum($column);
    }
}
