<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Organization;
use App\Models\Crm\OrganizationPerson;
use App\Models\Crm\Person;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function orgPersonTenant(): Tenant
{
    $domain = 'org-person-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function orgPersonUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedOrgPersonPermissions(): void
{
    Permission::firstOrCreate(['name' => 'organization-people.manage', 'guard_name' => 'tenant-api']);
}

beforeEach(function () {
    seedOrgPersonPermissions();
    $this->tenant = orgPersonTenant();
    $this->user = orgPersonUser($this->tenant, ['organization-people.manage']);
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
    $this->person = Person::create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
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

it('creates an organization-person relationship', function () {
    $this->postJson('/api/tenant/v1/crm/organization-people', [
        'organization_id' => $this->organization->id,
        'person_id' => $this->person->id,
        'role' => 'manager',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists organization-person relationships', function () {
    OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);
    $person2 = Person::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Jane', 'last_name' => 'Smith']);
    OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $person2->id, 'role' => 'employee']);

    $this->getJson('/api/tenant/v1/crm/organization-people')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows an organization-person relationship', function () {
    $op = OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);

    $this->getJson("/api/tenant/v1/crm/organization-people/{$op->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an organization-person relationship', function () {
    $op = OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);

    $this->putJson("/api/tenant/v1/crm/organization-people/{$op->id}", ['role' => 'director'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes an organization-person relationship', function () {
    $op = OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);

    $this->deleteJson("/api/tenant/v1/crm/organization-people/{$op->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists people by organization', function () {
    OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);

    $this->getJson('/api/tenant/v1/crm/organization-people/by-organization/'.$this->organization->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists organizations by person', function () {
    OrganizationPerson::create(['organization_id' => $this->organization->id, 'person_id' => $this->person->id, 'role' => 'manager']);

    $this->getJson('/api/tenant/v1/crm/organization-people/by-person/'.$this->person->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for organization people', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/organization-people')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks manage permission for organization people', function () {
    $guest = orgPersonUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/organization-people')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent organization-person relationship', function () {
    $this->getJson('/api/tenant/v1/crm/organization-people/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating organization-person with missing organization_id', function () {
    $this->postJson('/api/tenant/v1/crm/organization-people', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating organization-person with non-existent person_id', function () {
    $this->postJson('/api/tenant/v1/crm/organization-people', [
        'organization_id' => $this->organization->id,
        'person_id' => 99999,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
