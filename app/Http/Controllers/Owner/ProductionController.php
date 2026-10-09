<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Production;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-only: record production runs (raw materials + extra cost ->
 * finished goods) and browse/delete them. Productions have no approval
 * flow — manufacturing is an internal act. Stock and COGS are computed
 * live from these runs by InventoryService.
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
                ->with(['outputs.saleItem', 'components.purchaseItem', 'user'])
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'saleItems' => SaleItem::query()->active()->orderBy('name')->get(),
            'purchaseItems' => PurchaseItem::query()->active()->orderBy('name')->get(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a production run with its component lines. Blocked while
     * no commission cycle is open (same rule as other entries).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(CommissionPeriod::query()->open()->exists(), 409);

        $validated = $request->validate([
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.sale_item_id' => ['required', 'distinct', 'exists:sale_items,id'],
            'outputs.*.quantity' => ['required', 'integer', 'min:1'],
            'components' => ['nullable', 'array', 'min:0'],
            'components.*.purchase_item_id' => ['required', 'distinct', 'exists:purchase_items,id'],
            'components.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $production = Production::query()->create([
                'user_id' => $request->user()->id,
                'extra_cost' => $validated['extra_cost'] ?? 0,
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
            ]);

            foreach ($validated['outputs'] as $output) {
                $production->outputs()->create([
                    'sale_item_id' => $output['sale_item_id'],
                    'quantity' => $output['quantity'],
                ]);
            }

            foreach ($validated['components'] ?? [] as $component) {
                $production->components()->create([
                    'purchase_item_id' => $component['purchase_item_id'],
                    'quantity' => $component['quantity'],
                ]);
            }
        });

        return back()->with('success', __('messages.production_saved'));
    }

    /**
     * Remove a production run (cascades its component lines). Stock and
     * COGS recomputed live afterwards.
     */
    public function destroy(Production $production): RedirectResponse
    {
        $production->delete();

        return back()->with('success', __('messages.production_deleted'));
    }
}
