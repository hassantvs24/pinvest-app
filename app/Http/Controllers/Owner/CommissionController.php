<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Payout;
use App\Models\PayoutRequest;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-only: commission periods (open/close), payout requests and
 * settlements. The owner opens a period with opening cash/investment and
 * closes it manually; profit and commissions are computed at closing.
 */
class CommissionController extends Controller
{
    /**
     * Show the open period (or the open form), pending payout requests,
     * closed periods and settlements.
     */
    public function index(): View
    {
        $openPeriod = CommissionPeriod::query()->open()->latest('id')->first();

        return view('owner.commissions', [
            'openPeriod' => $openPeriod,
            'openDays' => $openPeriod ? max(1, $openPeriod->opened_at->diffInDays(now()) + 1) : 0,
            'requests' => PayoutRequest::query()->with('user')->pending()->latest('id')->get(),
            'closedPeriods' => CommissionPeriod::query()->closed()
                ->withCount('settlements')
                ->orderByDesc('closed_at')
                ->orderByDesc('id')
                ->get(),
            'settlements' => CommissionSettlement::query()
                ->with('user')
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->paginate(15),
            'totalRate' => CommissionSettlementService::totalActiveRate(),
            'today' => now()->format('Y-m-d'),
            'cashInHand' => BusinessStats::all()['cash_in_hand'],
        ]);
    }

    /**
     * Open a new commission period (only one open at a time). Optionally
     * records opening cash and an opening investment entry.
     */
    public function openPeriod(Request $request): RedirectResponse
    {
        abort_if(CommissionPeriod::query()->open()->exists(), 409);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'opened_at' => ['required', 'date'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'investment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        CommissionSettlementService::openPeriod(
            $validated['label'] ?? null,
            Carbon::parse($validated['opened_at']),
            isset($validated['opening_cash']) ? (float) $validated['opening_cash'] : null,
            isset($validated['investment_amount']) ? (float) $validated['investment_amount'] : null,
            $validated['note'] ?? null,
        );

        return back()->with('success', __('messages.period_opened'));
    }

    /**
     * Close the open period: profit/loss is computed and settlements are
     * created per partner (profit x rate). Idempotent per period.
     */
    public function closePeriod(Request $request): RedirectResponse
    {
        $period = CommissionPeriod::query()->open()->latest('id')->firstOrFail();

        $validated = $request->validate([
            'closed_at' => ['required', 'date', 'after_or_equal:'.$period->opened_at->format('Y-m-d')],
        ]);

        $result = CommissionSettlementService::closePeriod($period, Carbon::parse($validated['closed_at']));

        if ($result['profit'] <= 0) {
            return back()->with('warning', __('messages.period_closed_loss', [
                'profit' => number_format($result['profit'], 2),
            ]));
        }

        return back()->with('success', __('messages.period_closed_profit', [
            'profit' => number_format($result['profit'], 2),
            'count' => $result['created'],
        ]));
    }

    /**
     * Approve a payout request: mark ALL of that partner's pending
     * settlements paid and create the payout entry — atomically.
     */
    public function approveRequest(int $requestId): RedirectResponse
    {
        $payoutRequest = PayoutRequest::query()->with('user')->findOrFail($requestId);

        abort_unless($payoutRequest->status === 'pending', 409);

        DB::transaction(function () use ($payoutRequest): void {
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
