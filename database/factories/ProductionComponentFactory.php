<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Production;
use App\Models\ProductionComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionComponent>
 */
class ProductionComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'production_id' => Production::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
