<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner-only: record money invested into the business.
 */
class InvestmentController extends Controller
{
    /**
     * List investments with running total.
     */
    public function index(): View
    {
        return view('owner.investments', [
            'investments' => Investment::query()->latest('invested_at')->latest('id')->paginate(15),
            'total' => (float) Investment::query()->sum('amount'),
        ]);
    }

    /**
     * Store a new investment entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invested_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Investment::query()->create($validated);

        return back()->with('success', __('messages.investment_added'));
    }
}
