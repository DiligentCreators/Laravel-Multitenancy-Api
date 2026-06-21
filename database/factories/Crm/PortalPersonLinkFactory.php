<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\PortalPersonLink;
use App\Models\Crm\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class PortalPersonLinkFactory extends Factory
{
    protected $model = PortalPersonLink::class;

    public function definition(): array
    {
        return [
            'portal_user_id' => PortalUser::factory(),
        ];
    }

    public function forPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_id' => Person::factory(),
        ]);
    }

    public function forOrganization(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => Organization::factory(),
        ]);
    }
}
