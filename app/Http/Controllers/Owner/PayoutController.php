<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use Illuminate\View\View;

/**
 * Owner-only: payout HISTORY. Payouts are created automatically when a
 * partner's payout request is approved (see CommissionController) — they
 * are never entered manually, so commission due can never double-count.
 */
class PayoutController extends Controller
{
    /**
     * List all payouts with running total.
     */
    public function index(): View
    {
        return view('owner.payouts', [
            'payouts' => Payout::query()->with('user')->latest('payout_date')->latest('id')->paginate(15),
            'total' => (float) Payout::query()->sum('amount'),
        ]);
    }
}
