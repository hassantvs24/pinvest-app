<?php

namespace App\Http\Controllers\Owner;

use App\EntryStatus;
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

        // A selected cycle always wins: its date range scopes the whole report.
        $selectedCycle = $this->selectedCycle($request);

        if ($selectedCycle) {
            $from = $selectedCycle->opened_at->format('Y-m-d');
            $to = ($selectedCycle->closed_at ?? Carbon::today())->format('Y-m-d');
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
            'from' => $from,
            'to' => $to,
            'cycles' => CommissionPeriod::query()
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->get(),
            'selectedCycle' => $selectedCycle,
            'openDays' => $selectedCycle
                ? max(1, (int) $selectedCycle->opened_at->diffInDays($selectedCycle->closed_at ?? now()) + 1)
                : 0,
            'ownerShare' => $periodStats['sales'] - $periodStats['purchase'] - $periodStats['expense'] - $periodStats['commission'],
            'stats' => $periodStats,
            'lifetimeCashInHand' => $lifetime['cash_in_hand'],
            'lifetimeInvestment' => $lifetime['investment'],
            'partners' => $this->partnerRows($from, $to),
            'months' => $selectedCycle ? null : $this->monthlyRows(),
            'investments' => $this->investmentRows($from, $to),
            'settlements' => $settlements,
            'withdrawals' => OwnerWithdrawal::query()
                ->dateBetween($from, $to)
                ->orderByDesc('withdrawn_at')
                ->get(),
            'salesByItem' => $this->entryRowsByItem(Sale::class, 'sale_item_id', 'saleItem', $from, $to),
            'purchasesByItem' => $this->entryRowsByItem(Purchase::class, 'purchase_item_id', 'purchaseItem', $from, $to),
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
     * Read and normalize the from/to filters (nullable Y-m-d, from <= to).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;

        if ($from && $to && $from > $to) {
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
    private function partnerRows(?string $from, ?string $to): Collection
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
    private function investmentRows(?string $from, ?string $to): Collection
    {
        return Investment::query()
            ->when($from, fn ($q) => $q->whereDate('invested_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invested_at', '<=', $to))
            ->orderByDesc('invested_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Confirmed sale/purchase entries grouped by item for the period.
     *
     * @return Collection<int, array{name: string, quantity: int, total: float}>
     */
    private function entryRowsByItem(string $entryModel, string $fk, string $relation, ?string $from, ?string $to): Collection
    {
        return $entryModel::query()
            ->with($relation)
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
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
    private function expenseRowsByHead(?string $from, ?string $to): Collection
    {
        return Expense::query()
            ->with('expenseHead')
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
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
    private function entryDetailRows(string $entryModel, ?string $from, ?string $to): Collection
    {
        return $entryModel::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Monthly breakdown for the last 6 calendar months from confirmed
     * entries, grouped in PHP (portable across database engines).
     *
     * @return Collection<int, array{key: string, label: string, sales: float, purchase: float, expense: float, commission: float, net_profit: float}>
     */
    private function monthlyRows(): Collection
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $group = fn (Collection $rows, string $valueField): Collection => $rows
            ->where('status', EntryStatus::Confirmed->value)
            ->filter(fn ($row) => Carbon::parse($row->entry_date)->gte($start))
            ->groupBy(fn ($row) => Carbon::parse($row->entry_date)->format('Y-m'))
            ->map(fn (Collection $g) => (float) $g->sum($valueField));

        $sales = $group(Sale::query()->select('entry_date', 'status', 'total')->get(), 'total');
        $purchase = $group(Purchase::query()->select('entry_date', 'status', 'total')->get(), 'total');
        $expense = $group(Expense::query()->select('entry_date', 'status', 'amount')->get(), 'amount');

        // Commission distributed per month, from settlements whose period
        // starts in that month (settlement-based, not per-sale estimate).
        $commissionByMonth = CommissionSettlement::query()
            ->get()
            ->filter(fn ($row) => Carbon::parse($row->period_start)->gte($start))
            ->groupBy(fn ($row) => Carbon::parse($row->period_start)->format('Y-m'))
            ->map(fn (Collection $g) => (float) $g->sum('amount'));

        $rows = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');

            $s = (float) ($sales[$key] ?? 0);
            $p = (float) ($purchase[$key] ?? 0);
            $e = (float) ($expense[$key] ?? 0);
            $c = (float) ($commissionByMonth[$key] ?? 0);

            $rows->push([
                'key' => $key,
                'label' => $month->format('M Y'),
                'sales' => $s,
                'purchase' => $p,
                'expense' => $e,
                'commission' => $c,
                'net_profit' => $s - $p - $e,
                'owner_share' => $s - $p - $e - $c,
            ]);
        }

        return $rows;
    }
}
