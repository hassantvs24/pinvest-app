<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Item;
use App\Models\Sale;
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
            'item_id' => Item::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
            'status' => EntryStatus::Pending,
        ];
    }
}
