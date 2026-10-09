<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'note' => fake()->optional()->sentence(),
            'payout_date' => now(),
        ];
    }
}
