<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\StockLoss;
use App\Support\CommissionSettlementService;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-side: report stock losses directly (auto-confirmed) and
 * confirm/reject partner-reported losses. Confirmed losses reduce
 * stock at average cost and count as a loss in the cycle.
 */
class StockLossController extends Controller
{
    /**
     * Owner date-picker rules: today or a past day, never on/inside a
     * closed cycle (backdated entries land at midnight of the picked
     * day). The picked day resolves to the exact current moment when it
     * is today, midnight of that day otherwise.
     *
     * @return array<int, string>
     */
    private function entryDateRules(): array
    {
        $rules = ['required', 'date', 'before_or_equal:'.now()->toDateString()];

        $latestClosed = CommissionSettlementService::latestClosedDate();

        if ($latestClosed !== null) {
            $rules[] = 'after:'.$latestClosed->toDateString();
        } elseif (($openPeriod = CommissionPeriod::query()->open()->latest('id')->first()) !== null) {
            $rules[] = 'after_or_equal:'.$openPeriod->opened_at->toDateString();
        }

        return $rules;
    }

    private function entryMoment(string $date): Carbon
    {
        $picked = Carbon::parse($date);

        return $picked->toDateString() === now()->toDateString() ? now() : $picked->startOfDay();
    }

    /**
     * A loss made on/before the latest cycle close belongs to that
     * closed cycle — its write-off is baked into the stored profit and
     * settlements, so it must stay untouched.
     */
    private function closedCycleEntryError(StockLoss $stockLoss): ?string
    {
        $latestClosed = CommissionSettlementService::latestClosedDate();

        if ($latestClosed !== null && $stockLoss->entry_date->lessThanOrEqualTo($latestClosed)) {
            return __('messages.entry_locked_closed_cycle');
        }

        return null;
    }

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
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'entry_date' => $this->entryDateRules(),
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $error = $this->stockOverflowError((int) $validated['item_id'], (float) $validated['quantity']);
        if ($error !== null) {
            return back()->withInput()->withErrors(['quantity' => $error]);
        }

        StockLoss::query()->create([
            'user_id' => $request->user()->id,
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
            'note' => $validated['note'] ?? null,
            'entry_date' => $this->entryMoment($validated['entry_date']),
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.stock_loss_saved'));
    }

    /**
     * Stock-overdraft error message when a loss no longer fits, or null
     * when it does. Pending losses (like pending sales) reserve stock.
     */
    private function stockOverflowError(int $itemId, float $quantity, ?int $excludeLossId = null): ?string
    {
        $item = Item::query()->findOrFail($itemId);
        $available = InventoryService::availableQuantity($item, excludeLossId: $excludeLossId);

        if (ItemUnits::toBase((float) $quantity, $item->unit) <= $available + 1e-9) {
            return null;
        }

        return __('messages.loss_exceeds_stock', [
            'available' => rtrim(rtrim(number_format(ItemUnits::fromBase(max(0.0, $available), $item->unit), 2), '0'), '.'),
            'unit' => ItemUnits::label($item->unit),
        ]);
    }

    public function confirm(StockLoss $stockLoss): RedirectResponse
    {
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);

        // Re-check the stock under a row lock so two simultaneous
        // confirms cannot write off the same units twice.
        $error = null;

        DB::transaction(function () use ($stockLoss, &$error): void {
            $stockLoss = StockLoss::query()->lockForUpdate()->findOrFail($stockLoss->id);

            abort_unless($stockLoss->status === EntryStatus::Pending, 409);

            $error = $this->stockOverflowError((int) $stockLoss->item_id, (float) $stockLoss->quantity, (int) $stockLoss->id);

            if ($error !== null) {
                return;
            }

            $stockLoss->update([
                'status' => EntryStatus::Confirmed,
                'confirmed_by' => request()->user()->id,
                'confirmed_at' => now(),
            ]);
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

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
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);

        $locked = $this->closedCycleEntryError($stockLoss);

        if ($locked !== null) {
            return back()->with('error', $locked);
        }

        $stockLoss->delete();

        return back()->with('success', __('messages.stock_loss_deleted'));
    }
}
