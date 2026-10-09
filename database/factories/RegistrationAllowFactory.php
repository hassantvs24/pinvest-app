<?php

namespace Database\Factories;

use App\Models\RegistrationAllow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationAllow>
 */
class RegistrationAllowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '01'.fake()->unique()->numerify('#########'),
            'email' => null,
            'used_at' => null,
        ];
    }

    /**
     * Allowance consumed by a registration.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()]);
    }
}
