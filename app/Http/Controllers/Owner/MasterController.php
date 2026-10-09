<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ExpenseCostType;
use App\Http\Controllers\Controller;
use App\Models\ExpenseHead;
use App\Models\Production;
use App\Models\ProductionComponent;
use App\Models\ProductionOutput;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Support\ItemUnits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owner-only management of dropdown master data:
 * expense heads, purchase items, sale items.
 */
class MasterController extends Controller
{
    /**
     * Allowed master groups => configuration.
     *
     * @return array<string, array{model: class-string<Model>, has_price: bool, has_unit: bool, has_link: bool, has_cost_type: bool, relation: string}>
     */
    private function groups(): array
    {
        return [
            'expense-heads' => [
                'model' => ExpenseHead::class,
                'has_price' => false,
                'has_unit' => false,
                'has_link' => false,
                'has_cost_type' => true,
                'relation' => 'expenses',
            ],
            'purchase-items' => [
                'model' => PurchaseItem::class,
                'has_price' => false,
                'has_unit' => true,
                'has_link' => false,
                'has_cost_type' => false,
                'relation' => 'purchases',
            ],
            'sale-items' => [
                'model' => SaleItem::class,
                'has_price' => true,
                'has_unit' => true,
                'has_link' => true,
                'has_cost_type' => false,
                'relation' => 'sales',
            ],
        ];
    }

    /**
     * @return array{model: class-string<Model>, has_price: bool, has_unit: bool, has_link: bool, has_cost_type: bool, relation: string}
     */
    private function groupConfig(string $group): array
    {
        $groups = $this->groups();
        abort_unless(isset($groups[$group]), 404);

        return $groups[$group];
    }

    /**
     * Show all three master sections on one page.
     */
    public function index(): View
    {
        return view('owner.masters', [
            'expenseHeads' => ExpenseHead::query()->orderBy('name')->get(),
            'purchaseItems' => PurchaseItem::query()->orderBy('name')->get(),
            'saleItems' => SaleItem::query()->with('purchaseItem')->orderBy('name')->get(),
        ]);
    }

    /**
     * Add a new master item (inline form).
     */
    public function store(string $group, Request $request): RedirectResponse
    {
        $config = $this->groupConfig($group);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique((new $modelClass)->getTable(), 'name')],
        ];
        if ($config['has_price']) {
            $rules['default_price'] = ['required', 'numeric', 'min:0'];
        }
        if ($config['has_unit']) {
            $rules['unit'] = ['required', ItemUnits::rule()];
        }
        if ($config['has_link']) {
            $rules['purchase_item_id'] = ['nullable', 'exists:purchase_items,id'];
        }
        if ($config['has_cost_type']) {
            $rules['cost_type'] = ['required', Rule::enum(ExpenseCostType::class)];
        }

        $validated = $request->validate($rules);

        if ($config['has_link'] && ! empty($validated['purchase_item_id'])) {
            $this->ensureCompatibleUnits($validated['unit'], (int) $validated['purchase_item_id']);
        }

        $payload = [
            'name' => $validated['name'],
            'default_price' => $config['has_price'] ? $validated['default_price'] : null,
            'unit' => $config['has_unit'] ? $validated['unit'] : 'pcs',
        ];

        if ($config['has_link']) {
            $payload['purchase_item_id'] = $validated['purchase_item_id'] ?? null;
        }

        if ($config['has_cost_type']) {
            $payload['cost_type'] = $validated['cost_type'];
        }

        $modelClass::query()->create($payload);

        return back()->with('success', __('messages.item_added'));
    }

    /**
     * Toggle a master item active/inactive (inactive = hidden from dropdowns).
     */
    public function toggle(string $group, int $id): RedirectResponse
    {
        $config = $this->groupConfig($group);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $item = $modelClass::query()->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', __('messages.saved_success'));
    }

    /**
     * Rename a master item. Allowed even when entries reference it —
     * that is exactly why editing exists (used items cannot be deleted).
     * Names must stay unique within the group.
     */
    public function update(string $group, int $id, Request $request): RedirectResponse
    {
        $config = $this->groupConfig($group);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $item = $modelClass::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($item->getTable(), 'name')->ignore($item->id),
            ],
            'purchase_item_id' => ['nullable', 'exists:purchase_items,id'],
            'cost_type' => ['nullable', Rule::enum(ExpenseCostType::class)],
        ]);

        if ($config['has_link']) {
            $this->ensureCompatibleUnits($item->unit, (int) ($validated['purchase_item_id'] ?? 0));
        }

        $payload = ['name' => $validated['name']];

        if ($config['has_link']) {
            $payload['purchase_item_id'] = $validated['purchase_item_id'];
        }

        if ($config['has_cost_type']) {
            $payload['cost_type'] = $validated['cost_type'] ?? $item->cost_type;
        }

        $item->update($payload);

        return back()->with('success', __('messages.item_renamed'));
    }

    /**
     * A sale item may only resell a purchase item whose unit is
     * convertible (same unit category), otherwise stock and COGS
     * would be meaningless.
     */
    private function ensureCompatibleUnits(string $unit, int $purchaseItemId): void
    {
        if ($purchaseItemId <= 0) {
            return;
        }

        $purchaseItem = PurchaseItem::query()->findOrFail($purchaseItemId);

        if (! ItemUnits::compatible($unit, $purchaseItem->unit)) {
            throw ValidationException::withMessages([
                'purchase_item_id' => __('messages.incompatible_units'),
            ]);
        }
    }

    /**
     * Hard delete a master item — blocked (with a clear message) while
     * any entry still references it, to protect historical totals and
     * stock links. Purchase items are additionally guarded by sale-item
     * links and production components (losing those would silently
     * break COGS); sale items by production runs.
     */
    public function destroy(string $group, int $id): RedirectResponse
    {
        $config = $this->groupConfig($group);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $item = $modelClass::query()->findOrFail($id);

        if ($item->{$config['relation']}()->exists() || $this->isReferencedElsewhere($group, $item)) {
            return back()->with('error', __('messages.cannot_delete_in_use'));
        }

        $item->delete();

        return back()->with('success', __('messages.item_deleted'));
    }

    /**
     * Extra delete guards beyond the group's own entries.
     */
    private function isReferencedElsewhere(string $group, Model $item): bool
    {
        if ($group === 'purchase-items') {
            return SaleItem::query()->where('purchase_item_id', $item->id)->exists()
                || ProductionComponent::query()->where('purchase_item_id', $item->id)->exists();
        }

        if ($group === 'sale-items') {
            return ProductionOutput::query()->where('sale_item_id', $item->id)->exists();
        }

        return false;
    }
}
