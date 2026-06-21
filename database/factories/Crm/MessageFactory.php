<?php

namespace Database\Factories\Crm;

use App\Enums\MessageDirectionEnum;
use App\Enums\MessageStatusEnum;
use App\Models\Crm\Conversation;
use App\Models\Crm\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => User::class,
            'sender_id' => User::factory(),
            'direction' => $this->faker->randomElement(MessageDirectionEnum::cases()),
            'body' => $this->faker->paragraph(),
            'status' => MessageStatusEnum::SENT,
        ];
    }
}
