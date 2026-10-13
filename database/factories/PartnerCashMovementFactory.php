<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\PartnerCashMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCashMovement>
 */
class PartnerCashMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => CashMovementType::Hold,
            'amount' => fake()->randomFloat(2, 100, 50000),
            'note' => fake()->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }

    public function hold(): static
    {
        return $this->state(fn (): array => ['type' => CashMovementType::Hold]);
    }

    public function returned(): static
    {
        return $this->state(fn (): array => ['type' => CashMovementType::Return]);
    }

    public function spent(): static
    {
        return $this->state(fn (): array => ['type' => CashMovementType::Spent]);
    }
}
