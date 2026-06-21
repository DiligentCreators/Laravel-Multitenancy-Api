<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Conversation;
use App\Models\Crm\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationParticipantFactory extends Factory
{
    protected $model = ConversationParticipant::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'participant_type' => User::class,
            'participant_id' => User::factory(),
            'is_primary' => false,
        ];
    }
}
