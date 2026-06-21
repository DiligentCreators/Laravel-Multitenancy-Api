<?php

namespace Database\Factories\Central;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'code' => fake()->unique()->countryCode(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
