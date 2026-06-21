<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Person;
use App\Models\Crm\Source;
use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use App\Models\Crm\Tag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Permission;

function personTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'person-test-'.uniqid().'.localhost']);

    return $tenant;
}

function personUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedPersonPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "people.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedPersonPermissions();
    $this->tenant = personTenant();
    $this->user = personUser($this->tenant, ['people.view', 'people.create', 'people.update', 'people.delete']);
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

it('lists people', function () {
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);

    $this->getJson('/api/tenant/v1/crm/people')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a person', function () {
    $this->postJson('/api/tenant/v1/crm/people', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('creates a person with tags', function () {
    $tagA = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP']);
    $tagB = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'Hot Lead']);

    $this->postJson('/api/tenant/v1/crm/people', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tag_ids' => [$tagA->id, $tagB->id],
    ])->assertCreated()
        ->assertJson(['status' => 'success']);

    expect(Person::first()->tags()->count())->toBe(2);
});

it('shows a person', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->getJson("/api/tenant/v1/crm/people/{$person->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a person with relationships', function () {
    $type = StatusType::create(['tenant_id' => $this->tenant->id, 'entity_type' => 'people', 'name' => 'Lead Status', 'key' => 'lead_status']);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new']);
    $source = Source::create(['tenant_id' => $this->tenant->id, 'name' => 'Website', 'category' => 'website']);
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP']);

    $person = Person::create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'status_id' => $status->id,
        'source_id' => $source->id,
    ]);
    $person->tags()->sync([$tag->id]);

    $this->getJson("/api/tenant/v1/crm/people/{$person->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJsonStructure(['status', 'data' => ['id', 'first_name', 'last_name', 'status', 'source', 'tags']]);
});

it('updates a person', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->putJson("/api/tenant/v1/crm/people/{$person->id}", ['first_name' => 'Alicia'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a person (soft delete)', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->deleteJson("/api/tenant/v1/crm/people/{$person->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect(Person::withTrashed()->find($person->id)->trashed())->toBeTrue();
});

it('restores a soft-deleted person', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);
    $person->delete();

    $this->postJson("/api/tenant/v1/crm/people/{$person->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect(Person::find($person->id))->not->toBeNull();
});

it('searches people by name', function () {
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);

    $this->getJson('/api/tenant/v1/crm/people?search=Alice')
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJson(function (AssertableJson $json) {
            $json->has('data', 1)->etc();
        });
});

it('filters people by status', function () {
    $type = StatusType::create(['tenant_id' => $this->tenant->id, 'entity_type' => 'people', 'name' => 'Lead Status', 'key' => 'lead_status']);
    $statusA = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new']);
    $statusB = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'Contacted', 'key' => 'contacted']);

    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith', 'status_id' => $statusA->id]);
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Jones', 'status_id' => $statusB->id]);

    $this->getJson("/api/tenant/v1/crm/people?status_id={$statusA->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJson(function (AssertableJson $json) {
            $json->has('data', 1)->etc();
        });
});

it('sorts people by name', function () {
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $response = $this->getJson('/api/tenant/v1/crm/people?sort_by=first_name&sort_order=asc')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect($response->json('data')[0]['first_name'])->toBe('Alice');
    expect($response->json('data')[1]['first_name'])->toBe('Bob');
});

// --- Negative Tests ---

it('returns 401 when not authenticated', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/people')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 403 when user lacks view permission', function () {
    $guest = personUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/people')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks create permission', function () {
    $guest = personUser($this->tenant, ['people.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/people', [
        'first_name' => 'Test',
        'last_name' => 'User',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks update permission', function () {
    $guest = personUser($this->tenant, ['people.view', 'people.create']);
    $this->actingAs($guest, 'tenant-api');

    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->putJson("/api/tenant/v1/crm/people/{$person->id}", ['first_name' => 'Alicia'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 403 when user lacks delete permission', function () {
    $guest = personUser($this->tenant, ['people.view', 'people.create', 'people.update']);
    $this->actingAs($guest, 'tenant-api');

    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->deleteJson("/api/tenant/v1/crm/people/{$person->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors'])
        ->assertJson(['message' => 'Access denied.']);
});

it('returns 404 for non-existent person', function () {
    $this->getJson('/api/tenant/v1/crm/people/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating with missing first_name', function () {
    $this->postJson('/api/tenant/v1/crm/people', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating with invalid email', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $this->putJson("/api/tenant/v1/crm/people/{$person->id}", ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

// --- Tenant Isolation ---

it('enforces tenant isolation', function () {
    Person::create(['owner_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

    $otherTenant = personTenant();
    $otherUser = personUser($otherTenant, ['people.view', 'people.create', 'people.update', 'people.delete']);
    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::create([
        'tenant_id' => $otherTenant->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);
    $this->actingAs($otherUser, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/people')
        ->assertSuccessful()
        ->assertJson(['status' => 'success'])
        ->assertJson(function (AssertableJson $json) {
            $json->has('data', 0)->etc();
        });
});
