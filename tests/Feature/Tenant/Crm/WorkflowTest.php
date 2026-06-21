<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\WorkflowDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function workflowTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'workflow-test.localhost']);

    return $tenant;
}

function workflowUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedWorkflowPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "workflows.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedWorkflowPermissions();
    $this->tenant = workflowTenant();
    $this->user = workflowUser($this->tenant, ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete']);
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

it('lists workflow definitions', function () {
    WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Assign Owner on Lead Create',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->getJson('/api/tenant/v1/crm/workflows')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a workflow definition', function () {
    $this->postJson('/api/tenant/v1/crm/workflows', [
        'name' => 'Notify on Lead Create',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [
            ['type' => 'send_notification', 'title' => 'New Lead', 'body' => 'A new lead was created', 'user_ids' => [1]],
        ],
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('shows a workflow', function () {
    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->getJson("/api/tenant/v1/crm/workflows/{$workflow->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a workflow', function () {
    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->putJson("/api/tenant/v1/crm/workflows/{$workflow->id}", [
        'name' => 'Updated Workflow',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists workflow logs', function () {
    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->getJson("/api/tenant/v1/crm/workflows/{$workflow->id}/logs")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a workflow', function () {
    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->deleteJson("/api/tenant/v1/crm/workflows/{$workflow->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for workflows', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/workflows')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for workflows', function () {
    $guest = workflowUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/workflows')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for workflows', function () {
    $guest = workflowUser($this->tenant, ['workflows.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/workflows', [
        'name' => 'Test', 'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for workflows', function () {
    $guest = workflowUser($this->tenant, ['workflows.view', 'workflows.create']);
    $this->actingAs($guest, 'tenant-api');

    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->putJson("/api/tenant/v1/crm/workflows/{$workflow->id}", [
        'name' => 'Updated Workflow',
    ])->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for workflows', function () {
    $guest = workflowUser($this->tenant, ['workflows.view', 'workflows.create', 'workflows.update']);
    $this->actingAs($guest, 'tenant-api');

    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->deleteJson("/api/tenant/v1/crm/workflows/{$workflow->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent workflow', function () {
    $this->getJson('/api/tenant/v1/crm/workflows/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating workflow with missing name', function () {
    $this->postJson('/api/tenant/v1/crm/workflows', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating workflow with invalid action type', function () {
    $this->postJson('/api/tenant/v1/crm/workflows', [
        'name' => 'Test', 'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'invalid_action']],
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when updating workflow with empty name', function () {
    $workflow = WorkflowDefinition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workflow',
        'entity_type' => 'leads',
        'trigger_event' => 'lead.created',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
    ]);

    $this->putJson("/api/tenant/v1/crm/workflows/{$workflow->id}", [
        'name' => '',
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
