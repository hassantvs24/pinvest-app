<?php

namespace App\Support;

use App\Enums\ExpenseCostType;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perpetual inventory with moving weighted average cost.
 *
 * Confirmed purchases add stock (quantity and cost); confirmed product
 * expenses (transport, labour, processing) add cost to the stock they
 * belong to; confirmed sales consume stock at the average cost that is
 * current on the sale date (COGS). Unsold stock carries its value into
 * the next commission cycle automatically, so cycle profit is never
 * distorted by when goods were bought versus sold.
 *
 * Quantities are converted through ItemUnits so kg / gram / tola
 * purchases and sales can be mixed; count (pcs) and volume (ml) units
 * only ever match their own category.
 *
 * Everything is computed live from confirmed entries — nothing about
 * stock is stored, so owner edits/deletes always stay consistent.
 *
 * @phpstan-type ItemState array{item: PurchaseItem, in_qty: float, in_cost: float, purchase_cost: float, out_qty: float, cogs: float}
 * @phpstan-type Simulation array{states: array<int, ItemState>, cogs: float, warnings: list<string>}
 */
class InventoryService
{
    /**
     * COGS (cost of goods sold) for confirmed sales between two dates
     * (by entry date, inclusive), at each sale date's weighted average
     * cost. Sales of unlinked sale items count as 0 cost.
     */
    public static function cogs(Carbon $from, Carbon $to): float
    {
        return self::simulate($from, $to)['cogs'];
    }

    /**
     * Total value of stock still on hand at the given date
     * (remaining base quantity x moving average cost).
     */
    public static function stockValue(Carbon $asOf): float
    {
        return array_sum(array_map(
            fn (array $state): float => self::stateValue($state),
            self::simulate(null, $asOf)['states'],
        ));
    }

    /**
     * Per-item stock rows for display: current quantity (in the item's
     * own unit), remaining base quantity, average cost and value.
     *
     * @return array<int, array{item: PurchaseItem, quantity: float, base_quantity: float, avg_cost: float, value: float}>
     */
    public static function stockRows(Carbon $asOf): array
    {
        return array_values(array_filter(
            array_map(function (array $state): array {
                $remaining = max(0.0, $state['in_qty'] - $state['out_qty']);

                return [
                    'item' => $state['item'],
                    'quantity' => ItemUnits::fromBase($remaining, $state['item']->unit),
                    'base_quantity' => $remaining,
                    'avg_cost' => self::stateAvgCost($state),
                    'value' => self::stateValue($state),
                ];
            }, self::simulate(null, $asOf)['states']),
            fn (array $row): bool => $row['base_quantity'] > 0.00001 || $row['value'] > 0.00001,
        ));
    }

    /**
     * Human-readable warnings about data that makes COGS inaccurate:
     * unlinked sale items, negative stock and product expenses that
     * could not be allocated to any item.
     *
     * @return list<string>
     */
    public static function warnings(Carbon $asOf): array
    {
        return self::simulate(null, $asOf)['warnings'];
    }

    /**
     * Chronological simulation of every confirmed purchase, product
     * expense and sale up to $to. When $cogsFrom is given, only sales
     * on/after that date accumulate COGS (earlier sales still reduce
     * stock so the remaining quantity stays correct).
     *
     * @return Simulation
     */
    private static function simulate(?Carbon $cogsFrom, Carbon $to): array
    {
        /** @var array<int, ItemState> $states */
        $states = PurchaseItem::query()->get()
            ->mapWithKeys(fn (PurchaseItem $item): array => [$item->id => [
                'item' => $item,
                'in_qty' => 0.0,
                'in_cost' => 0.0,
                'purchase_cost' => 0.0,
                'out_qty' => 0.0,
                'cogs' => 0.0,
            ]])
            ->all();

        // Unified event stream, sorted by date; inputs (purchases,
        // product expenses) process before sales on the same date so
        // same-day purchases already count towards the average cost.
        $events = collect()
            ->merge(self::purchaseEvents($to))
            ->merge(self::productExpenseEvents($to))
            ->merge(self::saleEvents($to, $cogsFrom))
            ->sortBy(fn (array $event): string => $event['date'].'|'.$event['order'].'|'.$event['id'])
            ->values();

        $cogs = 0.0;
        $unallocatedExpense = 0.0;
        $negativeNames = [];

        foreach ($events as $event) {
            if ($event['kind'] === 'purchase') {
                $state = $states[$event['item_id']];
                $state['in_qty'] += $event['qty_base'];
                $state['in_cost'] += $event['cost'];
                $state['purchase_cost'] += $event['cost'];
                $states[$event['item_id']] = $state;

                continue;
            }

            if ($event['kind'] === 'product-expense') {
                $states = self::applyProductExpense($states, $event, $unallocatedExpense);

                continue;
            }

            // Sale: consume stock at the average cost current right now.
            $state = $states[$event['item_id']];
            $saleCogs = round($event['qty_base'] * self::stateAvgCost($state), 2);

            if ($event['count_cogs']) {
                $cogs += $saleCogs;
                $state['cogs'] += $saleCogs;
            }

            $state['out_qty'] += $event['qty_base'];
            $states[$event['item_id']] = $state;

            if ($state['out_qty'] > $state['in_qty']) {
                $negativeNames[$event['item_id']] = $state['item']->name;
            }
        }

        $warnings = [];
        $unlinkedNames = self::unlinkedSaleNames($to);

        if ($unlinkedNames !== []) {
            $warnings[] = __('messages.warn_unlinked_sale_items', ['items' => implode(', ', array_unique($unlinkedNames))]);
        }

        if ($negativeNames !== []) {
            $warnings[] = __('messages.warn_negative_stock', ['items' => implode(', ', array_values($negativeNames))]);
        }

        if ($unallocatedExpense > 0.0) {
            $warnings[] = __('messages.warn_unallocated_product_expense', ['amount' => number_format($unallocatedExpense, 2)]);
        }

        return ['states' => $states, 'cogs' => round($cogs, 2), 'warnings' => $warnings];
    }

