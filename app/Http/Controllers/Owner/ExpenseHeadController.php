<?php

namespace App\Http\Controllers\Owner;

use App\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Owner-only: expense ledger per head — which head costs the most
 * (totals, entry counts, share of all expenses) plus a per-head detail
 * list of every entry. Confirmed amounts count; pending is shown as a
 * memo (like the stock ledger).
 */
class ExpenseHeadController extends Controller
{
    /**
     * All expense heads ranked by confirmed spending (highest first).
     */
    public function index(): View
    {
        $expenses = Expense::query()
            ->with(['expenseHead', 'user', 'item'])
            ->get();

        $confirmedGrand = (float) $expenses->where('status', EntryStatus::Confirmed)->sum('amount');
        $pendingGrand = (float) $expenses->where('status', EntryStatus::Pending)->sum('amount');

        /** @var Collection<int, array{head: ExpenseHead, confirmed_total: float, confirmed_count: int, pending_total: float, pending_count: int, share: float}> $rows */
        $rows = $expenses
            ->groupBy('expense_head_id')
            ->map(function (Collection $group): array {
                $confirmed = $group->where('status', EntryStatus::Confirmed);

                return [
                    'head' => $group->first()->expenseHead,
                    'confirmed_total' => (float) $confirmed->sum('amount'),
                    'confirmed_count' => $confirmed->count(),
                    'pending_total' => (float) $group->where('status', EntryStatus::Pending)->sum('amount'),
                    'pending_count' => $group->where('status', EntryStatus::Pending)->count(),
                ];
            })
            ->filter(fn (array $row): bool => $row['confirmed_total'] > 0.0 || $row['head']->is_active)
            ->map(function (array $row) use ($confirmedGrand): array {
                $row['share'] = $confirmedGrand > 0.0
                    ? round($row['confirmed_total'] / $confirmedGrand * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sort(fn (array $a, array $b): int => [$b['confirmed_total'], $a['head']->name] <=> [$a['confirmed_total'], $b['head']->name])
            ->values();

        return view('owner.expense-heads.index', [
            'rows' => $rows,
            'confirmedGrand' => $confirmedGrand,
            'pendingGrand' => $pendingGrand,
        ]);
    }

    /**
     * One head's ledger: every confirmed entry (paginated) plus the
     * pending ones as a memo.
     */
    public function show(ExpenseHead $head, Request $request): View
    {
        $confirmed = Expense::query()
            ->where('expense_head_id', $head->id)
            ->where('status', EntryStatus::Confirmed->value)
            ->with(['user', 'item'])
            ->latest('entry_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $pending = Expense::query()
            ->where('expense_head_id', $head->id)
            ->where('status', EntryStatus::Pending->value)
            ->with(['user', 'item'])
            ->latest('entry_date')
            ->get();

        return view('owner.expense-heads.show', [
            'head' => $head,
            'rows' => $confirmed,
            'pending' => $pending,
            'total' => (float) Expense::query()
                ->where('expense_head_id', $head->id)
                ->where('status', EntryStatus::Confirmed->value)
                ->sum('amount'),
            'count' => (int) Expense::query()
                ->where('expense_head_id', $head->id)
                ->where('status', EntryStatus::Confirmed->value)
                ->count(),
        ]);
    }
}
