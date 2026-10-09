<?php

namespace Database\Factories;

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
            'is_active' => true,
        ];
    }
}
