<?php

namespace Database\Factories;

use App\EntryStatus;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50);
        $unitPrice = fake()->randomFloat(2, 10, 1000);

        return [
            'user_id' => User::factory(),
            'item_id' => Item::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
            'status' => EntryStatus::Pending,
        ];
    }
}
