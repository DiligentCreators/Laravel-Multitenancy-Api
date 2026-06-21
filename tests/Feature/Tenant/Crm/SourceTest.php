<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Source;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function sourceTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'source-test.localhost']);

    return $tenant;
}

function sourceUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedSourcePermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "sources.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedSourcePermissions();
    $this->tenant = sourceTenant();
    $this->user = sourceUser($this->tenant, ['sources.view', 'sources.create', 'sources.update', 'sources.delete']);
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

it('lists sources', function () {
    Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->getJson('/api/tenant/v1/crm/sources')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a source', function () {
    $this->postJson('/api/tenant/v1/crm/sources', [
        'name' => 'Facebook',
        'category' => 'social',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows a source', function () {
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->getJson("/api/tenant/v1/crm/sources/{$source->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a source', function () {
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->putJson("/api/tenant/v1/crm/sources/{$source->id}", ['name' => 'Web'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a source', function () {
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->deleteJson("/api/tenant/v1/crm/sources/{$source->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for sources', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/sources')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 403 when user lacks view permission for sources', function () {
    $guest = sourceUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/sources')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks create permission for sources', function () {
    $guest = sourceUser($this->tenant, ['sources.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/sources', ['name' => 'Test'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 404 for non-existent source', function () {
    $this->getJson('/api/tenant/v1/crm/sources/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating source with missing name', function () {
    $this->postJson('/api/tenant/v1/crm/sources', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating source with duplicate name', function () {
    Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website']);

    $this->postJson('/api/tenant/v1/crm/sources', ['name' => 'Website'])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for sources', function () {
    $guest = sourceUser($this->tenant, ['sources.view', 'sources.create']);
    $this->actingAs($guest, 'tenant-api');

    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->putJson("/api/tenant/v1/crm/sources/{$source->id}", ['name' => 'Renamed'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks delete permission for sources', function () {
    $guest = sourceUser($this->tenant, ['sources.view', 'sources.create', 'sources.update']);
    $this->actingAs($guest, 'tenant-api');

    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->deleteJson("/api/tenant/v1/crm/sources/{$source->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 422 when updating source with empty name', function () {
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);

    $this->putJson("/api/tenant/v1/crm/sources/{$source->id}", ['name' => ''])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
