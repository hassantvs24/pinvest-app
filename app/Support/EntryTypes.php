<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for the three partner entry types (expenses,
 * purchases, sales). Controllers, views and the cycle-close guard all
 * read this one config — models, relations, icons and label keys live
 * here instead of being duplicated per controller.
 */
class EntryTypes
{
    /**
     * @return array<string, array{model: class-string<Model>, items: class-string<Model>, item_relation: string, item_field: string, relations: array<int, string>, label: string, icon: string, has_quantity: bool, has_unit: bool, has_head: bool}>
     */
    public static function all(): array
    {
        return [
            'expenses' => [
                'model' => Expense::class,
                'items' => ExpenseHead::class,
                'item_relation' => 'expenseHead',
                'item_field' => 'expense_head_id',
                'relations' => ['user', 'expenseHead'],
                'label' => 'expenses',
                'icon' => '💸',
                'has_quantity' => false,
                'has_unit' => false,
                'has_head' => true,
            ],
            'purchases' => [
                'model' => Purchase::class,
                'items' => Item::class,
                'item_relation' => 'item',
                'item_field' => 'item_id',
                'relations' => ['user', 'item'],
                'label' => 'purchases',
                'icon' => '🛒',
                'has_quantity' => true,
                'has_unit' => true,
                'has_head' => false,
            ],
            'sales' => [
                'model' => Sale::class,
                'items' => Item::class,
                'item_relation' => 'item',
                'item_field' => 'item_id',
                'relations' => ['user', 'item'],
                'label' => 'sales',
                'icon' => '💰',
                'has_quantity' => true,
                'has_unit' => true,
                'has_head' => false,
            ],
        ];
    }

    /**
     * Config for one entry type or 404 (same guard the controllers used
     * to apply themselves).
     *
     * @return array{model: class-string<Model>, items: class-string<Model>, item_relation: string, item_field: string, relations: array<int, string>, label: string, icon: string, has_quantity: bool, has_unit: bool, has_head: bool}
     */
    public static function config(string $type): array
    {
        abort_unless(isset(self::all()[$type]), 404);

        return self::all()[$type];
    }
}
