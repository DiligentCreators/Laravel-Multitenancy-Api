<?php

namespace Database\Factories\Crm;

use App\Enums\ConversationChannelEnum;
use App\Enums\ConversationStatusEnum;
use App\Models\Crm\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'subject' => $this->faker->sentence(),
            'channel' => $this->faker->randomElement(ConversationChannelEnum::cases()),
            'status' => ConversationStatusEnum::OPEN,
        ];
    }
}
