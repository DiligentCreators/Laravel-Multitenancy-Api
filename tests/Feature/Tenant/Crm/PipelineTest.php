<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Pipeline;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function pipelineTenant(): Tenant
{
    $domain = 'pipeline-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function pipelineUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedPipelinePermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "pipelines.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedPipelinePermissions();
    $this->tenant = pipelineTenant();
    $this->user = pipelineUser($this->tenant, ['pipelines.view', 'pipelines.create', 'pipelines.update', 'pipelines.delete']);
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

it('creates a pipeline', function () {
    $this->postJson('/api/tenant/v1/crm/pipelines', [
        'name' => 'Sales Pipeline',
        'description' => 'Default sales process',
        'is_active' => true,
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists pipelines', function () {
    Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);
    Pipeline::create(['name' => 'Support Pipeline', 'sort_order' => 2]);

    $this->getJson('/api/tenant/v1/crm/pipelines')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a pipeline', function () {
    $pipeline = Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);

    $this->getJson("/api/tenant/v1/crm/pipelines/{$pipeline->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a pipeline', function () {
    $pipeline = Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);

    $this->putJson("/api/tenant/v1/crm/pipelines/{$pipeline->id}", [
        'name' => 'Updated Pipeline',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a pipeline', function () {
    $pipeline = Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);

    $this->deleteJson("/api/tenant/v1/crm/pipelines/{$pipeline->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters pipelines by search', function () {
    Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);
    Pipeline::create(['name' => 'Support Pipeline', 'sort_order' => 2]);

    $this->getJson('/api/tenant/v1/crm/pipelines?search=Sales')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters pipelines by is_active', function () {
    Pipeline::create(['name' => 'Sales Pipeline', 'is_active' => true, 'sort_order' => 1]);
    Pipeline::create(['name' => 'Archived Pipeline', 'is_active' => false, 'sort_order' => 2]);

    $this->getJson('/api/tenant/v1/crm/pipelines?is_active=1')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures pipeline tenant isolation', function () {
    $tenant2 = pipelineTenant();
    tenancy()->initialize($tenant2);
    $pipeline2 = Pipeline::create(['name' => 'Other Tenant Pipeline', 'sort_order' => 1]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/pipelines/{$pipeline2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for pipelines', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/pipelines')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for pipelines', function () {
    $guest = pipelineUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/pipelines')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent pipeline', function () {
    $this->getJson('/api/tenant/v1/crm/pipelines/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating pipeline without name', function () {
    $this->postJson('/api/tenant/v1/crm/pipelines', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
