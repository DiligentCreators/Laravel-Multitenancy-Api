<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function featureDefinitionTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'feature-definition-test-'.uniqid().'.localhost']);

    return $tenant;
}

function featureDefinitionUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedFeatureDefinitionPermissions(): void
{
    Permission::firstOrCreate(['name' => 'features.view', 'guard_name' => 'tenant-api']);
}

beforeEach(function () {
    seedFeatureDefinitionPermissions();
    $this->tenant = featureDefinitionTenant();
    $this->user = featureDefinitionUser($this->tenant, ['features.view']);
    tenancy()->initialize($this->tenant);
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('lists feature definitions', function () {
    FeatureDefinition::firstOrCreate(
        ['key' => 'people.create'],
        ['key' => 'people.create', 'name' => 'Create People', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    $this->getJson('/api/tenant/v1/crm/features')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a feature definition', function () {
    $feature = FeatureDefinition::firstOrCreate(
        ['key' => 'people.create'],
        ['key' => 'people.create', 'name' => 'Create People', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]
    );

    $this->getJson("/api/tenant/v1/crm/features/{$feature->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for features', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/features')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for features', function () {
    $guest = featureDefinitionUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/features')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent feature definition', function () {
    $this->getJson('/api/tenant/v1/crm/features/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
