<?php

namespace Database\Factories\Crm;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Models\Crm\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppAccountFactory extends Factory
{
    protected $model = WhatsAppAccount::class;

    public function definition(): array
    {
        return [
            'provider' => 'meta_cloud',
            'business_account_id' => $this->faker->numerify('##########'),
            'app_id' => $this->faker->numerify('############'),
            'app_secret' => $this->faker->sha256(),
            'access_token' => $this->faker->sha256(),
            'webhook_verify_token' => $this->faker->uuid(),
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ];
    }
}
