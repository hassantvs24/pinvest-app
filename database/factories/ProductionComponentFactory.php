<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\ProductionComponent;
use App\Models\PurchaseItem;
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
            'purchase_item_id' => PurchaseItem::factory(),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
