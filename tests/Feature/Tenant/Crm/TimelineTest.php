<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Organization;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function timelineTenant(): Tenant
{
    $domain = 'timeline-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function timelineUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTimelinePermissions(): void
{
    Permission::firstOrCreate(['name' => 'timeline.view', 'guard_name' => 'tenant-api']);
}

beforeEach(function () {
    seedTimelinePermissions();
    $this->tenant = timelineTenant();
    $this->user = timelineUser($this->tenant, ['timeline.view']);
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

it('lists timeline entries', function () {
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Organization Created',
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/tenant/v1/crm/timeline')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a timeline entry', function () {
    $entry = TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Organization Created',
        'occurred_at' => now(),
    ]);

    $this->getJson("/api/tenant/v1/crm/timeline/{$entry->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters timeline by entity', function () {
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Created',
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/tenant/v1/crm/timeline?entity_type='.urlencode(Organization::class).'&entity_id='.$this->organization->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters timeline by event_type', function () {
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Created',
        'occurred_at' => now(),
    ]);
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.updated',
        'title' => 'Updated',
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/tenant/v1/crm/timeline?event_type=organization.created')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('searches timeline by title', function () {
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Organization Created',
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/tenant/v1/crm/timeline?search=Created')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists timeline by entity endpoint', function () {
    TimelineEntry::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Organization::class,
        'entity_id' => $this->organization->id,
        'event_type' => 'organization.created',
        'title' => 'Organization Created',
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/tenant/v1/crm/timeline/by-entity/'.urlencode(Organization::class).'/'.$this->organization->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures timeline tenant isolation', function () {
    $tenant2 = timelineTenant();
    tenancy()->initialize($tenant2);
    $org2 = Organization::create(['tenant_id' => $tenant2->id, 'name' => 'Other Corp']);
    $entry2 = TimelineEntry::create([
        'tenant_id' => $tenant2->id,
        'entity_type' => Organization::class,
        'entity_id' => $org2->id,
        'event_type' => 'organization.created',
        'title' => 'Other',
        'occurred_at' => now(),
    ]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/timeline/{$entry2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for timeline', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/timeline')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks timeline.view permission', function () {
    $guest = timelineUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/timeline')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent timeline entry', function () {
    $this->getJson('/api/tenant/v1/crm/timeline/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
