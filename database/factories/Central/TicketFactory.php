<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $tenant = TenantFactory::new()->create();

        return [
            'ticket_number' => 'TKT-'.$this->faker->unique()->numberBetween(1000, 9999),
            'tenant_id' => $tenant->id,
            'subject' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(2, true),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Ticket $ticket) {
            // Create related models after the main model is created
        });
    }
}
