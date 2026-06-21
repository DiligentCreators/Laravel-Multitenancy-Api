<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('?????')),
            'type' => $this->faker->randomElement(['percentage', 'fixed']),
            'amount' => $this->faker->randomFloat(2, 5, 100),
            'usage_limit' => $this->faker->numberBetween(1, 100),
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Coupon $coupon) {
            // Create related models after the main model is created
        });
    }
}
