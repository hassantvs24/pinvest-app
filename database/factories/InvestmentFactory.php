<?php

namespace Database\Factories;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'note' => fake()->optional()->sentence(),
            'invested_at' => now(),
        ];
    }
}
