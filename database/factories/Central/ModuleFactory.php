<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().' Module',
            'slug' => $this->faker->unique()->slug(1),
            'description' => $this->faker->sentence(),
            'version' => '1.0.0',
            'is_enabled' => $this->faker->boolean(),
            'dependencies' => null,
        ];
    }
}
