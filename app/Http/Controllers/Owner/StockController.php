<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use Illuminate\View\View;

/**
 * Owner-only: live stock levels for every item and a per-item movement
 * ledger (purchases, production, sales, losses with running balance).
 * Everything is computed from confirmed entries — nothing is stored.
 */
class StockController extends Controller
{
    /**
     * All items with current stock, plus what pending sales reserve.
     */
    public function index(): View
    {
        $reservations = InventoryService::pendingReservations();

        $rows = array_values(array_filter(
            InventoryService::stockRows(now(), true),
            fn (array $row): bool => $row['base_quantity'] > 0.00001 || $row['item']->is_active,
        ));

        foreach ($rows as &$row) {
            $reserved = $reservations[$row['item']->id] ?? 0.0;
            $row['reserved'] = ItemUnits::fromBase($reserved, $row['item']->unit);
            $row['available'] = ItemUnits::fromBase(
                max(0.0, $row['base_quantity'] - $reserved),
                $row['item']->unit,
            );
        }
        unset($row);

        return view('owner.stock.index', [
            'rows' => $rows,
        ]);
    }

    /**
     * One item's movement ledger with running balance.
     */
    public function show(Item $item): View
    {
        $ledger = InventoryService::ledger($item, now());

        $state = InventoryService::stockRows(now(), true);
        $summary = collect($state)->firstWhere('item', $item) ?? [
            'quantity' => 0.0,
            'base_quantity' => 0.0,
            'avg_cost' => 0.0,
            'value' => 0.0,
        ];

        return view('owner.stock.show', [
            'item' => $item,
            'rows' => $ledger['rows'],
            'pending' => $ledger['pending'],
            'summary' => $summary,
        ]);
    }
}
