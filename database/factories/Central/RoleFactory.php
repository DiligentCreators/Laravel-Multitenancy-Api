<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            //
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Role $role) {
            // Create related models after the main model is created
        });
    }
}
