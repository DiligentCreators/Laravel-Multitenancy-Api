<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(1),
            'channel' => 'in_app',
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'variables' => ['user_name', 'app_name'],
            'is_active' => true,
        ];
    }
}
