<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => InvoiceFactory::new()->create()->id,
            'tenant_id' => TenantFactory::new()->create()->id,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'gateway' => $this->faker->randomElement(['stripe', 'paypal']),
            'transaction_id' => 'txn_'.$this->faker->uuid(),
            'status' => 'pending',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            // Create related models after the main model is created
        });
    }
}
