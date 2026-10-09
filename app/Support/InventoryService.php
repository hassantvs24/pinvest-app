<?php

namespace App\Support;

use App\Enums\ExpenseCostType;
use App\Models\Expense;
use App\Models\Production;
use App\Models\ProductionOutput;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perpetual inventory with moving weighted average cost.
 *
 * Two kinds of stock are tracked from confirmed events only:
 *
 *  - Materials (purchase items): purchases add quantity/cost, product
 *    expenses (transport, labour) add cost, production runs and direct
 *    resales consume them.
 *  - Finished goods (sale items): production runs add them at their
 *    production cost (components at average cost + extra labour cost),
 *    sales consume them.
 *
 * A sale is costed against the finished-good stock when the sale item
 * has production runs, otherwise against its linked purchase item
 * (simple resale like gold/silver), otherwise it costs 0 with a
 * warning. Unsold stock of both kinds carries its value forward, so
 * commission cycles never distort profit by when goods move.
 *
 * Quantities are converted through ItemUnits (kg / gram / tola share a
 * gram base; count and volume units only ever match their own kind).
 * Everything is computed live — nothing is stored, so owner edits and
 * deletes always stay consistent.
 *
 * @phpstan-type MaterialState array{item: PurchaseItem, in_qty: float, in_cost: float, purchase_cost: float, out_qty: float}
 * @phpstan-type FinishedState array{item: SaleItem, in_qty: float, in_cost: float, out_qty: float}
 * @phpstan-type Simulation array{materials: array<int, MaterialState>, finished: array<int, FinishedState>, cogs: float, warnings: list<string>}
 */
class InventoryService
{
    /**
     * COGS (cost of goods sold) for confirmed sales between two dates
     * (by entry date, inclusive), at each sale date's weighted average
     * cost. Sales that cannot be costed count as 0.
     */
    public static function cogs(Carbon $from, Carbon $to): float
    {
        return self::simulate($from, $to)['cogs'];
    }

    /**
     * Total value of all stock on hand at the given date (materials and
     * finished goods, at their moving average cost).
     */
    public static function stockValue(Carbon $asOf): float
    {
        $simulation = self::simulate(null, $asOf);

        return array_sum(array_map(
            fn (array $state): float => self::stateValue($state),
            $simulation['materials'],
        )) + array_sum(array_map(
            fn (array $state): float => self::stateValue($state),
            $simulation['finished'],
        ));
    }

    /**
     * Per-item stock rows for display, split into raw materials and
     * finished goods. Quantities are shown in each item's own unit.
     *
     * @return array{materials: array<int, array{item: PurchaseItem, quantity: float, base_quantity: float, avg_cost: float, value: float}>, finished: array<int, array{item: SaleItem, quantity: float, base_quantity: float, avg_cost: float, value: float}>}
     */
    public static function stockRows(Carbon $asOf): array
    {
        $simulation = self::simulate(null, $asOf);

        return [
            'materials' => self::rowsFromStates($simulation['materials']),
            'finished' => self::rowsFromStates($simulation['finished']),
        ];
    }

    /**
     * Human-readable warnings about data that makes COGS inaccurate:
     * unlinked sale items, negative material/finished stock and product
     * expenses that could not be allocated to any item.
     *
     * @return list<string>
     */
    public static function warnings(Carbon $asOf): array
    {
        return self::simulate(null, $asOf)['warnings'];
    }

