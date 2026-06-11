<?php

namespace Database\Factories\Central;

use App\Models\CentralUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<CentralUser>
 */
class CentralUserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign a role to the user after creating.
     *
     * @param  string  $role
     */
    public function withRole($role): static
    {
        return $this->afterCreating(function (CentralUser $user) use ($role) {
            $user->assignRole($role);
        });
    }
}
