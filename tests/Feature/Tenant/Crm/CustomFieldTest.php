<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\CustomFieldDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\CustomFieldService;
use Spatie\Permission\Models\Permission;

function cfTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'cf-test.localhost']);

    return $tenant;
}

function cfUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedCfPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "custom-fields.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedCfPermissions();
    $this->tenant = cfTenant();
    $this->user = cfUser($this->tenant, ['custom-fields.view', 'custom-fields.create', 'custom-fields.update', 'custom-fields.delete']);
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

it('lists custom field definitions', function () {
    CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->getJson('/api/tenant/v1/crm/custom-fields')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters custom fields by entity type', function () {
    CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);
    CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Budget', 'key' => 'budget', 'type' => 'number',
    ]);

    $this->getJson('/api/tenant/v1/crm/custom-fields?entity_type=person')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a custom field definition', function () {
    $this->postJson('/api/tenant/v1/crm/custom-fields', [
        'entity_type' => 'person',
        'name' => 'Source',
        'type' => 'text',
        'is_required' => false,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('creates a select custom field', function () {
    $this->postJson('/api/tenant/v1/crm/custom-fields', [
        'entity_type' => 'lead',
        'name' => 'Industry',
        'type' => 'select',
        'options' => ['Technology', 'Finance', 'Healthcare', 'Education'],
        'is_required' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows a custom field', function () {
    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->getJson("/api/tenant/v1/crm/custom-fields/{$field->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a custom field', function () {
    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->putJson("/api/tenant/v1/crm/custom-fields/{$field->id}", [
        'name' => 'Lead Source',
        'is_required' => true,
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a custom field', function () {
    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/custom-fields/{$field->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('validates custom field values correctly', function () {
    CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source',
        'type' => 'text', 'is_required' => true,
    ]);

    $service = app(CustomFieldService::class);
    $result = $service->validateValues('person', []);

    expect($result['errors'])->toHaveKey('source');
});

// --- Negative Tests ---

it('returns 401 when not authenticated for custom fields', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/custom-fields')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for custom fields', function () {
    $guest = cfUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/custom-fields')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for custom fields', function () {
    $guest = cfUser($this->tenant, ['custom-fields.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/custom-fields', [
        'entity_type' => 'person', 'name' => 'Test', 'type' => 'text',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for custom fields', function () {
    $guest = cfUser($this->tenant, ['custom-fields.view', 'custom-fields.create']);
    $this->actingAs($guest, 'tenant-api');

    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->putJson("/api/tenant/v1/crm/custom-fields/{$field->id}", [
        'name' => 'Lead Source',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for custom fields', function () {
    $guest = cfUser($this->tenant, ['custom-fields.view', 'custom-fields.create', 'custom-fields.update']);
    $this->actingAs($guest, 'tenant-api');

    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/custom-fields/{$field->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent custom field', function () {
    $this->getJson('/api/tenant/v1/crm/custom-fields/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating custom field with missing required fields', function () {
    $this->postJson('/api/tenant/v1/crm/custom-fields', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating custom field with invalid type', function () {
    $this->postJson('/api/tenant/v1/crm/custom-fields', [
        'entity_type' => 'person', 'name' => 'Test', 'type' => 'invalid_type',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating custom field with invalid data', function () {
    $field = CustomFieldDefinition::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Source', 'key' => 'source', 'type' => 'text',
    ]);

    $this->putJson("/api/tenant/v1/crm/custom-fields/{$field->id}", [
        'entity_type' => '',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
