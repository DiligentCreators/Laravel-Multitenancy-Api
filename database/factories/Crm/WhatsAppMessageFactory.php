<?php

namespace Database\Factories\Crm;

use App\Enums\WhatsAppMessageDirectionEnum;
use App\Enums\WhatsAppMessageStatusEnum;
use App\Enums\WhatsAppMessageTypeEnum;
use App\Models\Crm\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppMessageFactory extends Factory
{
    protected $model = WhatsAppMessage::class;

    public function definition(): array
    {
        return [
            'provider_message_id' => $this->faker->uuid(),
            'direction' => WhatsAppMessageDirectionEnum::INBOUND,
            'type' => WhatsAppMessageTypeEnum::TEXT,
            'from_number' => $this->faker->phoneNumber(),
            'to_number' => $this->faker->phoneNumber(),
            'content' => $this->faker->sentence(),
            'status' => WhatsAppMessageStatusEnum::DELIVERED,
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now()->subMinutes(4),
        ];
    }
}
