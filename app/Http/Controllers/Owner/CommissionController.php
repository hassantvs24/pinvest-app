<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CashMovementType;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\PartnerCashMovement;
use App\Models\Payout;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CashLock;
use App\Support\CommissionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-only: commission cycles (open/close), payout requests and
 * settlements. The owner opens a cycle with opening cash/investment and
 * closes it manually; at closing, cycle profit × each partner's rate
 * becomes that partner's commission (rate taken at closing time).
 */
class CommissionController extends Controller
{
    /**
     * Show the open cycle (or the open form), pending payout requests,
     * closed cycles and settlements.
     */
    public function index(): View
    {
        $openPeriod = CommissionPeriod::query()->open()->latest('id')->first();

        return view('owner.commissions', [
            'openPeriod' => $openPeriod,
            'openDays' => $openPeriod ? max(1, (int) $openPeriod->opened_at->diffInDays(now()) + 1) : 0,
            'requests' => PayoutRequest::query()->with('user')->pending()->latest('id')->get(),
            'closedPeriods' => CommissionPeriod::query()->closed()
                ->withCount('settlements')
                ->orderByDesc('closed_at')
                ->orderByDesc('id')
                ->get(),
            'settlements' => CommissionSettlement::query()
                ->with(['user', 'period'])
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->paginate(15),
            'totalRate' => CommissionSettlementService::totalActiveRate(),
            'today' => now()->format('Y-m-d'),
            'cashInHand' => BusinessStats::all()['cash_in_hand'],
        ]);
    }

    /**
     * Open a new cycle (only one open at a time). The cycle starts at
     * this exact moment, so closing a cycle and opening the next one
     * the SAME DAY is fine — everything up to the previous close stays
     * in the old cycle, everything after it falls into the new one.
     * Optionally records opening cash and an opening investment entry.
     */
    public function openPeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'investment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $openedAt = now();

        // Lock against a concurrent open creating a second open cycle.
        DB::transaction(function () use ($validated, $openedAt): void {
            abort_if(CommissionPeriod::query()->open()->lockForUpdate()->exists(), 409);
            abort_if(CommissionSettlementService::latestClosedDate() !== null
                && $openedAt->lessThanOrEqualTo(CommissionSettlementService::latestClosedDate()), 409);

            CommissionSettlementService::openPeriod(
                $validated['label'] ?? null,
                $openedAt,
                isset($validated['investment_amount']) ? (float) $validated['investment_amount'] : null,
                $validated['note'] ?? null,
            );
        });

