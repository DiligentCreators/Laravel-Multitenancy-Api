<?php

namespace Database\Factories\Crm;

use App\Models\Crm\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class PortalUserFactory extends Factory
{
    protected $model = PortalUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'invited_at' => now(),
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'invited_at' => now()->subDay(),
            'registered_at' => now(),
        ]);
    }
}
