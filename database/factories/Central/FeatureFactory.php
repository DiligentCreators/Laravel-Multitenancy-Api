<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->text(),
            'type' => $this->faker->randomElement(['boolean', 'integer', 'decimal', 'string']),
            'is_active' => $this->faker->boolean(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Feature $feature) {
            // Create related models after the main model is created
        });
    }
}