    /**
     * Confirmed purchases up to $to as input events.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, item_id: int, qty_base: float, cost: float}>
     */
    private static function purchaseEvents(Carbon $to): Collection
    {
        return Purchase::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->with('purchaseItem')
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'kind' => 'purchase',
                'id' => 'p'.$purchase->id,
                'date' => $purchase->entry_date->format('Y-m-d'),
                'order' => 0,
                'item_id' => $purchase->purchase_item_id,
                'qty_base' => ItemUnits::toBase((float) $purchase->quantity, $purchase->purchaseItem->unit),
                'cost' => (float) $purchase->total,
            ]);
    }

    /**
     * Confirmed product-type expenses up to $to as cost events. Only
     * heads whose cost type adds to stock produce events.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, amount: float, item_id: int|null}>
     */
    private static function productExpenseEvents(Carbon $to): Collection
    {
        return Expense::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->whereHas('expenseHead', fn ($query) => $query->where('cost_type', ExpenseCostType::Product->value))
            ->get()
            ->map(fn (Expense $expense): array => [
                'kind' => 'product-expense',
                'id' => 'e'.$expense->id,
                'date' => $expense->entry_date->format('Y-m-d'),
                'order' => 1,
                'amount' => (float) $expense->amount,
                'item_id' => $expense->purchase_item_id,
            ]);
    }

    /**
     * Confirmed sales of linked sale items up to $to as consumption
     * events. Sales before $cogsFrom reduce stock only.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, item_id: int, qty_base: float, count_cogs: bool}>
     */
    private static function saleEvents(Carbon $to, ?Carbon $cogsFrom): Collection
    {
        return Sale::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->whereHas('saleItem', fn ($query) => $query->whereNotNull('purchase_item_id'))
            ->with('saleItem.purchaseItem')
            ->get()
            ->map(fn (Sale $sale): array => [
                'kind' => 'sale',
                'id' => 's'.$sale->id,
                'date' => $sale->entry_date->format('Y-m-d'),
                'order' => 2,
                'item_id' => $sale->saleItem->purchase_item_id,
                'qty_base' => ItemUnits::toBase((float) $sale->quantity, $sale->saleItem->unit),
                'count_cogs' => $cogsFrom === null || $sale->entry_date->greaterThanOrEqualTo($cogsFrom),
            ]);
    }

    /**
     * Names of confirmed sales whose sale item is not linked to any
     * purchase item (their cost counts as 0).
     *
     * @return array<int, string>
     */
    private static function unlinkedSaleNames(Carbon $to): array
    {
        return Sale::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->whereHas('saleItem', fn ($query) => $query->whereNull('purchase_item_id'))
            ->with('saleItem')
            ->get()
            ->map(fn (Sale $sale): string => $sale->saleItem->name)
            ->all();
    }

    /**
     * Add a product expense to the stock cost pool: fully to its linked
     * item, otherwise spread across items proportionally to their
     * purchase cost so far. Expenses with nothing to attach to are
     * reported as unallocated.
     *
     * @param  array<int, ItemState>  $states
     * @return array<int, ItemState>
     */
    private static function applyProductExpense(array $states, array $event, float &$unallocatedExpense): array
    {
        if ($event['item_id'] !== null && isset($states[$event['item_id']])) {
            $state = $states[$event['item_id']];
            $state['in_cost'] += $event['amount'];
            $states[$event['item_id']] = $state;

            return $states;
        }

        $totalPurchaseCost = array_sum(array_map(
            fn (array $state): float => $state['purchase_cost'],
            $states,
        ));

        if ($totalPurchaseCost <= 0.0) {
            $unallocatedExpense += $event['amount'];

            return $states;
        }

        foreach ($states as $id => $state) {
            if ($state['purchase_cost'] <= 0.0) {
                continue;
            }

            $state['in_cost'] += $event['amount'] * ($state['purchase_cost'] / $totalPurchaseCost);
            $states[$id] = $state;
        }

        return $states;
    }

    /**
     * Moving weighted average cost per base unit.
     *
     * @param  ItemState  $state
     */
    private static function stateAvgCost(array $state): float
    {
        return $state['in_qty'] > 0.0 ? $state['in_cost'] / $state['in_qty'] : 0.0;
    }

    /**
     * Value of the stock still remaining in this state.
     *
     * @param  ItemState  $state
     */
    private static function stateValue(array $state): float
    {
        $remaining = $state['in_qty'] - $state['out_qty'];

        return $remaining > 0.0 ? round($remaining * self::stateAvgCost($state), 2) : 0.0;
    }
}
