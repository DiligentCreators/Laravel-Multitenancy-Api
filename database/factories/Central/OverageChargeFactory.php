<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\OverageCharge;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OverageChargeFactory extends Factory
{
    protected $model = OverageCharge::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature' => $this->faker->randomElement(['users', 'contacts', 'storage', 'api_calls']),
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit_price' => $this->faker->randomFloat(2, 0.5, 10),
            'amount' => $this->faker->randomFloat(2, 5, 1000),
            'status' => 'pending',
        ];
    }
}
