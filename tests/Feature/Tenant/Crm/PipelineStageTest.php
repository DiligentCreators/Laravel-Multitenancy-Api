<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function pipelineStageTenant(): Tenant
{
    $domain = 'pipeline-stage-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function pipelineStageUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedPipelineStagePermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "pipeline-stages.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedPipelineStagePermissions();
    $this->tenant = pipelineStageTenant();
    $this->user = pipelineStageUser($this->tenant, ['pipeline-stages.view', 'pipeline-stages.create', 'pipeline-stages.update', 'pipeline-stages.delete']);
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
    $this->pipeline = Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates a pipeline stage', function () {
    $this->postJson('/api/tenant/v1/crm/pipeline-stages', [
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Qualified',
        'sort_order' => 2,
        'probability' => 50,
        'color' => '#00ff00',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists pipeline stages', function () {
    PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);
    PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Qualified', 'sort_order' => 2]);

    $this->getJson('/api/tenant/v1/crm/pipeline-stages')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a pipeline stage', function () {
    $stage = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);

    $this->getJson("/api/tenant/v1/crm/pipeline-stages/{$stage->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a pipeline stage', function () {
    $stage = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);

    $this->putJson("/api/tenant/v1/crm/pipeline-stages/{$stage->id}", [
        'name' => 'Updated Stage',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a pipeline stage', function () {
    $stage = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);

    $this->deleteJson("/api/tenant/v1/crm/pipeline-stages/{$stage->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('lists stages by pipeline', function () {
    PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);
    PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Qualified', 'sort_order' => 2]);

    $this->getJson("/api/tenant/v1/crm/pipeline-stages/by-pipeline/{$this->pipeline->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('reorders pipeline stages', function () {
    $stage1 = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);
    $stage2 = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Qualified', 'sort_order' => 2]);

    $this->postJson('/api/tenant/v1/crm/pipeline-stages/reorder', [
        'order' => [$stage2->id, $stage1->id],
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('filters stages by pipeline_id', function () {
    $pipeline2 = Pipeline::create(['name' => 'Support Pipeline', 'sort_order' => 2]);
    PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1]);
    PipelineStage::create(['pipeline_id' => $pipeline2->id, 'name' => 'Opened', 'sort_order' => 1]);

    $this->getJson('/api/tenant/v1/crm/pipeline-stages?pipeline_id='.$this->pipeline->id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('creates a stage with is_won_stage flag', function () {
    $this->postJson('/api/tenant/v1/crm/pipeline-stages', [
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Won',
        'sort_order' => 5,
        'is_won_stage' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('creates a stage with is_lost_stage flag', function () {
    $this->postJson('/api/tenant/v1/crm/pipeline-stages', [
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Lost',
        'sort_order' => 6,
        'is_lost_stage' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures pipeline stage tenant isolation', function () {
    $tenant2 = pipelineStageTenant();
    tenancy()->initialize($tenant2);
    $pipeline2 = Pipeline::create(['name' => 'Other Pipeline', 'sort_order' => 1]);
    $stage2 = PipelineStage::create(['pipeline_id' => $pipeline2->id, 'name' => 'New', 'sort_order' => 1]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/pipeline-stages/{$stage2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for pipeline stages', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/pipeline-stages')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for pipeline stages', function () {
    $guest = pipelineStageUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/pipeline-stages')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent pipeline stage', function () {
    $this->getJson('/api/tenant/v1/crm/pipeline-stages/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating pipeline stage without name', function () {
    $this->postJson('/api/tenant/v1/crm/pipeline-stages', [
        'pipeline_id' => $this->pipeline->id,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating pipeline stage with invalid probability', function () {
    $this->postJson('/api/tenant/v1/crm/pipeline-stages', [
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Test',
        'probability' => 150,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
