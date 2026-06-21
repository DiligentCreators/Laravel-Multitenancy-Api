<?php

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->plan = Plan::factory()->create([
        'is_active' => true,
    ]);

    $this->tenant = Tenant::factory()->create();

    tenancy()->initialize($this->tenant);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

afterEach(function () {
    tenancy()->end();
});

it('allows access when tenant has an active subscription', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => SubscriptionBillingCycleEnum::MONTHLY,
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);

    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('allows access when tenant has a trial subscription', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
        'billing_cycle' => SubscriptionBillingCycleEnum::MONTHLY,
        'status' => SubscriptionStatusEnum::TRIAL,
    ]);

    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('denies access when tenant has no subscription', function () {
    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertStatus(402)
        ->assertJson([
            'status' => false,
            'message' => 'No active subscription found. Please subscribe to a plan.',
        ]);
});

it('denies access when subscription is expired', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'starts_at' => now()->subDays(60),
        'ends_at' => now()->subDays(30),
        'billing_cycle' => SubscriptionBillingCycleEnum::MONTHLY,
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);

    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertStatus(402)
        ->assertJson([
            'status' => false,
            'message' => 'Your subscription has expired. Please renew to continue.',
        ]);
});

it('denies access when subscription is suspended', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => SubscriptionBillingCycleEnum::MONTHLY,
        'status' => SubscriptionStatusEnum::SUSPENDED,
    ]);

    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertStatus(403)
        ->assertJson([
            'status' => false,
            'message' => 'Your subscription has been suspended. Please contact support.',
        ]);
});

it('denies access when subscription is cancelled', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => SubscriptionBillingCycleEnum::MONTHLY,
        'status' => SubscriptionStatusEnum::CANCELLED,
    ]);

    $this->actingAs($this->user, 'tenant-api')
        ->getJson('/api/tenant/v1/dashboard')
        ->assertStatus(402)
        ->assertJson([
            'status' => false,
            'message' => 'Your subscription has been cancelled. Please subscribe again.',
        ]);
});
