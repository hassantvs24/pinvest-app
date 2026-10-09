<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner-only: record commission payouts to partners.
 */
class PayoutController extends Controller
{
    /**
     * List payouts with running total.
     */
    public function index(): View
    {
        return view('owner.payouts', [
            'payouts' => Payout::query()->with('user')->latest('payout_date')->latest('id')->paginate(15),
            'total' => (float) Payout::query()->sum('amount'),
            'partners' => User::query()->partners()->active()->orderBy('name')->get(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a new payout entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payout_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Payout::query()->create($validated);

        return back()->with('success', __('messages.payout_added'));
    }
}
