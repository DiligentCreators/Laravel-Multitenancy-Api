<?php

namespace Database\Factories;

use App\Models\CentralUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'central_user_id' => CentralUser::factory(),
            'action' => fake()->randomElement(['login', 'logout', 'config_change', 'data_export']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'context' => [],
        ];
    }
}
