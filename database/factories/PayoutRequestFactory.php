<?php

namespace Database\Factories;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'status' => 'pending',
        ];
    }
}
