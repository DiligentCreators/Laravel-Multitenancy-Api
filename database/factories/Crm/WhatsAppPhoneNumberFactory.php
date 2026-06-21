<?php

namespace Database\Factories\Crm;

use App\Models\Crm\WhatsAppPhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppPhoneNumberFactory extends Factory
{
    protected $model = WhatsAppPhoneNumber::class;

    public function definition(): array
    {
        return [
            'phone_number_id' => $this->faker->numerify('##########'),
            'display_phone_number' => $this->faker->phoneNumber(),
            'verified_name' => $this->faker->company(),
            'quality_rating' => $this->faker->randomElement(['green', 'yellow', 'red']),
            'status' => 'connected',
        ];
    }
}