    /**
     * Chronological simulation of every confirmed purchase, product
     * expense, production and sale up to $to. When $cogsFrom is given,
     * only sales on/after that date accumulate COGS (earlier sales and
     * productions still shift stock so averages stay correct).
     *
     * @return Simulation
     */
    private static function simulate(?Carbon $cogsFrom, Carbon $to): array
    {
        /** @var array<int, MaterialState> $materials */
        $materials = PurchaseItem::query()->get()
            ->mapWithKeys(fn (PurchaseItem $item): array => [$item->id => [
                'item' => $item,
                'in_qty' => 0.0,
                'in_cost' => 0.0,
                'purchase_cost' => 0.0,
                'out_qty' => 0.0,
            ]])
            ->all();

        /** @var array<int, FinishedState> $finished */
        $finished = SaleItem::query()->get()
            ->mapWithKeys(fn (SaleItem $item): array => [$item->id => [
                'item' => $item,
                'in_qty' => 0.0,
                'in_cost' => 0.0,
                'out_qty' => 0.0,
            ]])
            ->all();

        // Sale items produced by at least one production run are costed
        // from finished-good stock; the rest fall back to their link.
        /** @var array<int, true> $producedSaleItemIds */
        $producedSaleItemIds = ProductionOutput::query()
            ->whereHas('production', fn ($query) => $query->whereDate('entry_date', '<=', $to))
            ->pluck('sale_item_id')
            ->flip()
            ->all();

        // Unified event stream, sorted by date; inputs (purchases,
        // product expenses, productions) process before sales on the
        // same date so same-day input already counts towards averages.
        $events = collect()
            ->merge(self::purchaseEvents($to))
            ->merge(self::productExpenseEvents($to))
            ->merge(self::productionEvents($to))
            ->merge(self::saleEvents($to, $cogsFrom, $producedSaleItemIds))
            ->sortBy(fn (array $event): string => $event['date'].'|'.$event['order'].'|'.$event['id'])
            ->values();

        $cogs = 0.0;
        $unallocatedExpense = 0.0;
        $negativeMaterials = [];
        $negativeFinished = [];

        foreach ($events as $event) {
            if ($event['kind'] === 'purchase') {
                $state = $materials[$event['item_id']];
                $state['in_qty'] += $event['qty_base'];
                $state['in_cost'] += $event['cost'];
                $state['purchase_cost'] += $event['cost'];
                $materials[$event['item_id']] = $state;

                continue;
            }

            if ($event['kind'] === 'product-expense') {
                $materials = self::applyProductExpense($materials, $event, $unallocatedExpense);

                continue;
            }

            if ($event['kind'] === 'production') {
                [$materials, $finished] = self::applyProduction($materials, $finished, $event, $negativeMaterials);

                continue;
            }

            // Sale: consume stock at the average cost current right now.
            $id = $event['pool'] === 'finished' ? $event['finished_id'] : $event['item_id'];
            $state = ($event['pool'] === 'finished' ? $finished : $materials)[$id];
            $saleCogs = round($event['qty_base'] * self::stateAvgCost($state), 2);

            if ($event['count_cogs']) {
                $cogs += $saleCogs;
            }

            $state['out_qty'] += $event['qty_base'];

            if ($event['pool'] === 'finished') {
                $finished[$id] = $state;

                if ($state['out_qty'] > $state['in_qty']) {
                    $negativeFinished[$id] = $state['item']->name;
                }
            } else {
                $materials[$id] = $state;

                if ($state['out_qty'] > $state['in_qty']) {
                    $negativeMaterials[$id] = $state['item']->name;
                }
            }
        }

        $warnings = [];
        $unlinkedNames = self::unlinkedSaleNames($to, $producedSaleItemIds);

        if ($unlinkedNames !== []) {
            $warnings[] = __('messages.warn_unlinked_sale_items', ['items' => implode(', ', array_unique($unlinkedNames))]);
        }

        if ($negativeMaterials !== []) {
            $warnings[] = __('messages.warn_negative_stock', ['items' => implode(', ', array_values($negativeMaterials))]);
        }

        if ($negativeFinished !== []) {
            $warnings[] = __('messages.warn_negative_finished_stock', ['items' => implode(', ', array_values($negativeFinished))]);
        }

        if ($unallocatedExpense > 0.0) {
            $warnings[] = __('messages.warn_unallocated_product_expense', ['amount' => number_format($unallocatedExpense, 2)]);
        }

        return [
            'materials' => $materials,
            'finished' => $finished,
            'cogs' => round($cogs, 2),
            'warnings' => $warnings,
        ];
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
     * Productions up to $to as conversion events. Component costs and
     * extra cost are pooled and split across the outputs in proportion
     * to each output's sale value (quantity x default price), falling
     * back to an even split when no output has a price — so a run like
     * "melt one ornament" can yield several finished goods, each with a
     * fair share of the cost.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, finished_id: int, qty_base: float, cost: float}>
     */
    private static function productionEvents(Carbon $to): Collection
    {
        return Production::query()
            ->whereDate('entry_date', '<=', $to)
            ->with(['outputs.saleItem', 'components.purchaseItem'])
            ->get()
            ->map(function (Production $production): array {
                $poolCost = (float) $production->extra_cost;

                $components = $production->components
                    ->map(fn ($component): array => [
                        'item_id' => $component->purchase_item_id,
                        'qty_base' => ItemUnits::toBase((float) $component->quantity, $component->purchaseItem->unit),
                    ])
                    ->all();

                // Output lines with their cost share resolved by the
                // caller simulation (needs live material averages).
                $outputs = $production->outputs
                    ->map(fn ($output): array => [
                        'finished_id' => $output->sale_item_id,
                        'qty_base' => ItemUnits::toBase((float) $output->quantity, $output->saleItem->unit),
                        'value' => (float) $output->quantity * (float) $output->saleItem->default_price,
                    ])
                    ->all();

                return [
                    'kind' => 'production',
                    'id' => 'pr'.$production->id,
                    'date' => $production->entry_date->format('Y-m-d'),
                    'order' => 2,
                    'extra_cost' => (float) $production->extra_cost,
                    'components' => $components,
                    'outputs' => $outputs,
                ];
            });
    }

