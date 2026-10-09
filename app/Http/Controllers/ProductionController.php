<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\Production;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                ->with(['outputs.saleItem', 'components.purchaseItem'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate(15),
            'saleItems' => SaleItem::query()->active()->orderBy('name')->get(),
            'purchaseItems' => PurchaseItem::query()->active()->orderBy('name')->get(),
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
                'status' => EntryStatus::Pending,
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

        return back()->with('warning', __('messages.waiting_owner'));
    }
}
