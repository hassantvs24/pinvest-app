<?php

namespace App\Http\Controllers\Owner;

use App\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\StockLoss;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner-side: report stock losses directly (auto-confirmed) and
 * confirm/reject partner-reported losses. Confirmed losses reduce
 * stock at average cost and count as a loss in the cycle.
 */
class StockLossController extends Controller
{
    public function index(): View
    {
        return view('owner.stock-losses', [
            'losses' => StockLoss::query()
                ->with(['item', 'user'])
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'items' => Item::query()->active()->orderBy('name')->get(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        StockLoss::query()->create([
            'user_id' => $request->user()->id,
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
            'note' => $validated['note'] ?? null,
            'entry_date' => $validated['entry_date'],
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.stock_loss_saved'));
    }

    public function confirm(StockLoss $stockLoss): RedirectResponse
    {
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);
        abort_unless($stockLoss->status === EntryStatus::Pending, 409);

        $stockLoss->update([
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => request()->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.entry_confirmed'));
    }

    public function reject(StockLoss $stockLoss): RedirectResponse
    {
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);
        abort_unless($stockLoss->status === EntryStatus::Pending, 409);

        $stockLoss->update([
            'status' => EntryStatus::Rejected,
            'confirmed_by' => request()->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.entry_rejected'));
    }

    public function destroy(StockLoss $stockLoss): RedirectResponse
    {
        $stockLoss->delete();

        return back()->with('success', __('messages.stock_loss_deleted'));
    }
}