    /**
     * Confirmed sales up to $to as consumption events, routed to
     * finished-good stock when the item is manufactured, to the linked
     * material otherwise. Sales before $cogsFrom reduce stock only.
     *
     * @param  array<int, true>  $producedSaleItemIds
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, pool: string, item_id: int, finished_id: int, qty_base: float, count_cogs: bool}>
     */
    private static function saleEvents(Carbon $to, ?Carbon $cogsFrom, array $producedSaleItemIds): Collection
    {
        return Sale::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->where(function ($query) use ($producedSaleItemIds): void {
                $query->whereHas('saleItem', fn ($q) => $q->whereNotNull('purchase_item_id'))
                    ->orWhereHas('saleItem', fn ($q) => $q->whereIn('id', array_keys($producedSaleItemIds)));
            })
            ->with('saleItem')
            ->get()
            ->map(function (Sale $sale) use ($cogsFrom, $producedSaleItemIds): array {
                $saleItem = $sale->saleItem;
                $isProduced = isset($producedSaleItemIds[$saleItem->id]);

                return [
                    'kind' => 'sale',
                    'id' => 's'.$sale->id,
                    'date' => $sale->entry_date->format('Y-m-d'),
                    'order' => 3,
                    'pool' => $isProduced ? 'finished' : 'material',
                    'item_id' => $isProduced ? 0 : $saleItem->purchase_item_id,
                    'finished_id' => $isProduced ? $saleItem->id : 0,
                    'qty_base' => ItemUnits::toBase((float) $sale->quantity, $saleItem->unit),
                    'count_cogs' => $cogsFrom === null || $sale->entry_date->greaterThanOrEqualTo($cogsFrom),
                ];
            });
    }

    /**
     * Names of confirmed sales whose item can be costed neither through
     * production nor through a purchase link (their cost counts as 0).
     *
     * @param  array<int, true>  $producedSaleItemIds
     * @return array<int, string>
     */
    private static function unlinkedSaleNames(Carbon $to, array $producedSaleItemIds): array
    {
        return Sale::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->with('saleItem')
            ->get()
            ->filter(fn (Sale $sale): bool => ! isset($producedSaleItemIds[$sale->saleItem->id])
                && $sale->saleItem->purchase_item_id === null)
            ->map(fn (Sale $sale): string => $sale->saleItem->name)
            ->all();
    }

