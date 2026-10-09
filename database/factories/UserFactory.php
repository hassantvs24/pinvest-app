<?php

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '01'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Partner,
            'commission_rate' => 0,
            'preferred_language' => 'bn',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Owner account state.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }
}
