<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function statusTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'status-test.localhost']);

    return $tenant;
}

function statusUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedStatusPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "statuses.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedStatusPermissions();
    $this->tenant = statusTenant();
    $this->user = statusUser($this->tenant, ['statuses.view', 'statuses.create', 'statuses.update', 'statuses.delete']);
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

// --- Status Types ---

it('lists status types', function () {
    StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'person', 'name' => 'Person Statuses', 'key' => 'person_statuses',
    ]);

    $this->getJson('/api/tenant/v1/crm/status-types')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a status type', function () {
    $this->postJson('/api/tenant/v1/crm/status-types', [
        'entity_type' => 'lead',
        'name' => 'Lead Statuses',
        'key' => 'lead_statuses',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows a status type', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->getJson("/api/tenant/v1/crm/status-types/{$type->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a status type', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->putJson("/api/tenant/v1/crm/status-types/{$type->id}", ['name' => 'Updated'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a status type', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/status-types/{$type->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Statuses ---

it('creates a status', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->postJson('/api/tenant/v1/crm/statuses', [
        'type_id' => $type->id,
        'name' => 'New Lead',
        'key' => 'new_lead',
        'color' => '#6366f1',
        'is_default' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists statuses', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->getJson('/api/tenant/v1/crm/statuses')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters statuses by entity type', function () {
    $leadType = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $taskType = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'task', 'name' => 'Task Statuses', 'key' => 'task_statuses',
    ]);

    Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $leadType->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);
    Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $taskType->id, 'name' => 'Open', 'key' => 'open', 'color' => '#6366f1', 'order' => 1]);

    $this->getJson('/api/tenant/v1/crm/statuses?entity_type=lead')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a single status', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->getJson("/api/tenant/v1/crm/statuses/{$status->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a status', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->putJson("/api/tenant/v1/crm/statuses/{$status->id}", ['name' => 'Qualified'])
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a status', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->deleteJson("/api/tenant/v1/crm/statuses/{$status->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for statuses', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/statuses')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Unauthenticated.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 401 when not authenticated for status types', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/status-types')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Unauthenticated.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for statuses', function () {
    $guest = statusUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/statuses')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for statuses', function () {
    $guest = statusUser($this->tenant, ['statuses.view']);
    $this->actingAs($guest, 'tenant-api');
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->postJson('/api/tenant/v1/crm/statuses', [
        'type_id' => $type->id, 'name' => 'Test', 'key' => 'test',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for statuses', function () {
    $guest = statusUser($this->tenant, ['statuses.view', 'statuses.create']);
    $this->actingAs($guest, 'tenant-api');
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->putJson("/api/tenant/v1/crm/statuses/{$status->id}", ['name' => 'Updated'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for statuses', function () {
    $guest = statusUser($this->tenant, ['statuses.view', 'statuses.create', 'statuses.update']);
    $this->actingAs($guest, 'tenant-api');
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->deleteJson("/api/tenant/v1/crm/statuses/{$status->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for status types', function () {
    $guest = statusUser($this->tenant, ['statuses.view', 'statuses.create']);
    $this->actingAs($guest, 'tenant-api');
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->putJson("/api/tenant/v1/crm/status-types/{$type->id}", ['name' => 'Updated'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for status types', function () {
    $guest = statusUser($this->tenant, ['statuses.view', 'statuses.create', 'statuses.update']);
    $this->actingAs($guest, 'tenant-api');
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->deleteJson("/api/tenant/v1/crm/status-types/{$type->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJson(['message' => 'Access denied.'])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent status', function () {
    $this->getJson('/api/tenant/v1/crm/statuses/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent status type', function () {
    $this->getJson('/api/tenant/v1/crm/status-types/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating status with missing required fields', function () {
    $this->postJson('/api/tenant/v1/crm/statuses', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating status type with missing entity_type', function () {
    $this->postJson('/api/tenant/v1/crm/status-types', ['name' => 'Test'])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating status with empty name', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    $status = Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->putJson("/api/tenant/v1/crm/statuses/{$status->id}", ['name' => ''])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating status type with empty name', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);

    $this->putJson("/api/tenant/v1/crm/status-types/{$type->id}", ['name' => ''])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns statuses by entity type', function () {
    $type = StatusType::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses',
    ]);
    Status::create(['tenant_id' => $this->tenant->id, 'type_id' => $type->id, 'name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1]);

    $this->getJson('/api/tenant/v1/crm/statuses/by-entity/lead')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});
