<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\PortalUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function seedPortalUserPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "portal-users.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPortalUserPermissions();

    FeatureDefinition::create(['key' => 'portal.enabled', 'name' => 'Portal Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);

    $domain = 'portal-admin-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);

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

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->givePermissionTo(['portal-users.view', 'portal-users.create', 'portal-users.update', 'portal-users.delete']);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('lists portal users', function () {
    PortalUser::factory()->count(3)->create();

    $response = $this->getJson('/api/tenant/v1/crm/portal-users');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('creates a portal user', function () {
    $response = $this->postJson('/api/tenant/v1/crm/portal-users', [
        'name' => 'Test Portal User',
        'email' => 'portal@example.com',
    ]);

    $response->assertCreated();
    expect($response->json('data.email'))->toBe('portal@example.com');
});

it('shows a portal user', function () {
    $portalUser = PortalUser::factory()->create();

    $response = $this->getJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}");

    $response->assertOk();
});

it('updates a portal user', function () {
    $portalUser = PortalUser::factory()->create();

    $response = $this->putJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertOk();
    expect($response->json('data.name'))->toBe('Updated Name');
});

it('deletes a portal user', function () {
    $portalUser = PortalUser::factory()->create();

    $response = $this->deleteJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}");

    $response->assertOk();
    $this->assertSoftDeleted($portalUser);
});

it('activates a portal user', function () {
    $portalUser = PortalUser::factory()->inactive()->create();

    $response = $this->postJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}/activate");

    $response->assertOk();
    $this->assertDatabaseHas('portal_users', ['id' => $portalUser->id, 'is_active' => true]);
});

it('deactivates a portal user', function () {
    $portalUser = PortalUser::factory()->create();

    $response = $this->postJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}/deactivate");

    $response->assertOk();
    $this->assertDatabaseHas('portal_users', ['id' => $portalUser->id, 'is_active' => false]);
});

it('prevents cross-tenant portal user access', function () {
    $portalUser = PortalUser::factory()->create();

    $otherTenant = Tenant::factory()->create();
    $otherTenant->domains()->create(['domain' => 'other-'.uniqid().'.localhost']);
    tenancy()->end();
    tenancy()->initialize($otherTenant);

    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUser->givePermissionTo('portal-users.view');
    $this->actingAs($otherUser, 'tenant-api');

    $response = $this->getJson("/api/tenant/v1/crm/portal-users/{$portalUser->id}");

    $response->assertNotFound();
});

it('requires permission to manage portal users', function () {
    $userWithoutPerms = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->actingAs($userWithoutPerms, 'tenant-api')
        ->getJson('/api/tenant/v1/crm/portal-users');

    $response->assertForbidden();
});