        return back()->with('success', __('messages.period_opened'));
    }

    /**
     * Close summary (preview): shows cycle profit/loss and exactly what
     * each partner will receive — nothing is written to the database.
     */
    public function closePreview(): View|RedirectResponse
    {
        $period = CommissionPeriod::query()->open()->latest('id')->first();

        if (! $period) {
            return redirect()->route('owner.commissions.index');
        }

        $closedAt = now();
        $stats = CommissionSettlementService::periodStats(CommissionSettlementService::effectiveStart($period), $closedAt);
        $profit = $stats['profit'];
        $rows = CommissionSettlementService::settlementRows($profit);
        $totalCommission = array_sum(array_column($rows, 'amount'));
        $pendingEntries = CommissionSettlementService::pendingEntries();

        // Remember which partner rates this preview showed, so the actual
        // close can warn when a rate drifted in between.
        session(['close_preview_rate_snapshot' => CommissionSettlementService::rateSnapshot($rows)]);

        // Partner cash (custody) review for this cycle: what moved during
        // the period and what each partner still holds — so the owner can
        // top up minuses ("give") or take returns BEFORE closing.
        $from = CommissionSettlementService::effectiveStart($period);
        $cycleMovements = PartnerCashMovement::query()
            ->select('user_id', 'type')
            ->selectRaw('SUM(amount) as total')
            ->occurredBetween($from, $closedAt)
            ->groupBy('user_id', 'type')
            ->get()
            ->groupBy('user_id');

        $partnerCash = User::query()->partners()->orderBy('name')->get()
            ->map(function (User $partner) use ($cycleMovements): array {
                $cycle = $cycleMovements->get($partner->id, collect());
                $sum = fn (CashMovementType $type): float => (float) $cycle
                    ->firstWhere('type', $type->value)?->total ?? 0.0;

                return [
                    'user' => $partner,
                    'outstanding' => PartnerCashMovement::outstanding($partner->id),
                    'cycle_hold' => $sum(CashMovementType::Hold),
                    'cycle_spent' => $sum(CashMovementType::Spent),
                    'cycle_return' => $sum(CashMovementType::Return),
                    'cycle_adjustment' => $sum(CashMovementType::CommissionAdjustment),
                ];
            })
            ->filter(fn (array $row): bool => $row['outstanding'] != 0.0
                || $row['cycle_hold'] != 0.0 || $row['cycle_spent'] != 0.0
                || $row['cycle_return'] != 0.0 || $row['cycle_adjustment'] != 0.0)
            ->values();

        return view('owner.close-preview', [
            'period' => $period,
            'closedAt' => $closedAt,
            'pendingEntries' => $pendingEntries,
            'stats' => $stats,
            'profit' => $profit,
            'rows' => $rows,
            'totalCommission' => $totalCommission,
            'ownerShare' => $profit - $totalCommission,
            'partnerCash' => $partnerCash,
            'partnerCashNegative' => $partnerCash->contains(fn (array $row): bool => $row['outstanding'] < 0.0),
        ]);
    }

    /**
     * Close the open cycle: profit/loss is computed and settlements are
     * created per partner (profit × rate). Idempotent per cycle.
     */
    public function closePeriod(Request $request): RedirectResponse
    {
        $period = CommissionPeriod::query()->open()->latest('id')->firstOrFail();

        // The cycle cannot close while any entry awaits the owner's approval.
        $pendingCount = CommissionSettlementService::pendingEntries()->count();

        if ($pendingCount > 0) {
            return redirect()
                ->route('owner.commissions.index')
                ->with('warning', trans_choice(__('messages.close_blocked_pending'), $pendingCount, ['count' => $pendingCount]));
        }

        $result = CommissionSettlementService::closePeriod($period, now());

        // Warn when a partner's rate/active flag changed since the preview,
        // so the settlements never silently differ from what was shown.
        $previewSnapshot = session()->pull('close_preview_rate_snapshot');
        $rateDrifted = $previewSnapshot !== null
            && $previewSnapshot !== CommissionSettlementService::rateSnapshot(
                CommissionSettlementService::settlementRows($result['profit'])
            );
        $driftNote = $rateDrifted ? ' '.__('messages.commission_rate_changed_since_preview') : '';

        if ($result['profit'] <= 0) {
            return redirect()
                ->route('owner.commissions.index')
                ->with('warning', __('messages.period_closed_loss', [
                    'profit' => number_format($result['profit'], 2),
                ]).$driftNote);
        }

        return redirect()
            ->route('owner.commissions.index')
            ->with('success', __('messages.period_closed_profit', [
                'profit' => number_format($result['profit'], 2),
                'count' => $result['created'],
            ]).$driftNote);
    }

    /**
     * Approve a payout request: mark ALL of that partner's pending
     * settlements paid and create the payout entry — atomically.
     */
    public function approveRequest(int $requestId): RedirectResponse
    {
        $payoutRequest = PayoutRequest::query()->with('user')->findOrFail($requestId);

        abort_unless($payoutRequest->status === 'pending', 409);

        $approved = true;

        // Serialize against every other cash-affecting operation: the
        // cash-in-hand check below reads an aggregate SUM that has no
        // row to lock, so the named lock keeps a parallel withdrawal or
        // approval from overdrawing the same cash figure.
        CashLock::acquire();

        try {
            DB::transaction(function () use ($payoutRequest, &$approved): void {
                $payoutRequest->refresh();
                abort_unless($payoutRequest->status === 'pending', 409);

                $settlements = CommissionSettlement::query()
                    ->pending()
                    ->where('user_id', $payoutRequest->user_id)
                    ->lockForUpdate()
                    ->get();

                $total = (float) $settlements->sum('amount');

                if ($total <= 0) {
                    // Nothing left to pay — close the request without a payout.
                    $payoutRequest->update(['status' => 'rejected']);

                    return;
                }

                // Paying commission is cash out — never pay beyond the cash
                // actually in hand.
                $cashInHand = BusinessStats::all()['cash_in_hand'];

                if ($total > $cashInHand) {
                    $approved = false;

                    return;
                }

                CommissionSettlement::query()
                    ->whereIn('id', $settlements->pluck('id'))
                    ->update(['status' => 'paid']);

                Payout::query()->create([
                    'user_id' => $payoutRequest->user_id,
                    'amount' => $total,
                    'note' => __('messages.payout_for_commission', ['name' => $payoutRequest->user->name]),
                    'payout_date' => Carbon::today()->toDateString(),
                ]);

                $payoutRequest->update(['status' => 'approved', 'amount' => $total]);
            });
        } finally {
            CashLock::release();
        }

        if ($approved === false) {
            return back()->with('error', __('messages.payout_exceeds_cash', [
                'cash' => number_format(BusinessStats::all()['cash_in_hand'], 2),
            ]));
        }

        return back()->with('success', __('messages.payout_approved'));
    }

    /**
     * Reject a payout request (settlements stay pending).
     */
    public function rejectRequest(int $requestId): RedirectResponse
    {
        $payoutRequest = PayoutRequest::query()->findOrFail($requestId);

        abort_unless($payoutRequest->status === 'pending', 409);

        $payoutRequest->update(['status' => 'rejected']);

        return back()->with('success', __('messages.request_rejected'));
    }
}
