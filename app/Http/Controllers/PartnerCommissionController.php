<?php

namespace App\Http\Controllers;

use App\Models\CommissionSettlement;
use App\Models\PayoutRequest;
use App\Support\CommissionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner-side: view own commission settlements and request payout.
 */
class PartnerCommissionController extends Controller
{
    /**
     * List the partner's settlements with pending due and request state.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('commissions.index', [
            'settlements' => CommissionSettlement::query()
                ->with('period')
                ->where('user_id', $user->id)
                ->orderByDesc('period_start')
                ->paginate(15),
            'pendingDue' => CommissionSettlementService::pendingDue($user->id),
            'earnedTotal' => CommissionSettlementService::earned($user->id),
            'hasPendingRequest' => PayoutRequest::query()
                ->pending()
                ->where('user_id', $user->id)
                ->exists(),
        ]);
    }

    /**
     * Request payout of all pending commission. One pending request at a
     * time; the owner approves it from the commissions page.
     */
    public function requestPayout(Request $request): RedirectResponse
    {
        $user = $request->user();

        $due = CommissionSettlementService::pendingDue($user->id);

        if ($due <= 0) {
            return back()->with('warning', __('messages.no_pending_commission'));
        }

        $alreadyPending = PayoutRequest::query()
            ->pending()
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyPending) {
            return back()->with('warning', __('messages.request_pending_note'));
        }

        PayoutRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $due,
        ]);

        return back()->with('success', __('messages.payout_request_sent'));
    }
}
