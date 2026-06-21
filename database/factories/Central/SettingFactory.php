<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'group_id' => SettingGroup::factory(),
            'key' => $this->faker->unique()->word(),
            'label' => $this->faker->word(),
            'value' => $this->faker->word(),
            'type' => 'text',
            'default_value' => null,
            'validation_rules' => null,
            'is_public' => true,
            'is_encrypted' => false,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
