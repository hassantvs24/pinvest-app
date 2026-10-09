<?php

namespace Database\Factories;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'default_price' => fake()->randomFloat(2, 50, 2000),
            'is_active' => true,
        ];
    }
}
