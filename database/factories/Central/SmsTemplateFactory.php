<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsTemplateFactory extends Factory
{
    protected $model = SmsTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(1),
            'message' => $this->faker->sentence(),
            'variables' => ['user_name', 'app_name'],
            'is_active' => true,
        ];
    }
}
