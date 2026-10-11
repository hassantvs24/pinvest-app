<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CashMovementType;
use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\PartnerCashMovement;
use App\Models\Production;
use App\Support\CommissionSettlementService;
use App\Support\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owner-only: record production runs (raw materials + extra cost ->
 * finished goods), confirm/reject partner runs, and browse/delete.
 * Stock and COGS are computed live from confirmed runs by
 * InventoryService.
 */
class ProductionController extends Controller
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
     * List productions (newest first) with the create form data.
     */
    public function index(): View
    {
        return view('owner.productions', [
            'productions' => Production::query()
                ->with(['outputs.item', 'components.item', 'user'])
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'items' => Item::query()->active()->orderBy('name')->get(),
            'available' => InventoryService::availableMap(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Whether a commission cycle is currently open (productions can
     * only be acted on while one is open).
     */
    private function cycleOpen(): bool
    {
        return CommissionPeriod::query()->open()->exists();
    }

    /**
     * Store a production run with its component lines. Owner entries
     * are auto-confirmed (the owner is the approver).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->cycleOpen(), 409);

        $validated = $request->validate([
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'entry_date' => $this->entryDateRules(),
            'note' => ['nullable', 'string', 'max:1000'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'distinct', 'exists:items,id'],
            'outputs.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'components' => ['nullable', 'array', 'min:0'],
            'components.*.item_id' => ['required', 'distinct', 'exists:items,id'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $componentIds = array_column($validated['components'] ?? [], 'item_id');
        $outputIds = array_column($validated['outputs'], 'item_id');

        if (array_intersect($componentIds, $outputIds) !== []) {
            throw ValidationException::withMessages([
                'components' => __('messages.production_circular_item'),
            ]);
        }

        $error = InventoryService::validateProduction(
            $validated['components'] ?? [],
            $validated['outputs'],
            (float) ($validated['extra_cost'] ?? 0),
        );
        if ($error !== null) {
            return back()->withInput()->with('error', $error);
        }

        DB::transaction(function () use ($request, $validated): void {
            $production = Production::query()->create([
                'user_id' => $request->user()->id,
                'extra_cost' => $validated['extra_cost'] ?? 0,
                'note' => $validated['note'] ?? null,
                'entry_date' => $this->entryMoment($validated['entry_date']),
                'status' => EntryStatus::Confirmed,
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            foreach ($validated['outputs'] as $output) {
                $production->outputs()->create([
                    'item_id' => $output['item_id'],
                    'quantity' => $output['quantity'],
                ]);
            }

            foreach ($validated['components'] ?? [] as $component) {
                $production->components()->create([
                    'item_id' => $component['item_id'],
                    'quantity' => $component['quantity'],
                ]);
            }
        });

        return back()->with('success', __('messages.production_saved'));
    }

    /**
     * Confirm a partner's pending production (stock/COGS start counting).
     */
    public function confirm(Production $production): RedirectResponse
    {
        abort_unless($this->cycleOpen(), 409);

        // Stock and prices may have moved since the partner submitted —
        // re-validate both checks under a row lock so two simultaneous
        // confirms cannot oversell the same units.
        $error = null;

        DB::transaction(function () use ($production, &$error): void {
            $production = Production::query()->lockForUpdate()->findOrFail($production->id);

            abort_unless($production->status === EntryStatus::Pending, 409);

            $error = InventoryService::validateProduction(
                $production->components->map(fn ($c): array => ['item_id' => $c->item_id, 'quantity' => (float) $c->quantity])->all(),
                $production->outputs->map(fn ($o): array => ['item_id' => $o->item_id, 'quantity' => (float) $o->quantity])->all(),
                (float) $production->extra_cost,
                excludeProductionId: $production->id,
            );

            if ($error !== null) {
                return;
            }

            $production->update([
                'status' => EntryStatus::Confirmed,
                'confirmed_by' => request()->user()->id,
                'confirmed_at' => now(),
            ]);

            // Labour is paid in cash from whatever the partner holds:
            // auto-deduct from their outstanding (may go negative — the
            // owner tops up later). The owner's own runs have no partner
            // custody, so they are skipped.
            if ((float) $production->extra_cost > 0 && ! $production->user->isOwner()) {
                PartnerCashMovement::query()->create([
                    'user_id' => $production->user_id,
                    'type' => CashMovementType::Spent,
                    'amount' => (float) $production->extra_cost,
                    'note' => __('messages.auto_spent_production', ['id' => $production->id]),
                    'occurred_at' => $production->entry_date,
                ]);
            }
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', __('messages.entry_confirmed'));
    }

    /**
     * Reject a partner's pending production (kept visible, never counts).
     */
    public function reject(Production $production): RedirectResponse
    {
        abort_unless($this->cycleOpen(), 409);
        abort_unless($production->status === EntryStatus::Pending, 409);

        $production->update([
            'status' => EntryStatus::Rejected,
            'confirmed_by' => request()->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.entry_rejected'));
    }

    /**
     * Remove a production run (cascades its component and output lines).
     * Stock and COGS recomputed live afterwards. Only while a cycle is
     * open, so closed cycles keep matching their stored profit.
     */
    public function destroy(Production $production): RedirectResponse
    {
        abort_unless($this->cycleOpen(), 409);

        // Refund the partner's held cash before deleting a confirmed run
        // — the labour auto-spent movement stays in the immutable ledger,
        // so the custody side must be given back to keep both in sync.
        if ($production->status === EntryStatus::Confirmed
            && ! $production->user->isOwner()
            && (float) $production->extra_cost > 0) {
            PartnerCashMovement::query()->create([
                'user_id' => $production->user_id,
                'type' => CashMovementType::Hold,
                'amount' => (float) $production->extra_cost,
                'note' => __('messages.custody_delete_refund', ['id' => $production->id]),
                'occurred_at' => $production->entry_date,
            ]);
        }

        $production->delete();

        return back()->with('success', __('messages.production_deleted'));
    }
}
