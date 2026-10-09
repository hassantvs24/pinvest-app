<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\ProductionOutput;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOutput>
 */
class ProductionOutputFactory extends Factory
{
    public function definition(): array
    {
        return [
            'production_id' => Production::factory(),
            'sale_item_id' => SaleItem::factory(),
            'quantity' => fake()->numberBetween(1, 20),
        ];
    }
}
