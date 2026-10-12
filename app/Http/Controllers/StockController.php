<?php

namespace App\Http\Controllers;

use App\Support\InventoryService;
use App\Support\ItemUnits;
use Illuminate\View\View;

/**
 * Partner-side: current stock levels, quantity only. Partners see how
 * much of each item is on hand and how much remains sellable, without
 * the cost/value figures or the movement ledger (both stay owner-only).
 */
class StockController extends Controller
{
    public function index(): View
    {
        $reservations = InventoryService::pendingReservations();

        $rows = array_values(array_filter(
            InventoryService::stockRows(now(), true),
            fn (array $row): bool => $row['base_quantity'] > 0.00001,
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

        return view('stock.index', [
            'rows' => $rows,
        ]);
    }
}
