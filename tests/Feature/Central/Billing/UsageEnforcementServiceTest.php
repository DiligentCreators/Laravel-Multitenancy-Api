<?php

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Central\UsageEnforcementService;
use App\Services\Central\UsageService;

beforeEach(function () {
    $this->service = app(UsageEnforcementService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plan = Plan::factory()->create(['monthly_price' => 100]);
    $this->plan->features()->attach(
        Feature::factory()->create(['slug' => 'users', 'type' => 'integer'])->id,
        ['value' => 10]
    );
    $this->plan->features()->attach(
        Feature::factory()->create(['slug' => 'unlimited-feature', 'type' => 'integer'])->id,
        ['value' => -1]
    );
    $this->plan->features()->attach(
        Feature::factory()->create(['slug' => 'disabled-feature', 'type' => 'boolean'])->id,
        ['value' => '0']
    );
});

it('denies access without active subscription', function () {
    $result = $this->service->checkLimit($this->tenant, 'users');

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toContain('No active subscription');
});

it('allows access when within usage limits', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $result = $this->service->checkLimit($this->tenant, 'users');

    expect($result['allowed'])->toBeTrue()
        ->and($result['limit'])->toBe(10)
        ->and($result['current'])->toBe(0);
});

it('blocks access when usage limit exceeded', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $usageService = app(UsageService::class);
    $usageService->increment($this->tenant, 'users', 10);

    $result = $this->service->checkLimit($this->tenant, 'users', 1);

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toContain('Usage limit exceeded');
});

it('allows unlimited features', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $result = $this->service->checkLimit($this->tenant, 'unlimited-feature', 999);

    expect($result['allowed'])->toBeTrue()
        ->and($result['limit'])->toBe(-1);
});

it('blocks disabled features', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $result = $this->service->checkLimit($this->tenant, 'disabled-feature');

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toContain('not available');
});

it('blocks non-existent features', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $result = $this->service->checkLimit($this->tenant, 'non-existent-feature');

    expect($result['allowed'])->toBeFalse();
});
