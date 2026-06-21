<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Activity;
use App\Models\Crm\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function activityTenant(): Tenant
{
    $domain = 'activity-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function activityUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedActivityPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "activities.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedActivityPermissions();
    $this->tenant = activityTenant();
    $this->user = activityUser($this->tenant, ['activities.view', 'activities.create', 'activities.update', 'activities.delete']);
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
    $this->organization = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates an activity', function () {
    $this->postJson('/api/tenant/v1/crm/activities', [
        'activityable_type' => Organization::class,
        'activityable_id' => $this->organization->id,
        'type' => 'call',
        'subject' => 'Discovery call',
        'description' => 'Initial call with client',
        'status' => 'completed',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists activities', function () {
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call 1']);
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'meeting', 'subject' => 'Meeting 1']);

    $this->getJson('/api/tenant/v1/crm/activities')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows an activity', function () {
    $activity = Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Discovery call']);

    $this->getJson("/api/tenant/v1/crm/activities/{$activity->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an activity', function () {
    $activity = Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Discovery call']);

    $this->putJson("/api/tenant/v1/crm/activities/{$activity->id}", [
        'subject' => 'Updated call',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes an activity', function () {
    $activity = Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Discovery call']);

    $this->deleteJson("/api/tenant/v1/crm/activities/{$activity->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters activities by type', function () {
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call']);
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'meeting', 'subject' => 'Meeting']);

    $this->getJson('/api/tenant/v1/crm/activities?type=call')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters activities by status', function () {
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call', 'status' => 'completed']);
    Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'meeting', 'subject' => 'Meeting', 'status' => 'pending']);

    $this->getJson('/api/tenant/v1/crm/activities?status=completed')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('restores a soft-deleted activity', function () {
    $activity = Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call']);
    $activity->delete();

    $this->postJson("/api/tenant/v1/crm/activities/{$activity->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures activity tenant isolation', function () {
    $tenant2 = activityTenant();
    tenancy()->initialize($tenant2);
    $org2 = Organization::create(['tenant_id' => $tenant2->id, 'name' => 'Other Corp']);
    $activity2 = Activity::create(['owner_id' => $this->user->id, 'activityable_type' => Organization::class, 'activityable_id' => $org2->id, 'type' => 'call', 'subject' => 'Other']);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/activities/{$activity2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for activities', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/activities')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for activities', function () {
    $guest = activityUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/activities')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent activity', function () {
    $this->getJson('/api/tenant/v1/crm/activities/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating activity without subject', function () {
    $this->postJson('/api/tenant/v1/crm/activities', [
        'activityable_type' => Organization::class,
        'activityable_id' => $this->organization->id,
        'type' => 'call',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating activity with invalid type', function () {
    $this->postJson('/api/tenant/v1/crm/activities', [
        'activityable_type' => Organization::class,
        'activityable_id' => $this->organization->id,
        'type' => 'invalid_type',
        'subject' => 'Test',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
