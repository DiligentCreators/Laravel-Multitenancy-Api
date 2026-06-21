<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Tag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function tagTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'tag-test.localhost']);

    return $tenant;
}

function tagUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTagPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "tags.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedTagPermissions();
    $this->tenant = tagTenant();
    $this->user = tagUser($this->tenant, ['tags.view', 'tags.create', 'tags.update', 'tags.delete']);
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

it('lists tags', function () {
    Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->getJson('/api/tenant/v1/crm/tags')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a tag', function () {
    $this->postJson('/api/tenant/v1/crm/tags', [
        'name' => 'Hot Lead',
        'color' => '#ef4444',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows a tag', function () {
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->getJson("/api/tenant/v1/crm/tags/{$tag->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a tag', function () {
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->putJson("/api/tenant/v1/crm/tags/{$tag->id}", ['name' => 'Very Important'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a tag', function () {
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->deleteJson("/api/tenant/v1/crm/tags/{$tag->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('merges two tags', function () {
    $source = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Source', 'color' => '#ef4444']);
    $target = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Target', 'color' => '#3b82f6']);

    $this->postJson('/api/tenant/v1/crm/tags/merge', [
        'source_id' => $source->id,
        'target_id' => $target->id,
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect(Tag::find($source->id))->toBeNull();
    expect(Tag::find($target->id))->not->toBeNull();
});

// --- Negative Tests ---

it('returns 401 when not authenticated for tags', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/tags')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for tags', function () {
    $guest = tagUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/tags')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for tags', function () {
    $guest = tagUser($this->tenant, ['tags.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/tags', ['name' => 'Test'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for tags', function () {
    $guest = tagUser($this->tenant, ['tags.view', 'tags.create']);
    $this->actingAs($guest, 'tenant-api');

    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->putJson("/api/tenant/v1/crm/tags/{$tag->id}", ['name' => 'Very Important'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for tags', function () {
    $guest = tagUser($this->tenant, ['tags.view', 'tags.create', 'tags.update']);
    $this->actingAs($guest, 'tenant-api');

    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->deleteJson("/api/tenant/v1/crm/tags/{$tag->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent tag', function () {
    $this->getJson('/api/tenant/v1/crm/tags/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating tag with missing name', function () {
    $this->postJson('/api/tenant/v1/crm/tags', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating tag with empty name', function () {
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'color' => '#f59e0b']);

    $this->putJson("/api/tenant/v1/crm/tags/{$tag->id}", ['name' => ''])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when merging with same source and target', function () {
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Test', 'color' => '#000']);

    $this->postJson('/api/tenant/v1/crm/tags/merge', [
        'source_id' => $tag->id,
        'target_id' => $tag->id,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
