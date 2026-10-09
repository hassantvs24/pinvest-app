<?php

namespace Database\Factories;

use App\EntryStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50);
        $unitPrice = fake()->randomFloat(2, 10, 2000);
        $total = round($quantity * $unitPrice, 2);

        return [
            'user_id' => User::factory(),
            'sale_item_id' => SaleItem::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
            'status' => EntryStatus::Pending,
        ];
    }
}
