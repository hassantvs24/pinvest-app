<?php

namespace App\Support;

use App\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Models\CommissionPeriod;
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
 * @phpstan-type StatsArray array{investment: float, opening_cash: float, purchase: float, cogs: float, expense: float, general_expense: float, product_expense: float, stock_loss: float, stock_value: float, sales: float, commission: float, payout: float, withdrawal: float, net_profit: float, cash_in_hand: float}
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

        // COGS / stock are business-wide concepts: partner-scoped views
        // keep their simple cash-basis figures.
        $cogs = $userId === null ? InventoryService::cogs(
            Carbon::parse($from ?? '1970-01-01'),
            Carbon::parse($to ?? now()->format('Y-m-d')),
        ) : 0.0;
        $generalExpense = $userId === null ? (float) Expense::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->whereHas('expenseHead', fn (Builder $q) => $q->where('cost_type', ExpenseCostType::General->value))
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('entry_date', '<=', $to))
            ->sum('amount') : $expense;
        $stockValue = $userId === null
            ? InventoryService::stockValue(Carbon::parse($to ?? now()->format('Y-m-d')))
            : 0.0;

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

        // Physical cash the cycles started with (per-cycle opening cash).
        $openingCash = (float) CommissionPeriod::query()->sum('opening_cash');

        // Production labour is paid in cash as the run happens.
        $productionLabour = (float) Production::query()
            ->confirmed()
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('entry_date', '<=', $to))
            ->sum('extra_cost');

        // Lost stock costs profit (non-cash: stock value shrinks instead).
        $stockLoss = $userId === null ? InventoryService::stockLossCost(
            Carbon::parse($from ?? '1970-01-01'),
            Carbon::parse($to ?? now()->format('Y-m-d')),
        ) : 0.0;

        $netProfit = $sales - $cogs - $generalExpense - $stockLoss - $commission;
        $cashInHand = $investment + $openingCash + $sales - $purchase - $expense - $productionLabour - $payout - $withdrawal;

        return [
            'investment' => $investment,
            'opening_cash' => $openingCash,
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
