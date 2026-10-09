<?php

namespace App\Http\Controllers\Owner;

use App\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\Production;
use App\Support\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'distinct', 'exists:items,id'],
            'outputs.*.quantity' => ['required', 'integer', 'min:1'],
            'components' => ['nullable', 'array', 'min:0'],
            'components.*.item_id' => ['required', 'distinct', 'exists:items,id'],
            'components.*.quantity' => ['required', 'integer', 'min:1'],
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
                'entry_date' => $validated['entry_date'],
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
        abort_unless($production->status === EntryStatus::Pending, 409);

        // Stock and prices may have moved since the partner submitted —
        // re-validate both checks before anything starts counting.
        $error = InventoryService::validateProduction(
            $production->components->map(fn ($c): array => ['item_id' => $c->item_id, 'quantity' => (int) $c->quantity])->all(),
            $production->outputs->map(fn ($o): array => ['item_id' => $o->item_id, 'quantity' => (int) $o->quantity])->all(),
            (float) $production->extra_cost,
            excludeProductionId: $production->id,
        );
        if ($error !== null) {
            return back()->with('error', $error);
        }

        $production->update([
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => request()->user()->id,
            'confirmed_at' => now(),
        ]);

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

        $production->delete();

        return back()->with('success', __('messages.production_deleted'));
    }
}