    /**
     * Apply one production: consume each component at the material
     * average cost current now, then split the pooled cost across the
     * outputs (proportional to sale value, even split as fallback) and
     * add each share to the finished good's cost pool.
     *
     * @param  array<int, MaterialState>  $materials
     * @param  array<int, FinishedState>  $finished
     * @param  array<int, string>  $negativeMaterials
     * @return array{0: array<int, MaterialState>, 1: array<int, FinishedState>}
     */
    private static function applyProduction(array $materials, array $finished, array $event, array &$negativeMaterials): array
    {
        $poolCost = $event['extra_cost'];

        foreach ($event['components'] as $component) {
            if (! isset($materials[$component['item_id']])) {
                continue;
            }

            $state = $materials[$component['item_id']];
            $poolCost += $component['qty_base'] * self::stateAvgCost($state);

            $state['out_qty'] += $component['qty_base'];
            $materials[$component['item_id']] = $state;

            if ($state['out_qty'] > $state['in_qty']) {
                $negativeMaterials[$component['item_id']] = $state['item']->name;
            }
        }

        $totalValue = array_sum(array_map(fn (array $output): float => $output['value'], $event['outputs']));
        $outputs = array_values(array_filter(
            $event['outputs'],
            fn (array $output): bool => isset($finished[$output['finished_id']]),
        ));
        $count = count($outputs);
        $allocated = 0.0;

        foreach ($outputs as $i => $output) {
            $share = $totalValue > 0.0
                ? round($poolCost * ($output['value'] / $totalValue), 2)
                : round($poolCost / max(1, $count), 2);

            // The last output absorbs the rounding remainder so the
            // shares always sum to the pool cost exactly.
            if ($i === $count - 1) {
                $share = round($poolCost - $allocated, 2);
            }

            $allocated += $share;

            $state = $finished[$output['finished_id']];
            $state['in_qty'] += $output['qty_base'];
            $state['in_cost'] += $share;
            $finished[$output['finished_id']] = $state;
        }

        return [$materials, $finished];
    }

    /**
     * Add a product expense to the material cost pool: fully to its
     * linked item, otherwise spread across items proportionally to
     * their purchase cost so far. Expenses with nothing to attach to
     * are reported as unallocated.
     *
     * @param  array<int, MaterialState>  $materials
     * @return array<int, MaterialState>
     */
    private static function applyProductExpense(array $materials, array $event, float &$unallocatedExpense): array
    {
        if ($event['item_id'] !== null && isset($materials[$event['item_id']])) {
            $state = $materials[$event['item_id']];
            $state['in_cost'] += $event['amount'];
            $materials[$event['item_id']] = $state;

            return $materials;
        }

        $totalPurchaseCost = array_sum(array_map(
            fn (array $state): float => $state['purchase_cost'],
            $materials,
        ));

        if ($totalPurchaseCost <= 0.0) {
            $unallocatedExpense += $event['amount'];

            return $materials;
        }

        foreach ($materials as $id => $state) {
            if ($state['purchase_cost'] <= 0.0) {
                continue;
            }

            $state['in_cost'] += $event['amount'] * ($state['purchase_cost'] / $totalPurchaseCost);
            $materials[$id] = $state;
        }

        return $materials;
    }

    /**
     * @param  MaterialState|FinishedState  $state
     */
    private static function stateAvgCost(array $state): float
    {
        return $state['in_qty'] > 0.0 ? $state['in_cost'] / $state['in_qty'] : 0.0;
    }

    /**
     * @param  MaterialState|FinishedState  $state
     */
    private static function stateValue(array $state): float
    {
        $remaining = $state['in_qty'] - $state['out_qty'];

        return $remaining > 0.0 ? round($remaining * self::stateAvgCost($state), 2) : 0.0;
    }

    /**
     * Build display rows (quantity in the item's own unit) for states
     * that still hold stock or value.
     *
     * @param  array<int, MaterialState|FinishedState>  $states
     * @return array<int, array{item: PurchaseItem|SaleItem, quantity: float, base_quantity: float, avg_cost: float, value: float}>
     */
    private static function rowsFromStates(array $states): array
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
            }, $states),
            fn (array $row): bool => $row['base_quantity'] > 0.00001 || $row['value'] > 0.00001,
        ));
    }
}
