<?php

namespace Database\Factories\Crm;

use App\Enums\ConversationChannelEnum;
use App\Models\Crm\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'channel' => $this->faker->randomElement([ConversationChannelEnum::EMAIL->value, ConversationChannelEnum::SMS->value]),
            'body' => $this->faker->paragraph(),
            'variables' => [],
            'is_active' => true,
        ];
    }
}
