<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'extra_cost' => 0,
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
        ];
    }
}
