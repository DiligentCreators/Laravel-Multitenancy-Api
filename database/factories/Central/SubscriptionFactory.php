<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new()->create()->id,
            'plan_id' => PlanFactory::new()->create()->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'billing_cycle' => $this->faker->randomElement(SubscriptionBillingCycleEnum::cases()),
            'status' => $this->faker->randomElement(SubscriptionStatusEnum::cases()),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Subscription $subscription) {
            // Create related models after the main model is created
        });
    }
}
