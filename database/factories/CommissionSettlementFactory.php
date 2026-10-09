<?php

namespace Database\Factories;

use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSettlement>
 */
class CommissionSettlementFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfMonth()->subMonth();

        return [
            'user_id' => User::factory(),
            'period_start' => $start,
            'period_end' => $start->copy()->endOfMonth(),
            'business_profit' => 1000,
            'commission_rate' => 10,
            'amount' => 100,
            'status' => 'pending',
        ];
    }

    /**
     * Paid settlement.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paid']);
    }
}
