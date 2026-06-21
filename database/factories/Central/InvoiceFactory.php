<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $tenant = TenantFactory::new()->create();

        return [
            'invoice_number' => 'INV-'.$this->faker->unique()->numberBetween(1000, 9999),
            'tenant_id' => $tenant->id,
            'subscription_id' => SubscriptionFactory::new()->create(['tenant_id' => $tenant->id])->id,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'tax_amount' => $this->faker->randomFloat(2, 1, 50),
            'discount_amount' => $this->faker->randomFloat(2, 0, 50),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            // Create related models after the main model is created
        });
    }
}
