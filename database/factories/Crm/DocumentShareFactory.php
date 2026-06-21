<?php

namespace Database\Factories\Crm;

use App\Models\Crm\DocumentShare;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentShareFactory extends Factory
{
    protected $model = DocumentShare::class;

    public function definition(): array
    {
        return [
            'share_token' => $this->faker->uuid(),
            'password_protected' => false,
            'access_count' => 0,
        ];
    }
}
