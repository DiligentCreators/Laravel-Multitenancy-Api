<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\PortalPersonLink;
use App\Models\Crm\PortalUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function seedPortalLinkPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "portal-users.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPortalLinkPermissions();

    FeatureDefinition::create(['key' => 'portal.enabled', 'name' => 'Portal Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);

    $domain = 'portal-link-'.uniqid().'.localhost';
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

    $this->portalUser = PortalUser::factory()->create();
    $this->person = Person::create(['first_name' => 'Test', 'last_name' => 'Person']);
    $this->organization = Organization::create(['name' => 'Test Org']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('creates a portal person link', function () {
    $response = $this->postJson('/api/tenant/v1/crm/portal-person-links', [
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('portal_person_links', [
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);
});

it('creates a portal organization link', function () {
    $response = $this->postJson('/api/tenant/v1/crm/portal-person-links', [
        'portal_user_id' => $this->portalUser->id,
        'organization_id' => $this->organization->id,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('portal_person_links', [
        'portal_user_id' => $this->portalUser->id,
        'organization_id' => $this->organization->id,
    ]);
});

it('lists portal person links', function () {
    PortalPersonLink::factory()->create([
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);

    $response = $this->getJson('/api/tenant/v1/crm/portal-person-links');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('deletes a portal person link', function () {
    $link = PortalPersonLink::factory()->create([
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);

    $response = $this->deleteJson("/api/tenant/v1/crm/portal-person-links/{$link->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('portal_person_links', ['id' => $link->id]);
});

it('prevents cross-tenant portal person link access', function () {
    $link = PortalPersonLink::factory()->create([
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherTenant->domains()->create(['domain' => 'other-'.uniqid().'.localhost']);
    tenancy()->end();
    tenancy()->initialize($otherTenant);

    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUser->givePermissionTo('portal-users.delete');
    $this->actingAs($otherUser, 'tenant-api');

    $response = $this->deleteJson("/api/tenant/v1/crm/portal-person-links/{$link->id}");

    $response->assertNotFound();
});
