<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\OwnerWithdrawal;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLoss;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Owner-only: essential business reports (period summary, per-partner
 * report with commission due, monthly breakdown). No charts — simple
 * translated cards/rows for non-technical users.
 */
class ReportController extends Controller
{
    /**
     * Show the reports page with date-range filtering.
     */
    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        // A selected cycle always wins: its exact moment range (the
        // previous close + 1s → the close/open moment) scopes the whole
        // report, so the closing day's post-close entries never leak into
        // the closed cycle's report and pre-open entries never leak into
        // an open one. The date strings stay for display.
        $selectedCycle = $this->selectedCycle($request);

        if ($selectedCycle) {
            $from = CommissionSettlementService::periodStart($selectedCycle);
            $to = $selectedCycle->closed_at ?? now();
        }

        // Period summary + full-lifetime balance figures.
        $periodStats = BusinessStats::all(null, $from, $to);
        $lifetime = BusinessStats::all();

        $settlements = CommissionSettlement::query()
            ->with('user')
            ->when(
                $selectedCycle,
                fn ($q) => $q->where('commission_period_id', $selectedCycle->id),
                fn ($q) => $q->periodBetween($from, $to),
            )
            ->orderByDesc('period_start')
            ->orderBy('user_id')
            ->get();

        return view('owner.reports', [
            'from' => $from instanceof Carbon ? $from->toDateString() : $from,
            'to' => $to instanceof Carbon ? $to->toDateString() : $to,
            'cycles' => CommissionPeriod::query()
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->get(),
            'selectedCycle' => $selectedCycle,
            'openDays' => $selectedCycle
                ? max(1, (int) $selectedCycle->opened_at->diffInDays($selectedCycle->closed_at ?? now()) + 1)
                : 0,
            'ownerShare' => $periodStats['net_profit'],
            'stats' => $periodStats,
            'stockRows' => InventoryService::stockRows($to instanceof Carbon ? $to : Carbon::parse($to ?? now()->toDateString())->endOfDay()),
            'lifetimeCashInHand' => $lifetime['cash_in_hand'],
            'lifetimeInvestment' => $lifetime['investment'],
            'insights' => $this->businessInsights($periodStats, (float) $lifetime['cash_in_hand'], $from, $to),
            'ratios' => $this->ratioCards($periodStats, (float) $lifetime['cash_in_hand'], $from, $to),
            'trend' => $selectedCycle ? collect() : $this->monthlyTrend($to ?? now()),
            'partners' => $this->partnerRows($from, $to),
            'investments' => $this->investmentRows($from, $to),
            'settlements' => $settlements,
            'withdrawals' => OwnerWithdrawal::query()
                ->dateBetween($from, $to)
                ->orderByDesc('withdrawn_at')
                ->get(),
            'productPerformance' => $this->productPerformance($from, $to),
            'purchasesByItem' => $this->entryRowsByItem(Purchase::class, 'item_id', 'item', $from, $to),
            'expensesByHead' => $this->expenseRowsByHead($from, $to),
            'salesDetails' => $this->entryDetailRows(Sale::class, $from, $to),
            'purchaseDetails' => $this->entryDetailRows(Purchase::class, $from, $to),
            'expenseDetails' => $this->entryDetailRows(Expense::class, $from, $to),
        ]);
    }

    /**
     * Business-health insight rows for the top of the report: each one
     * names a concrete issue (or confirms the business is healthy) with
     * a tone and icon the Blade renders as an alert card.
     *
     * @param  array{net_profit: float, sales: float, general_expense: float, stock_value: float, withdrawal: float}  $periodStats
     * @return Collection<int, array{tone: string, icon: string, message: string, link?: string, link_label?: string}>
     */
    private function businessInsights(array $periodStats, float $lifetimeCashInHand, ?Carbon $from, ?Carbon $to): Collection
    {
        $insights = collect();
        $sales = $periodStats['sales'];
        $netProfit = $periodStats['net_profit'];
        $marginRate = $sales > 0.0 ? $netProfit / $sales : null;

        if ($lifetimeCashInHand < 0.0) {
            $insights->push([
                'tone' => 'danger',
                'icon' => 'exclamation-triangle',
                'message' => __('messages.insight_cash_negative', ['cash' => Money::format($lifetimeCashInHand)]),
                'link' => route('owner.investments.index'),
                'link_label' => __('messages.add_cash'),
            ]);
        }

        if ($sales > 0.0 && $netProfit < 0.0) {
            $insights->push([
                'tone' => 'danger',
                'icon' => 'arrow-trending-down',
                'message' => __('messages.insight_loss_period', ['amount' => Money::format(abs($netProfit))]),
            ]);
        }

        $commissionDue = (float) CommissionSettlement::query()->pending()->sum('amount');

        if ($commissionDue > 0.0 && $commissionDue > $lifetimeCashInHand) {
            $insights->push([
                'tone' => 'danger',
                'icon' => 'credit-card',
                'message' => __('messages.insight_commission_over_cash', [
                    'due' => Money::format($commissionDue),
                    'cash' => Money::format($lifetimeCashInHand),
                ]),
                'link' => route('owner.commissions.index'),
                'link_label' => __('messages.commissions'),
            ]);
        }

        $totalRate = CommissionSettlementService::totalActiveRate();

        if ($totalRate > 100.0) {
            $insights->push([
                'tone' => 'danger',
                'icon' => 'receipt-percent',
                'message' => __('messages.rate_over_100', ['total' => number_format($totalRate, 2)]),
                'link' => route('owner.partners.index'),
                'link_label' => __('messages.partners'),
            ]);
        }

        $pendingCount = Sale::query()->pending()->count()
            + Purchase::query()->pending()->count()
            + Expense::query()->pending()->count()
            + Production::query()->pending()->count()
            + StockLoss::query()->pending()->count();

        if ($pendingCount > 0) {
            $insights->push([
                'tone' => 'warning',
                'icon' => 'clock',
                'message' => trans_choice(__('messages.insight_pending_entries'), $pendingCount, ['count' => $pendingCount]),
                'link' => route('owner.entries.index', ['status' => 'pending']),
                'link_label' => __('messages.pending_entries'),
            ]);
        }

        if ($marginRate !== null && $marginRate < 0.05) {
            $insights->push([
                'tone' => 'warning',
                'icon' => 'chart-bar',
                'message' => __('messages.insight_low_margin', ['percent' => number_format($marginRate * 100, 1)]),
            ]);
        } elseif ($marginRate !== null && $marginRate >= 0.15) {
            $insights->push([
                'tone' => 'success',
                'icon' => 'arrow-trending-up',
                'message' => __('messages.insight_healthy_margin', ['percent' => number_format($marginRate * 100, 1)]),
            ]);
        }

        $expenseRatio = $sales > 0.0 ? $periodStats['general_expense'] / $sales : null;

        if ($expenseRatio !== null && $expenseRatio > 0.30) {
            $insights->push([
                'tone' => 'warning',
                'icon' => 'currency-dollar',
                'message' => __('messages.insight_high_expense', ['percent' => number_format($expenseRatio * 100, 1)]),
            ]);
        }

        if ($from !== null && $to !== null && $sales > 0.0) {
            $days = max(1, (int) $from->diffInDays($to) + 1);
            $monthlySales = $sales / $days * 30;
            $stockMonths = $monthlySales > 0.0 ? $periodStats['stock_value'] / $monthlySales : null;

            if ($stockMonths !== null && $stockMonths >= 3.0) {
                $insights->push([
                    'tone' => 'warning',
                    'icon' => 'building-storefront',
                    'message' => __('messages.insight_stock_heavy', [
                        'value' => Money::format($periodStats['stock_value']),
                        'months' => number_format($stockMonths, 1),
                    ]),
                    'link' => route('owner.stock.index'),
                    'link_label' => __('messages.stock_report'),
                ]);
            }
        }

        if ($periodStats['withdrawal'] > 0.0 && $netProfit >= 0.0 && $periodStats['withdrawal'] > $netProfit) {
            $insights->push([
                'tone' => 'warning',
                'icon' => 'building-library',
                'message' => __('messages.insight_withdrawal_over_profit', [
                    'withdrawal' => Money::format($periodStats['withdrawal']),
                    'profit' => Money::format($netProfit),
                ]),
                'link' => route('owner.withdrawals.index'),
                'link_label' => __('messages.withdrawal_report'),
            ]);
        }

        foreach (InventoryService::warnings($to ?? now()) as $warning) {
            $insights->push([
                'tone' => 'warning',
                'icon' => 'exclamation-triangle',
                'message' => $warning,
            ]);
        }

        if ($insights->isEmpty()) {
            $insights->push([
                'tone' => 'success',
                'icon' => 'check-circle',
                'message' => __('messages.insight_all_good'),
            ]);
        }

        return $insights;
    }

    /**
     * Key performance ratios shown as compact cards under the summary.
     *
     * @param  array{sales: float, net_profit: float, general_expense: float, stock_value: float}  $periodStats
     * @return array{profit_margin: ?float, expense_ratio: ?float, stock_months: ?float, cash_after_commission: float}
     */
    private function ratioCards(array $periodStats, float $lifetimeCashInHand, ?Carbon $from, ?Carbon $to): array
    {
        $sales = $periodStats['sales'];
        $commissionDue = (float) CommissionSettlement::query()->pending()->sum('amount');
        $stockMonths = null;

        if ($from !== null && $to !== null && $sales > 0.0) {
            $days = max(1, (int) $from->diffInDays($to) + 1);
            $monthlySales = $sales / $days * 30;
            $stockMonths = $monthlySales > 0.0 ? $periodStats['stock_value'] / $monthlySales : null;
        }

        return [
            'profit_margin' => $sales > 0.0 ? $periodStats['net_profit'] / $sales * 100 : null,
            'expense_ratio' => $sales > 0.0 ? $periodStats['general_expense'] / $sales * 100 : null,
            'stock_months' => $stockMonths,
            'cash_after_commission' => $lifetimeCashInHand - $commissionDue,
        ];
    }

    /**
     * Last six full months of sales, purchases, general expenses and
     * accrual profit (sales − COGS − general expense − stock loss), for
     * the growth trend table on non-cycle report views.
     *
     * @return Collection<int, array{label: string, sales: float, purchase: float, expense: float, profit: float, margin_rate: ?float, sales_bar: float, profit_bar: float, profit_negative: bool}>
     */
    private function monthlyTrend(Carbon $to): Collection
    {
        $from = $to->copy()->subMonths(5)->startOfMonth();

        $sales = Sale::query()
            ->confirmed()
            ->whereBetween('entry_date', [$from, $to])
            ->get()
            ->groupBy(fn (Sale $sale): string => $sale->entry_date->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('total'));

        $purchases = Purchase::query()
            ->confirmed()
            ->whereBetween('entry_date', [$from, $to])
            ->get()
            ->groupBy(fn (Purchase $purchase): string => $purchase->entry_date->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('total'));

        $expenses = Expense::query()
            ->confirmed()
            ->whereHas('expenseHead', fn (Builder $query) => $query->where('cost_type', ExpenseCostType::General->value))
            ->whereBetween('entry_date', [$from, $to])
            ->get()
            ->groupBy(fn (Expense $expense): string => $expense->entry_date->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('amount'));

        $costs = InventoryService::monthlyCosts($from, $to);
        $rows = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $to->copy()->subMonths($i)->startOfMonth();
            $key = $month->format('Y-m');
            $monthSales = $sales[$key] ?? 0.0;
            $cogs = $costs[$key]['cogs'] ?? 0.0;
            $stockLoss = $costs[$key]['stock_loss'] ?? 0.0;
            $generalExpense = $expenses[$key] ?? 0.0;
            $profit = $monthSales - $cogs - $generalExpense - $stockLoss;

            $rows[] = [
                'label' => $month->format('M Y'),
                'sales' => $monthSales,
                'purchase' => $purchases[$key] ?? 0.0,
                'expense' => $generalExpense,
                'profit' => $profit,
                'margin_rate' => $monthSales > 0.0 ? $profit / $monthSales : null,
            ];
        }

        $maxSales = max(1.0, (float) collect($rows)->max('sales'));
        $maxProfit = max(1.0, (float) collect($rows)->max(fn (array $row): float => abs($row['profit'])));

        return collect($rows)->map(function (array $row) use ($maxSales, $maxProfit): array {
            return [
                ...$row,
                'sales_bar' => round($row['sales'] / $maxSales * 100, 1),
                'profit_bar' => round(abs($row['profit']) / $maxProfit * 100, 1),
                'profit_negative' => $row['profit'] < 0.0,
            ];
        });
    }

    /**
     * Per-item sales performance (sales, COGS, gross profit) for the
     * selected period — the top-products list on the sales report.
     *
     * @return Collection<int, array{name: string, unit: string, quantity: float, sales: float, cogs: float, margin: float, margin_rate: float}>
     */
    private function productPerformance(?Carbon $from, ?Carbon $to): Collection
    {
        return InventoryService::itemSalesPerformance($from, $to);
    }

    /**
     * Resolve the cycle selected via the ?cycle= filter (null when absent
     * or not a real period — the caller then falls back to the date range).
     */
    private function selectedCycle(Request $request): ?CommissionPeriod
    {
        $cycleId = $request->query('cycle');

        if (! is_numeric($cycleId)) {
            return null;
        }

        return CommissionPeriod::query()->find((int) $cycleId);
    }

    /**
     * Read and normalize the from/to filters (nullable whole-day Carbon
     * boundaries, from <= to) — a picked date means that entire day.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? Carbon::parse($from)->startOfDay() : null;
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? Carbon::parse($to)->endOfDay() : null;

        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Per-partner report rows for the selected period, plus lifetime
     * commission due (earned all-time minus paid all-time).
     *
     * @return Collection<int, array{name: string, commission_rate: float, sales: float, commission: float, purchase: float, expense: float, payout: float, commission_due: float}>
     */
    private function partnerRows(Carbon|string|null $from, Carbon|string|null $to): Collection
    {
        return User::query()
            ->partners()
            ->orderBy('name')
            ->get()
            ->map(function (User $partner) use ($from, $to): array {
                $period = BusinessStats::all($partner->id, $from, $to);

                return [
                    'name' => $partner->name,
                    'commission_rate' => (float) $partner->commission_rate,
                    'sales' => $period['sales'],
                    'commission' => $period['commission'],
                    'purchase' => $period['purchase'],
                    'expense' => $period['expense'],
                    'payout' => $period['payout'],
                    'commission_due' => CommissionSettlementService::pendingDue($partner->id),
                ];
            });
    }

    /**
     * Investment entries for the selected period (newest first).
     *
     * @return Collection<int, Investment>
     */
    private function investmentRows(Carbon|string|null $from, Carbon|string|null $to): Collection
    {
        return Investment::query()
            ->when($from, fn ($q) => $q->where('invested_at', '>=', $from instanceof Carbon ? $from->toDateString() : $from))
            ->when($to, fn ($q) => $q->where('invested_at', '<=', $to instanceof Carbon ? $to->toDateString() : $to))
            ->orderByDesc('invested_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Confirmed sale/purchase entries grouped by item for the period.
     *
     * @return Collection<int, array{name: string, quantity: int, total: float}>
     */
    private function entryRowsByItem(string $entryModel, string $fk, string $relation, Carbon|string|null $from, Carbon|string|null $to): Collection
    {
        return $entryModel::query()
            ->with($relation)
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->get()
            ->groupBy($fk)
            ->map(fn (Collection $group) => [
                'name' => $group->first()->{$relation}->name ?? '—',
                'unit' => ItemUnits::label($group->first()->{$relation}->unit ?? null),
                'quantity' => (int) $group->sum('quantity'),
                'total' => (float) $group->sum('total'),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * Confirmed expenses grouped by expense head for the period.
     *
     * @return Collection<int, array{name: string, total: float}>
     */
    private function expenseRowsByHead(Carbon|string|null $from, Carbon|string|null $to): Collection
    {
        return Expense::query()
            ->with('expenseHead')
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->get()
            ->groupBy('expense_head_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->expenseHead->name ?? '—',
                'total' => (float) $group->sum('amount'),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * Individual confirmed entries in the period (newest first), for the
     * per-cycle entry detail lists.
     *
     * @return Collection<int, Sale|Purchase|Expense>
     */
    private function entryDetailRows(string $entryModel, Carbon|string|null $from, Carbon|string|null $to): Collection
    {
        return $entryModel::query()
            ->with($entryModel === Expense::class ? 'expenseHead' : 'item')
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }
}
