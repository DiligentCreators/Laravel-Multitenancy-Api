<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(1),
            'subject' => 'Welcome to {{app_name}}, {{user_name}}!',
            'body' => $this->faker->paragraph(),
            'variables' => ['app_name', 'user_name'],
            'is_active' => true,
        ];
    }
}
