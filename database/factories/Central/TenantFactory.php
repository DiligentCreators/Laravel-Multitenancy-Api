<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'company_name' => $this->faker->company(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $tenant->domains()->create([
                'domain' => Str::slug($tenant->company_name).'.'.$this->faker->freeEmailDomain(),
            ]);

            UserFactory::new()->create([
                'tenant_id' => $tenant->id,
            ]);
        });
    }
}
