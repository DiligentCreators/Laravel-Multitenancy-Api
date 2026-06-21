<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word().'-api-key',
            'key' => ApiKey::generateKey(),
            'permissions' => ['read', 'write'],
            'expires_at' => now()->addYear(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ApiKey $apiKey) {
            // Create related models after the main model is created
        });
    }
}
