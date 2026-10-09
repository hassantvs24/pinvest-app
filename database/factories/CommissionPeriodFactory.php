<?php

namespace Database\Factories;

use App\Models\CommissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionPeriod>
 */
class CommissionPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => null,
            'opened_at' => now()->startOfMonth()->subMonth(),
            'opening_cash' => null,
            'note' => null,
            'status' => 'closed',
            'profit' => null,
            'closed_at' => now()->startOfMonth()->subMonth()->endOfMonth(),
        ];
    }

    /**
     * Currently open period.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'profit' => null,
            'closed_at' => null,
        ]);
    }
}
