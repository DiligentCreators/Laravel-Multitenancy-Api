<?php

namespace Database\Factories\Crm;

use App\Models\Crm\WhatsAppWebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppWebhookLogFactory extends Factory
{
    protected $model = WhatsAppWebhookLog::class;

    public function definition(): array
    {
        return [
            'event_type' => $this->faker->randomElement(['messages', 'message_status']),
            'payload' => ['entry' => [['changes' => [['value' => ['messages' => []]]]]]],
            'created_at' => now(),
        ];
    }
}
