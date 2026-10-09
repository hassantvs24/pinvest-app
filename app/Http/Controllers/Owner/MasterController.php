<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ExpenseCostType;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Support\ItemUnits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner-only management of dropdown master data: expense heads and
 * the unified item list (the same items are bought, sold and used in
 * production — one weighted-average pool each).
 */
class MasterController extends Controller
{
    /**
     * Allowed master groups => configuration.
     *
     * @return array<string, array{model: class-string<Model>, has_price: bool, has_unit: bool, has_cost_type: bool, relation: string}>
     */
    private function groups(): array
    {
        return [
            'expense-heads' => [
                'model' => ExpenseHead::class,
                'has_price' => false,
                'has_unit' => false,
                'has_cost_type' => true,
                'relation' => 'expenses',
            ],
            'items' => [
                'model' => Item::class,
                'has_price' => true,
                'has_unit' => true,
                'has_cost_type' => false,
                'relation' => 'purchases',
            ],
        ];
    }

    /**
     * @return array{model: class-string<Model>, has_price: bool, has_unit: bool, has_cost_type: bool, relation: string}
     */
    private function groupConfig(string $group): array
    {
        $groups = $this->groups();
        abort_unless(isset($groups[$group]), 404);

        return $groups[$group];
    }

    /**
     * Show both master sections on one page.
     */
    public function index(): View
    {
        return view('owner.masters', [
            'expenseHeads' => ExpenseHead::query()->orderBy('name')->get(),
            'items' => Item::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Add a new master row (inline form).
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
        if ($config['has_cost_type']) {
            $rules['cost_type'] = ['required', Rule::enum(ExpenseCostType::class)];
        }

        $validated = $request->validate($rules);

        $payload = [
            'name' => $validated['name'],
            'default_price' => $config['has_price'] ? $validated['default_price'] : null,
            'unit' => $config['has_unit'] ? $validated['unit'] : 'pcs',
        ];

        if ($config['has_cost_type']) {
            $payload['cost_type'] = $validated['cost_type'];
        }

        $modelClass::query()->create($payload);

        return back()->with('success', __('messages.item_added'));
    }

    /**
     * Toggle a master row active/inactive (inactive = hidden from dropdowns).
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
     * Rename a master row. Allowed even when entries reference it —
     * that is exactly why editing exists (used rows cannot be deleted).
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
            'cost_type' => ['nullable', Rule::enum(ExpenseCostType::class)],
        ]);

        $payload = ['name' => $validated['name']];

        if ($config['has_cost_type']) {
            $payload['cost_type'] = $validated['cost_type'] ?? $item->cost_type;
        }

        $item->update($payload);

        return back()->with('success', __('messages.item_renamed'));
    }

    /**
     * Hard delete a master row — blocked (with a clear message) while
     * any entry, production line or loss still references it, to
     * protect historical totals and stock links.
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
     * Extra delete guards beyond the group's own entries (the unified
     * item list is referenced from many places).
     */
    private function isReferencedElsewhere(string $group, Model $item): bool
    {
        if ($group === 'items') {
            return $item->sales()->exists()
                || $item->productionComponents()->exists()
                || $item->productionOutputs()->exists()
                || $item->stockLosses()->exists()
                || Expense::query()->where('item_id', $item->id)->exists();
        }

        return false;
    }
}
