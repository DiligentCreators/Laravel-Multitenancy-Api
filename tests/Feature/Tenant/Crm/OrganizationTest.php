<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Organization;
use App\Models\Crm\Source;
use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use App\Models\Crm\Tag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function orgTenant(): Tenant
{
    $domain = 'org-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function orgUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedOrgPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "organizations.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedOrgPermissions();
    $this->tenant = orgTenant();
    $this->user = orgUser($this->tenant, ['organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete']);
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

it('lists organizations', function () {
    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);
    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Globex Inc']);

    $this->getJson('/api/tenant/v1/crm/organizations')
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJsonCount(2, 'data');
});

it('creates an organization', function () {
    $this->postJson('/api/tenant/v1/crm/organizations', [
        'name' => 'New Org',
        'website' => 'https://example.com',
        'email' => 'contact@example.com',
        'phone' => '+1234567890',
    ])->assertCreated()
        ->assertJson(['status' => 'success'])
        ->assertJsonStructure(['data' => ['id', 'name', 'website', 'email', 'phone']]);
});

it('creates an organization with tags', function () {
    $tag1 = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP']);
    $tag2 = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Enterprise']);

    $this->postJson('/api/tenant/v1/crm/organizations', [
        'name' => 'Tagged Org',
        'tag_ids' => [$tag1->id, $tag2->id],
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows an organization', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Visible Org']);

    $this->getJson("/api/tenant/v1/crm/organizations/{$org->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows an organization with relationships', function () {
    $statusType = StatusType::create(['tenant_id' => $this->tenant->id, 'name' => 'Org Status', 'entity_type' => 'organization', 'key' => 'org_status']);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $statusType->id, 'name' => 'Active', 'key' => 'active']);
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Referral', 'category' => 'referral']);
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Important']);
    $org = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Rich Org',
        'status_id' => $status->id,
        'source_id' => $source->id,
    ]);
    $org->tags()->attach($tag->id);

    $this->getJson("/api/tenant/v1/crm/organizations/{$org->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJsonStructure([
            'data' => [
                'status' => ['id', 'name'],
                'source' => ['id', 'name'],
                'tags',
            ],
        ]);
});

it('updates an organization', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Old Name']);

    $this->putJson("/api/tenant/v1/crm/organizations/{$org->id}", [
        'name' => 'Updated Name',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes an organization (soft delete)', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Delete Me']);

    $this->deleteJson("/api/tenant/v1/crm/organizations/{$org->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $this->assertSoftDeleted($org);
});

it('restores a soft-deleted organization', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Restore Me']);
    $org->delete();

    $this->postJson("/api/tenant/v1/crm/organizations/{$org->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $this->assertNotSoftDeleted($org);
});

it('searches organizations by name', function () {
    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Alpha Corp']);
    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Beta Inc']);

    $this->getJson('/api/tenant/v1/crm/organizations?search=Alpha')
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJsonCount(1, 'data');
});

it('filters organizations by source', function () {
    $sourceA = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Web', 'category' => 'website']);
    $sourceB = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Email', 'category' => 'email']);

    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'From Web', 'source_id' => $sourceA->id]);
    Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'From Email', 'source_id' => $sourceB->id]);

    $this->getJson('/api/tenant/v1/crm/organizations?source_id='.$sourceA->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJsonCount(1, 'data');
});

// --- Negative Tests ---

it('returns 401 when not authenticated', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/organizations')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 403 when user lacks view permission', function () {
    $guest = orgUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/organizations')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks create permission', function () {
    $guest = orgUser($this->tenant, ['organizations.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/organizations', ['name' => 'Test'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks update permission', function () {
    $guest = orgUser($this->tenant, ['organizations.view', 'organizations.create']);
    $this->actingAs($guest, 'tenant-api');

    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Untouchable']);

    $this->putJson("/api/tenant/v1/crm/organizations/{$org->id}", ['name' => 'Hacked'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks delete permission', function () {
    $guest = orgUser($this->tenant, ['organizations.view', 'organizations.create', 'organizations.update']);
    $this->actingAs($guest, 'tenant-api');

    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Cannot Delete']);

    $this->deleteJson("/api/tenant/v1/crm/organizations/{$org->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 404 for non-existent organization', function () {
    $this->getJson('/api/tenant/v1/crm/organizations/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating with missing name', function () {
    $this->postJson('/api/tenant/v1/crm/organizations', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating with invalid website', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Valid Name']);

    $this->putJson("/api/tenant/v1/crm/organizations/{$org->id}", [
        'website' => 'not-a-valid-url',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

// --- Tenant Isolation ---

it('enforces tenant isolation for organizations', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'name' => 'Tenant A Org']);

    tenancy()->end();

    $tenantB = orgTenant();
    $userB = orgUser($tenantB, ['organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete']);

    tenancy()->initialize($tenantB);
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::create([
        'tenant_id' => $tenantB->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);
    $this->actingAs($userB, 'tenant-api');

    $this->getJson("/api/tenant/v1/crm/organizations/{$org->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
