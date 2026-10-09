<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHead;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Support\ItemUnits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
     * @return array<string, array{model: class-string<Model>, has_price: bool, has_unit: bool, relation: string}>
     */
    private function groups(): array
    {
        return [
            'expense-heads' => [
                'model' => ExpenseHead::class,
                'has_price' => false,
                'has_unit' => false,
                'relation' => 'expenses',
            ],
            'purchase-items' => [
                'model' => PurchaseItem::class,
                'has_price' => false,
                'has_unit' => true,
                'relation' => 'purchases',
            ],
            'sale-items' => [
                'model' => SaleItem::class,
                'has_price' => true,
                'has_unit' => true,
                'relation' => 'sales',
            ],
        ];
    }

    /**
     * @return array{model: class-string<Model>, has_price: bool, has_unit: bool, relation: string}
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
            'saleItems' => SaleItem::query()->orderBy('name')->get(),
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

        $validated = $request->validate($rules);

        $modelClass::query()->create([
            'name' => $validated['name'],
            'default_price' => $config['has_price'] ? $validated['default_price'] : null,
            'unit' => $config['has_unit'] ? $validated['unit'] : 'pcs',
        ]);

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
        ]);

        $item->update(['name' => $validated['name']]);

        return back()->with('success', __('messages.item_renamed'));
    }

    /**
     * Hard delete a master item — blocked (with a clear message) while
     * any entry still references it, to protect historical totals.
     */
    public function destroy(string $group, int $id): RedirectResponse
    {
        $config = $this->groupConfig($group);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $item = $modelClass::query()->findOrFail($id);

        if ($item->{$config['relation']}()->exists()) {
            return back()->with('error', __('messages.cannot_delete_in_use'));
        }

        $item->delete();

        return back()->with('success', __('messages.item_deleted'));
    }
}
