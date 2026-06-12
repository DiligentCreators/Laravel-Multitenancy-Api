<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->text(),
            'monthly_price' => $this->faker->randomFloat(2, 0, 100),
            'yearly_price' => $this->faker->randomFloat(2, 0, 100),
            'trial_days' => $this->faker->numberBetween(0, 30),
            'is_active' => $this->faker->boolean(),
            'is_featured' => $this->faker->boolean(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Plan $plan) {
            // Create related models after the main model is created
        });
    }
}
