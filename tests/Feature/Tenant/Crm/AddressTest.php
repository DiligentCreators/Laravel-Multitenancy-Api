<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Address;
use App\Models\Crm\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function addressTenant(): Tenant
{
    $domain = 'address-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function addressUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedAddressPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "addresses.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedAddressPermissions();
    $this->tenant = addressTenant();
    $this->user = addressUser($this->tenant, ['addresses.view', 'addresses.create', 'addresses.update', 'addresses.delete']);
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
    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Corp',
    ]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates an address', function () {
    $this->postJson('/api/tenant/v1/crm/addresses', [
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'country' => 'US',
        'postal_code' => '10001',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists addresses', function () {
    Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->getJson('/api/tenant/v1/crm/addresses')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows an address', function () {
    $address = Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->getJson("/api/tenant/v1/crm/addresses/{$address->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an address', function () {
    $address = Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->putJson("/api/tenant/v1/crm/addresses/{$address->id}", [
        'address_line_1' => '456 Oak Ave',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes an address', function () {
    $address = Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/addresses/{$address->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists addresses by entity', function () {
    Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $entityType = urlencode(Organization::class);
    $this->getJson("/api/tenant/v1/crm/addresses/by-entity/{$entityType}/{$this->organization->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates multiple address types for same entity', function () {
    $this->postJson('/api/tenant/v1/crm/addresses', [
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
        'city' => 'New York',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->postJson('/api/tenant/v1/crm/addresses', [
        'type' => 'shipping',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '456 Oak Ave',
        'city' => 'Los Angeles',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);

    $addresses = Address::withoutGlobalScopes()
        ->where('addressable_type', Organization::class)
        ->where('addressable_id', $this->organization->id)
        ->get();

    expect($addresses)->toHaveCount(2);
    expect($addresses->pluck('type')->toArray())->toEqualCanonicalizing(['billing', 'shipping']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for addresses', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/addresses')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for addresses', function () {
    $guest = addressUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/addresses')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for addresses', function () {
    $guest = addressUser($this->tenant, ['addresses.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/addresses', [
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for addresses', function () {
    $guest = addressUser($this->tenant, ['addresses.view', 'addresses.create']);
    $this->actingAs($guest, 'tenant-api');

    $address = Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->putJson("/api/tenant/v1/crm/addresses/{$address->id}", [
        'address_line_1' => '456 Oak Ave',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for addresses', function () {
    $guest = addressUser($this->tenant, ['addresses.view', 'addresses.create', 'addresses.update']);
    $this->actingAs($guest, 'tenant-api');

    $address = Address::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'billing',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/addresses/{$address->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent address', function () {
    $this->getJson('/api/tenant/v1/crm/addresses/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating address with missing address_line_1', function () {
    $this->postJson('/api/tenant/v1/crm/addresses', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating address with invalid type', function () {
    $this->postJson('/api/tenant/v1/crm/addresses', [
        'type' => 'invalid',
        'addressable_type' => Organization::class,
        'addressable_id' => $this->organization->id,
        'address_line_1' => '123 Main St',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
