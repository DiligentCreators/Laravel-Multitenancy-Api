<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\SettingDefinitionTypeEnum;
use App\Models\SettingDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingDefinition>
 */
class SettingDefinitionFactory extends Factory
{
    protected $model = SettingDefinition::class;

    public function definition(): array
    {
        return [
            'group' => $this->faker->word,
            'key' => $this->faker->word,
            'label' => $this->faker->word,
            'type' => $this->faker->randomElement(SettingDefinitionTypeEnum::cases())->value,
            'default_value' => $this->faker->word,
            'is_required' => $this->faker->boolean(),
            'is_active' => $this->faker->boolean(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SettingDefinition $settingDefinition) {
            // Create related models after the main model is created
        });
    }
}
