<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\SettingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingGroupFactory extends Factory
{
    protected $model = SettingGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(1),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
