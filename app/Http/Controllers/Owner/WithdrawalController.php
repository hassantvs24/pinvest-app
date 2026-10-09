<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerWithdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner-only: withdraw business profit (reduces cash in hand).
 */
class WithdrawalController extends Controller
{
    /**
     * List withdrawals with running total.
     */
    public function index(): View
    {
        return view('owner.withdrawals', [
            'withdrawals' => OwnerWithdrawal::query()->latest('withdrawn_at')->latest('id')->paginate(15),
            'total' => (float) OwnerWithdrawal::query()->sum('amount'),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Record a new profit withdrawal.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'withdrawn_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        OwnerWithdrawal::query()->create($validated);

        return back()->with('success', __('messages.withdrawal_added'));
    }
}
