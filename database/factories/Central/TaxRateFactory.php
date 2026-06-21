<?php

namespace Database\Factories\Central;

use App\Models\TaxRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tax_region_id' => TaxRegion::factory(),
            'name' => fake()->word().' Tax',
            'rate' => fake()->randomFloat(2, 0, 25),
            'type' => 'percentage',
            'is_active' => true,
            'effective_from' => now()->startOfYear(),
        ];
    }
}
