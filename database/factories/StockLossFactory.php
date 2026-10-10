<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Item;
use App\Models\StockLoss;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLoss>
 */
class StockLossFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'note' => fake()->optional()->sentence(),
            'entry_date' => now(),
            'status' => EntryStatus::Confirmed,
        ];
    }
}
