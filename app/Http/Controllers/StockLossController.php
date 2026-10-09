<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\StockLoss;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner-side: report stock that vanished without a sale (theft, rot,
 * damage, shrinkage). Entries stay pending until the owner confirms;
 * confirmed losses reduce stock and count as a loss in the cycle.
 */
class StockLossController extends Controller
{
    public function index(Request $request): View
    {
        return view('stock-losses.index', [
            'losses' => StockLoss::query()
                ->with('item')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'items' => Item::query()->active()->orderBy('name')->get(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! CommissionPeriod::query()->open()->exists()) {
            return back()->with('warning', __('messages.entry_blocked_no_period'));
        }

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        StockLoss::query()->create([
            'user_id' => $request->user()->id,
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
            'note' => $validated['note'] ?? null,
            'entry_date' => now()->format('Y-m-d'), // partners always report today
            'status' => EntryStatus::Pending,
        ]);

        return back()->with('warning', __('messages.waiting_owner'));
    }
}
