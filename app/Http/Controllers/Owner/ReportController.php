<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\OwnerWithdrawal;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\Support\InventoryService;
use App\Support\ItemUnits;
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
            'partners' => $this->partnerRows($from, $to),
            'investments' => $this->investmentRows($from, $to),
            'settlements' => $settlements,
            'withdrawals' => OwnerWithdrawal::query()
                ->dateBetween($from, $to)
                ->orderByDesc('withdrawn_at')
                ->get(),
            'salesByItem' => $this->entryRowsByItem(Sale::class, 'item_id', 'item', $from, $to),
            'purchasesByItem' => $this->entryRowsByItem(Purchase::class, 'item_id', 'item', $from, $to),
            'expensesByHead' => $this->expenseRowsByHead($from, $to),
            'salesDetails' => $this->entryDetailRows(Sale::class, $from, $to),
            'purchaseDetails' => $this->entryDetailRows(Purchase::class, $from, $to),
            'expenseDetails' => $this->entryDetailRows(Expense::class, $from, $to),
        ]);
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
