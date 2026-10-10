<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'expense_head_id' => ExpenseHead::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
            'status' => EntryStatus::Pending,
        ];
    }
}
