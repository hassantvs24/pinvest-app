<?php

namespace Database\Factories;

use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'unit' => 'pcs',
            'is_active' => true,
        ];
    }
}
