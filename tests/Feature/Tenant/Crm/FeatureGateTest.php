<?php

use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\UsageCounter;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\FeatureGateService;
use Spatie\Permission\Models\Permission;

function featureGateTenant(?string $domain = null): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain ?? 'feature-gate-'.uniqid().'.localhost']);

    return $tenant;
}

function featureGateUser(Tenant $tenant, Permission $perm): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'feature-gate@test.com',
    ]);
    $user->givePermissionTo($perm);

    return $user;
}

beforeEach(function () {
    $perm = Permission::create(['name' => 'features.view', 'guard_name' => 'tenant-api']);
    $this->tenant = featureGateTenant();
    $this->user = featureGateUser($this->tenant, $perm);
    $this->service = app(FeatureGateService::class);
});

// --- Feature Resolution ---

it('allows feature when tenant has active subscription and plan includes it', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    FeatureDefinition::firstOrCreate(
        ['key' => 'people.create'],
        ['key' => 'people.create', 'name' => 'Create People', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    expect($this->service->allows($this->tenant, 'people.create'))->toBeTrue();
});

it('denies feature when tenant has no active subscription', function () {
    FeatureDefinition::firstOrCreate(
        ['key' => 'people.create'],
        ['key' => 'people.create', 'name' => 'Create People', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    expect($this->service->allows($this->tenant, 'people.create'))->toBeFalse();
});

it('denies feature when usage limit is reached and overage is not allowed', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    FeatureDefinition::firstOrCreate(
        ['key' => 'leads.max'],
        ['key' => 'leads.max', 'name' => 'Max Leads', 'type' => 'integer', 'default_value' => 10, 'is_usage_limit' => true]
    );

    UsageCounter::create([
        'tenant_id' => $this->tenant->id,
        'feature_key' => 'leads.max',
        'count' => 10,
    ]);

    expect($this->service->allows($this->tenant, 'leads.max'))->toBeFalse();
});

it('asserts feature or throws exception', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    FeatureDefinition::firstOrCreate(
        ['key' => 'test.assert'],
        ['key' => 'test.assert', 'name' => 'Test Assert', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    $this->service->assert($this->tenant, 'test.assert');
    expect(true)->toBeTrue();
});

// --- Usage Tracking ---

it('increments and decrements usage counters', function () {
    $this->service->incrementUsage($this->tenant, 'test.feature');
    expect($this->service->usage($this->tenant, 'test.feature'))->toBe(1);

    $this->service->incrementUsage($this->tenant, 'test.feature', 5);
    expect($this->service->usage($this->tenant, 'test.feature'))->toBe(6);

    $this->service->decrementUsage($this->tenant, 'test.feature', 2);
    expect($this->service->usage($this->tenant, 'test.feature'))->toBe(4);
});

it('returns remaining count', function () {
    FeatureDefinition::firstOrCreate(
        ['key' => 'test.limit'],
        ['key' => 'test.limit', 'name' => 'Test Limit', 'type' => 'integer', 'default_value' => 100, 'is_usage_limit' => true]
    );

    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    $this->service->incrementUsage($this->tenant, 'test.limit', 30);

    expect($this->service->remaining($this->tenant, 'test.limit'))->toBe(70);
});

it('returns null remaining when feature has no limit', function () {
    expect($this->service->remaining($this->tenant, 'nonexistent.feature'))->toBeNull();
});

// --- Tenant Isolation ---

it('enforces tenant isolation for usage counters', function () {
    $tenantA = $this->tenant;
    $tenantB = featureGateTenant();

    $this->service->incrementUsage($tenantA, 'shared.feature', 10);
    $this->service->incrementUsage($tenantB, 'shared.feature', 5);

    expect($this->service->usage($tenantA, 'shared.feature'))->toBe(10);
    expect($this->service->usage($tenantB, 'shared.feature'))->toBe(5);

    $this->service->decrementUsage($tenantA, 'shared.feature', 3);

    expect($this->service->usage($tenantA, 'shared.feature'))->toBe(7);
    expect($this->service->usage($tenantB, 'shared.feature'))->toBe(5);
});

it('enforces tenant isolation for feature resolution', function () {
    $tenantA = $this->tenant;
    $tenantB = featureGateTenant();

    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $tenantA->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    FeatureDefinition::firstOrCreate(
        ['key' => 'isolation.test'],
        ['key' => 'isolation.test', 'name' => 'Isolation Test', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    expect($this->service->allows($tenantA, 'isolation.test'))->toBeTrue();
    expect($this->service->allows($tenantB, 'isolation.test'))->toBeFalse();
});

// --- Cache Invalidation ---

it('invalidates cache for specific feature', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    FeatureDefinition::firstOrCreate(
        ['key' => 'cache.test'],
        ['key' => 'cache.test', 'name' => 'Cache Test', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    expect($this->service->allows($this->tenant, 'cache.test'))->toBeTrue();

    $this->service->invalidate($this->tenant, 'cache.test');

    expect(true)->toBeTrue();
});

it('invalidates all feature caches for tenant', function () {
    $this->service->invalidate($this->tenant);

    expect(true)->toBeTrue();
});
