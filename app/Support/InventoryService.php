<?php

namespace App\Support;

use App\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Production;
use App\Models\ProductionComponent;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLoss;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perpetual inventory with moving weighted average cost over ONE
 * unified pool per item.
 *
 * Every item flows through the same pool:
 *
 *   in  = purchases (quantity + cost) + production outputs
 *         (quantity + allocated production cost)
 *   out = sales (COGS) + production components + stock losses
 *
 * Average cost = total cost in ÷ total quantity in (moving, computed
 * as of each event's date). An item bought ready-made AND produced
 * in-house shares one fair average; grades that must stay separate
 * are simply separate items.
 *
 * Quantities are converted through ItemUnits (kg / gram / tola share a
 * gram base; count and volume units only ever match their own kind).
 * Everything is computed live from confirmed entries — nothing is
 * stored, so owner edits and deletes always stay consistent.
 *
 * @phpstan-type ItemState array{item: Item, in_qty: float, in_cost: float, purchase_cost: float, out_qty: float}
 * @phpstan-type TraceEntry array{sort: string, unit_cost: float, cost: float}
 * @phpstan-type Simulation array{states: array<int, ItemState>, cogs: float, loss_cost: float, warnings: list<string>, trace: list<TraceEntry>}
 */
class InventoryService
{
    /**
     * COGS (cost of goods sold) for confirmed sales between two dates
     * (by entry date, inclusive), at each sale date's weighted average
     * cost.
     */
    public static function cogs(Carbon $from, Carbon $to): float
    {
        return self::simulate($from, $to)['cogs'];
    }

    /**
     * Total cost of stock lost (confirmed losses) between two dates, at
     * each loss date's weighted average cost. Non-cash: it reduces
     * profit and stock value, not cash.
     */
    public static function stockLossCost(Carbon $from, Carbon $to): float
    {
        return self::simulate($from, $to)['loss_cost'];
    }

    /**
     * Total value of all stock on hand at the given date (at each
     * item's moving average cost).
     */
    public static function stockValue(Carbon $asOf): float
    {
        return array_sum(array_map(
            fn (array $state): float => self::stateValue($state),
            self::simulate(null, $asOf)['states'],
        ));
    }

    /**
     * Per-item stock rows for display: current quantity in the item's
     * own unit, remaining base quantity, average cost and value.
     *
     * @return array<int, array{item: Item, quantity: float, base_quantity: float, avg_cost: float, value: float}>
     */
    public static function stockRows(Carbon $asOf, bool $includeEmpty = false): array
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
            fn (array $row): bool => $includeEmpty || $row['base_quantity'] > 0.00001 || $row['value'] > 0.00001,
        ));
    }

    /**
     * Stock (base quantity) currently reserved by pending sales and
     * pending stock losses, keyed by item id. Pending entries do not
     * move stock yet, but they hold it so two people cannot claim the
     * same last unit twice.
     *
     * @return array<int, float>
     */
    public static function pendingReservations(): array
    {
        $reserved = Sale::query()->pending()->with('item')->get()
            ->groupBy('item_id')
            ->map(fn ($sales, $itemId): float => $sales
                ->sum(fn (Sale $sale): float => ItemUnits::toBase((float) $sale->quantity, $sale->item->unit)));

        StockLoss::query()->pending()->with('item')->get()
            ->groupBy('item_id')
            ->each(function ($losses, $itemId) use ($reserved): void {
                $reserved[$itemId] = ($reserved[$itemId] ?? 0.0) + $losses
                    ->sum(fn (StockLoss $loss): float => ItemUnits::toBase((float) $loss->quantity, $loss->item->unit));
            });

        // Pending production runs reserve their raw materials too.
        ProductionComponent::query()
            ->whereHas('production', fn ($query) => $query->where('status', EntryStatus::Pending->value))
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->each(function ($components, $itemId) use ($reserved): void {
                $reserved[$itemId] = ($reserved[$itemId] ?? 0.0) + $components
                    ->sum(fn (ProductionComponent $component): float => ItemUnits::toBase((float) $component->quantity, $component->item->unit));
            });

        return $reserved->all();
    }

    /**
     * How much of an item may still leave stock right now (sold,
     * written off or consumed in production): confirmed stock on hand
     * minus quantities reserved by other pending sales, pending stock
     * losses and pending production components.
     */
    public static function availableQuantity(
        Item $item,
        ?int $excludeSaleId = null,
        ?int $excludeLossId = null,
        ?int $excludeProductionId = null,
    ): float {
        $states = self::simulate(null, now())['states'];
        $onHand = ($states[$item->id]['in_qty'] ?? 0.0) - ($states[$item->id]['out_qty'] ?? 0.0);
        $reserved = Sale::query()->pending()
            ->where('item_id', $item->id)
            ->when($excludeSaleId !== null, fn ($query) => $query->where('id', '!=', $excludeSaleId))
            ->with('item')->get()
            ->sum(fn (Sale $sale): float => ItemUnits::toBase((float) $sale->quantity, $sale->item->unit));
        $reserved += StockLoss::query()->pending()
            ->where('item_id', $item->id)
            ->when($excludeLossId !== null, fn ($query) => $query->where('id', '!=', $excludeLossId))
            ->with('item')->get()
            ->sum(fn (StockLoss $loss): float => ItemUnits::toBase((float) $loss->quantity, $loss->item->unit));
        $reserved += ProductionComponent::query()
            ->where('item_id', $item->id)
            ->whereHas('production', fn ($query) => $query->where('status', EntryStatus::Pending->value))
            ->when($excludeProductionId !== null, fn ($query) => $query->where('production_id', '!=', $excludeProductionId))
            ->with('item')->get()
            ->sum(fn (ProductionComponent $component): float => ItemUnits::toBase((float) $component->quantity, $component->item->unit));

        return $onHand - $reserved;
    }

    /**
     * Available-to-use quantity per item (on hand minus pending
     * reservations), keyed by item id, in each item's own unit — for
     * form hints.
     *
     * @return array<int, float>
     */
    public static function availableMap(): array
    {
        $reservations = self::pendingReservations();
        $map = [];

        foreach (self::stockRows(now(), true) as $row) {
            $reserved = $reservations[$row['item']->id] ?? 0.0;
            $map[$row['item']->id] = ItemUnits::fromBase(
                max(0.0, $row['base_quantity'] - $reserved),
                $row['item']->unit,
            );
        }

        return $map;
    }

    /**
     * The moving weighted-average cost of one unit of an item, in the
     * item's own unit (0 when the item has no stock history yet — the
     * same basis the COGS calculation uses).
     */
    public static function avgCostPerUnit(Item $item): float
    {
        $states = self::simulate(null, now())['states'];
        $state = $states[$item->id] ?? null;

        if ($state === null || $state['in_qty'] <= 0.0) {
            return 0.0;
        }

        return self::stateAvgCost($state) * ItemUnits::toBase(1.0, $item->unit);
    }

    /**
     * Validate a production run before it is stored or confirmed.
     * Raw materials may not exceed what is available (pending runs
     * reserve their materials), and the finished goods must be worth
     * at least what the run consumes. Returns an error message or null.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $components
     * @param  array<int, array{item_id: int, quantity: int}>  $outputs
     */
    public static function validateProduction(
        array $components,
        array $outputs,
        float $extraCost,
        ?int $excludeProductionId = null,
    ): ?string {
        // Rows are validated distinct, but aggregate defensively so the
        // check stays correct for any caller.
        $sumByItem = fn (array $rows): array => collect($rows)
            ->groupBy('item_id')
            ->map(fn ($group): float => (float) $group->sum('quantity'))
            ->all();

        $componentsByItem = $sumByItem($components);
        $outputsByItem = $sumByItem($outputs);

        // 1. Raw materials must fit the available stock.
        foreach ($componentsByItem as $itemId => $quantity) {
            $item = Item::query()->find($itemId);
            if ($item === null) {
                continue;
            }
            $available = self::availableQuantity($item, excludeProductionId: $excludeProductionId);

            if (ItemUnits::toBase($quantity, $item->unit) > $available + 1e-9) {
                return __('messages.production_component_exceeds_stock', [
                    'item' => $item->name,
                    'available' => rtrim(rtrim(number_format(ItemUnits::fromBase(max(0.0, $available), $item->unit), 2), '0'), '.'),
                    'unit' => ItemUnits::label($item->unit),
                ]);
            }
        }

        // 2. The outputs must be worth at least the inputs.
        $inputCost = $extraCost;
        foreach ($componentsByItem as $itemId => $quantity) {
            $item = Item::query()->find($itemId);
            if ($item !== null) {
                $inputCost += $quantity * self::avgCostPerUnit($item);
            }
        }

        $outputValue = 0.0;
        foreach ($outputsByItem as $itemId => $quantity) {
            $item = Item::query()->find($itemId);
            if ($item !== null) {
                $outputValue += $quantity * (float) $item->default_price;
            }
        }

        if ($outputValue + 0.01 < $inputCost) {
            return __('messages.production_value_below_cost', [
                'cost' => number_format($inputCost, 2),
                'value' => number_format($outputValue, 2),
            ]);
        }

        return null;
    }

    /**
     * Chronological movement ledger for one item, computed live from
     * confirmed entries: purchases and production outputs add stock,
     * sales, production components and stock losses remove it. Each row
     * carries a running balance in the item's own unit. Pending sales
     * are returned separately — they reserve stock but do not move it.
     *
     * @return array{rows: list<array{date: Carbon, kind: string, direction: string, quantity: float, balance: float, unit_price: float|null, total: float|null, note: string}>, pending: list<array{date: Carbon, quantity: float, note: string}>, totals: array{purchase: array{qty: float, amount: float}, sale: array{qty: float, amount: float}, production_in: array{qty: float, amount: float}, production_out: array{qty: float, amount: float}, stock-loss: array{qty: float, amount: float}>}
     */
    public static function ledger(Item $item, Carbon $asOf): array
    {
        // Costs per movement (production allocation, sale/loss at the
        // average cost of that moment) come from the simulation trace.
        /** @var array<string, TraceEntry> $traceMap */
        $traceMap = collect(self::simulate(null, $asOf)['trace'])
            ->keyBy('sort')
            ->all();

        $events = collect();

        Purchase::query()->confirmed()->where('item_id', $item->id)
            ->whereDate('entry_date', '<=', $asOf)->with('user')->get()
            ->each(fn (Purchase $purchase) => $events->push([
                'sort' => $purchase->entry_date->format('Y-m-d').'|0|p'.$purchase->id,
                'date' => $purchase->entry_date,
                'kind' => 'purchase',
                'direction' => 'in',
                'quantity_base' => ItemUnits::toBase((float) $purchase->quantity, $item->unit),
                'unit_price' => (float) $purchase->unit_price,
                'total' => (float) $purchase->total,
                'note' => self::ledgerNote($purchase->user?->name, $purchase->note),
            ]));

        Production::query()->confirmed()->whereDate('entry_date', '<=', $asOf)
            ->where(function ($query) use ($item): void {
                $query->whereHas('components', fn ($q) => $q->where('item_id', $item->id))
                    ->orWhereHas('outputs', fn ($q) => $q->where('item_id', $item->id));
            })
            ->with(['components', 'outputs'])
            ->get()
            ->each(function (Production $production) use ($events, $item): void {
                foreach ($production->components->where('item_id', $item->id) as $component) {
                    $events->push([
                        'sort' => $production->entry_date->format('Y-m-d').'|2|prc'.$production->id.'c'.$component->id,
                        'date' => $production->entry_date,
                        'kind' => 'production',
                        'direction' => 'out',
                        'quantity_base' => ItemUnits::toBase((float) $component->quantity, $item->unit),
                        'unit_price' => null,
                        'total' => null,
                        'note' => self::ledgerProductionNote($production),
                    ]);
                }
                foreach ($production->outputs->where('item_id', $item->id) as $output) {
                    $events->push([
                        'sort' => $production->entry_date->format('Y-m-d').'|2|pro'.$production->id.'o'.$output->id,
                        'date' => $production->entry_date,
                        'kind' => 'production',
                        'direction' => 'in',
                        'quantity_base' => ItemUnits::toBase((float) $output->quantity, $item->unit),
                        'unit_price' => null,
                        'total' => null,
                        'note' => self::ledgerProductionNote($production),
                    ]);
                }
            });

        Sale::query()->confirmed()->where('item_id', $item->id)
            ->whereDate('entry_date', '<=', $asOf)->with('user')->get()
            ->each(fn (Sale $sale) => $events->push([
                'sort' => $sale->entry_date->format('Y-m-d').'|3|s'.$sale->id,
                'date' => $sale->entry_date,
                'kind' => 'sale',
                'direction' => 'out',
                'quantity_base' => ItemUnits::toBase((float) $sale->quantity, $item->unit),
                'unit_price' => (float) $sale->unit_price,
                'total' => (float) $sale->total,
                'note' => self::ledgerNote($sale->user?->name, $sale->note),
            ]));

        StockLoss::query()->confirmed()->where('item_id', $item->id)
            ->whereDate('entry_date', '<=', $asOf)->with('user')->get()
            ->each(fn (StockLoss $loss) => $events->push([
                'sort' => $loss->entry_date->format('Y-m-d').'|4|l'.$loss->id,
                'date' => $loss->entry_date,
                'kind' => 'stock-loss',
                'direction' => 'out',
                'quantity_base' => ItemUnits::toBase((float) $loss->quantity, $item->unit),
                'unit_price' => null,
                'total' => null,
                'note' => self::ledgerNote($loss->user?->name, $loss->note),
            ]));

        $balance = 0.0;
        $rows = $events->sortBy('sort')->values()->map(function (array $event) use (&$balance, $item, $traceMap): array {
            $balance += $event['direction'] === 'in' ? $event['quantity_base'] : -$event['quantity_base'];
            $costInfo = $traceMap[$event['sort']] ?? null;

            return [
                'date' => $event['date'],
                'kind' => $event['kind'],
                'direction' => $event['direction'],
                'quantity' => ItemUnits::fromBase($event['quantity_base'], $item->unit),
                'balance' => ItemUnits::fromBase($balance, $item->unit),
                'unit_price' => $event['unit_price'] ?? $costInfo['unit_cost'] ?? null,
                'total' => $event['total'] ?? $costInfo['cost'] ?? null,
                'note' => $event['note'],
            ];
        })->all();

        // Per-kind totals over the whole ledger (quantity in the item's
        // own unit + money moved).
        $totals = [
            'purchase' => ['qty' => 0.0, 'amount' => 0.0],
            'sale' => ['qty' => 0.0, 'amount' => 0.0],
            'production_in' => ['qty' => 0.0, 'amount' => 0.0],
            'production_out' => ['qty' => 0.0, 'amount' => 0.0],
            'stock-loss' => ['qty' => 0.0, 'amount' => 0.0],
        ];
        foreach ($rows as $row) {
            $key = $row['kind'] === 'production' ? 'production_'.$row['direction'] : $row['kind'];
            $totals[$key]['qty'] += $row['quantity'];
            $totals[$key]['amount'] += $row['total'] ?? 0.0;
        }

        $pending = Sale::query()->pending()->where('item_id', $item->id)
            ->whereDate('entry_date', '<=', $asOf)->with('user')->latest('entry_date')->get()
            ->map(fn (Sale $sale): array => [
                'date' => $sale->entry_date,
                'quantity' => (float) $sale->quantity,
                'note' => self::ledgerNote($sale->user?->name, $sale->note),
            ])->all();

        return ['rows' => $rows, 'pending' => $pending, 'totals' => $totals];
    }

    /**
     * Ledger note: who made the entry, plus their note when present.
     */
    private static function ledgerNote(?string $userName, ?string $note): string
    {
        return trim(($userName ?? '').($note ? ' — '.$note : ''));
    }

    /**
     * Ledger reference for a production run: its number plus note.
     */
    private static function ledgerProductionNote(Production $production): string
    {
        return '#'.$production->id.($production->note ? ' — '.$production->note : '');
    }

    /**
     * Human-readable warnings about data that makes COGS inaccurate:
     * negative stock and product expenses that could not be allocated
     * to any item.
     *
     * @return list<string>
     */
    public static function warnings(Carbon $asOf): array
    {
        return self::simulate(null, $asOf)['warnings'];
    }

    /**
     * Chronological simulation of every confirmed purchase, product
     * expense, production, sale and stock loss up to $to. When
     * $cogsFrom is given, only sales/losses on/after that date
     * accumulate cost totals (earlier events still shift stock so
     * averages stay correct).
     *
     * @return Simulation
     */
    private static function simulate(?Carbon $cogsFrom, Carbon $to): array
    {
        /** @var array<int, ItemState> $states */
        $states = Item::query()->get()
            ->mapWithKeys(fn (Item $item): array => [$item->id => [
                'item' => $item,
                'in_qty' => 0.0,
                'in_cost' => 0.0,
                'purchase_cost' => 0.0,
                'out_qty' => 0.0,
            ]])
            ->all();

        // Unified event stream, sorted by date; inputs (purchases,
        // product expenses, productions) process before sales and
        // losses on the same date so same-day input already counts
        // towards the average cost.
        $events = collect()
            ->merge(self::purchaseEvents($to))
            ->merge(self::productExpenseEvents($to))
            ->merge(self::productionEvents($to))
            ->merge(self::saleEvents($to, $cogsFrom))
            ->merge(self::stockLossEvents($to, $cogsFrom))
            ->sortBy(fn (array $event): string => $event['date'].'|'.$event['order'].'|'.$event['id'])
            ->values();

        $cogs = 0.0;
        $lossCost = 0.0;
        $unallocatedExpense = 0.0;
        $negativeNames = [];
        /** @var list<TraceEntry> $trace */
        $trace = [];

        foreach ($events as $event) {
            $sort = $event['date'].'|'.$event['order'].'|'.$event['id'];

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

            if ($event['kind'] === 'production') {
                $states = self::applyProduction($states, $event, $negativeNames, $trace);

                continue;
            }

            // Sale or stock loss: consume stock at the average cost
            // current right now.
            $state = $states[$event['item_id']];
            $unitCost = self::stateAvgCost($state);
            $cost = round($event['qty_base'] * $unitCost, 2);

            if ($event['kind'] === 'sale' && $event['count_cogs']) {
                $cogs += $cost;
            }

            if ($event['kind'] === 'stock-loss' && $event['count_cost']) {
                $lossCost += $cost;
            }

            $trace[] = ['sort' => $sort, 'unit_cost' => $unitCost, 'cost' => $cost];

            $state['out_qty'] += $event['qty_base'];
            $states[$event['item_id']] = $state;

            if ($state['out_qty'] > $state['in_qty']) {
                $negativeNames[$event['item_id']] = $state['item']->name;
            }
        }

        $warnings = [];

        if ($negativeNames !== []) {
            $warnings[] = __('messages.warn_negative_stock', ['items' => implode(', ', array_values($negativeNames))]);
        }

        if ($unallocatedExpense > 0.0) {
            $warnings[] = __('messages.warn_unallocated_product_expense', ['amount' => number_format($unallocatedExpense, 2)]);
        }

        return [
            'states' => $states,
            'cogs' => round($cogs, 2),
            'loss_cost' => round($lossCost, 2),
            'warnings' => $warnings,
            'trace' => $trace,
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
            ->with('item')
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'kind' => 'purchase',
                'id' => 'p'.$purchase->id,
                'date' => $purchase->entry_date->format('Y-m-d'),
                'order' => 0,
                'item_id' => $purchase->item_id,
                'qty_base' => ItemUnits::toBase((float) $purchase->quantity, $purchase->item->unit),
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
                'item_id' => $expense->item_id,
            ]);
    }

    /**
     * Productions up to $to as conversion events, each carrying its
     * component consumption and output lines (base quantities; output
     * values from quantity × default price drive the cost split).
     *
     * @return Collection<int, array{kind: string, id: string, num: int, date: string, order: int, extra_cost: float, components: array<int, array{item_id: int, cid: int, qty_base: float}>, outputs: array<int, array{item_id: int, oid: int, qty_base: float, value: float}>}>
     */
    private static function productionEvents(Carbon $to): Collection
    {
        return Production::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->with(['components.item', 'outputs.item'])
            ->get()
            ->map(fn (Production $production): array => [
                'kind' => 'production',
                'id' => 'pr'.$production->id,
                'num' => $production->id,
                'date' => $production->entry_date->format('Y-m-d'),
                'order' => 2,
                'extra_cost' => (float) $production->extra_cost,
                'components' => $production->components
                    ->map(fn ($component): array => [
                        'item_id' => $component->item_id,
                        'cid' => $component->id,
                        'qty_base' => ItemUnits::toBase((float) $component->quantity, $component->item->unit),
                    ])
                    ->all(),
                'outputs' => $production->outputs
                    ->map(fn ($output): array => [
                        'item_id' => $output->item_id,
                        'oid' => $output->id,
                        'qty_base' => ItemUnits::toBase((float) $output->quantity, $output->item->unit),
                        'value' => (float) $output->quantity * (float) $output->item->default_price,
                    ])
                    ->all(),
            ]);
    }

    /**
     * Confirmed sales up to $to as consumption events. Sales before
     * $cogsFrom reduce stock only.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, item_id: int, qty_base: float, count_cogs: bool}>
     */
    private static function saleEvents(Carbon $to, ?Carbon $cogsFrom): Collection
    {
        return Sale::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->with('item')
            ->get()
            ->map(fn (Sale $sale): array => [
                'kind' => 'sale',
                'id' => 's'.$sale->id,
                'date' => $sale->entry_date->format('Y-m-d'),
                'order' => 3,
                'item_id' => $sale->item_id,
                'qty_base' => ItemUnits::toBase((float) $sale->quantity, $sale->item->unit),
                'count_cogs' => $cogsFrom === null || $sale->entry_date->greaterThanOrEqualTo($cogsFrom),
            ]);
    }

    /**
     * Confirmed stock losses up to $to as consumption events. Losses
     * before $countFrom reduce stock only.
     *
     * @return Collection<int, array{kind: string, id: string, date: string, order: int, item_id: int, qty_base: float, count_cost: bool}>
     */
    private static function stockLossEvents(Carbon $to, ?Carbon $countFrom): Collection
    {
        return StockLoss::query()
            ->confirmed()
            ->whereDate('entry_date', '<=', $to)
            ->with('item')
            ->get()
            ->map(fn (StockLoss $loss): array => [
                'kind' => 'stock-loss',
                'id' => 'l'.$loss->id,
                'date' => $loss->entry_date->format('Y-m-d'),
                'order' => 4,
                'item_id' => $loss->item_id,
                'qty_base' => ItemUnits::toBase((float) $loss->quantity, $loss->item->unit),
                'count_cost' => $countFrom === null || $loss->entry_date->greaterThanOrEqualTo($countFrom),
            ]);
    }

    /**
     * Apply one production: consume each component at the average cost
     * current now, then split the pooled cost across the outputs
     * (proportional to sale value, even split as fallback) and add each
     * share to the output's cost pool.
     *
     * @param  array<int, ItemState>  $states
     * @param  array<int, string>  $negativeNames
     * @param  list<TraceEntry>  $trace
     * @return array<int, ItemState>
     */
    private static function applyProduction(array $states, array $event, array &$negativeNames, array &$trace): array
    {
        $poolCost = $event['extra_cost'];

        foreach ($event['components'] as $component) {
            if (! isset($states[$component['item_id']])) {
                continue;
            }

            $state = $states[$component['item_id']];
            $unitCost = self::stateAvgCost($state);
            $poolCost += $component['qty_base'] * $unitCost;

            $trace[] = [
                'sort' => $event['date'].'|2|prc'.$event['num'].'c'.$component['cid'],
                'unit_cost' => $unitCost,
                'cost' => round($component['qty_base'] * $unitCost, 2),
            ];

            $state['out_qty'] += $component['qty_base'];
            $states[$component['item_id']] = $state;

            if ($state['out_qty'] > $state['in_qty']) {
                $negativeNames[$component['item_id']] = $state['item']->name;
            }
        }

        $outputs = array_values(array_filter(
            $event['outputs'],
            fn (array $output): bool => isset($states[$output['item_id']]),
        ));
        $totalValue = array_sum(array_map(fn (array $output): float => $output['value'], $outputs));
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

            $trace[] = [
                'sort' => $event['date'].'|2|pro'.$event['num'].'o'.$output['oid'],
                'unit_cost' => $output['qty_base'] > 0.0 ? $share / $output['qty_base'] : 0.0,
                'cost' => $share,
            ];

            $state = $states[$output['item_id']];
            $state['in_qty'] += $output['qty_base'];
            $state['in_cost'] += $share;
            $states[$output['item_id']] = $state;
        }

        return $states;
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
     * @param  ItemState  $state
     */
    private static function stateAvgCost(array $state): float
    {
        return $state['in_qty'] > 0.0 ? $state['in_cost'] / $state['in_qty'] : 0.0;
    }

    /**
     * @param  ItemState  $state
     */
    private static function stateValue(array $state): float
    {
        $remaining = $state['in_qty'] - $state['out_qty'];

        return $remaining > 0.0 ? round($remaining * self::stateAvgCost($state), 2) : 0.0;
    }
}
