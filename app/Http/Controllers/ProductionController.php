<?php

namespace App\Http\Controllers;

use App\EntryStatus;
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
 * Partner-side: record production runs (raw materials + labour ->
 * finished goods). Partner entries are "pending" until the owner
 * confirms them from the owner productions page; nothing is costed
 * while pending.
 */
class ProductionController extends Controller
{
    /**
     * Show the production form and the partner's own runs.
     */
    public function index(Request $request): View
    {
        return view('productions.index', [
            'productions' => Production::query()
                ->with(['outputs.item', 'components.item'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'items' => Item::query()->active()->orderBy('name')->get(),
            'available' => InventoryService::availableMap(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a production run as pending (the owner confirms it). Blocked
     * while no commission cycle is open.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! CommissionPeriod::query()->open()->exists()) {
            return back()->with('warning', __('messages.entry_blocked_no_period'));
        }

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

        $this->guardCircularItems($validated);

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
                'entry_date' => now()->format('Y-m-d'), // partners always report today
                'status' => EntryStatus::Pending,
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

        return back()->with('warning', __('messages.waiting_owner'));
    }

    /**
     * One item cannot be both a component and an output of the same
     * run — that would be circular (making an item from itself).
     *
     * @param  array{outputs: array<int, array{item_id: int}>, components?: array<int, array{item_id: int}>}  $validated
     */
    private function guardCircularItems(array $validated): void
    {
        $componentIds = array_column($validated['components'] ?? [], 'item_id');
        $outputIds = array_column($validated['outputs'], 'item_id');

        if (array_intersect($componentIds, $outputIds) !== []) {
            throw ValidationException::withMessages([
                'components' => __('messages.production_circular_item'),
            ]);
        }
    }
}
