<?php

namespace Database\Factories\Central;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProrationRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement(['upgrade', 'downgrade']),
            'credit_amount' => fake()->randomFloat(2, 0, 100),
            'charge_amount' => fake()->randomFloat(2, 0, 100),
            'net_amount' => 0,
            'currency' => 'USD',
            'details' => null,
            'status' => 'pending',
        ];
    }
}
