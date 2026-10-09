<?php

namespace Database\Factories;

use App\Models\OwnerWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerWithdrawal>
 */
class OwnerWithdrawalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 500, 50000),
            'note' => fake()->optional()->sentence(),
            'withdrawn_at' => now(),
        ];
    }
}
