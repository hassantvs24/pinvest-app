<?php

namespace Database\Factories;

use App\Enums\ExpenseCostType;
use App\Models\ExpenseHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseHead>
 */
class ExpenseHeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'cost_type' => ExpenseCostType::General,
            'is_active' => true,
        ];
    }
}
