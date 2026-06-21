<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function leadTenant(): Tenant
{
    $domain = 'lead-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function leadUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedLeadPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "leads.{$action}", 'guard_name' => 'tenant-api']);
    }
    Permission::firstOrCreate(['name' => 'lead-stage.move', 'guard_name' => 'tenant-api']);
}

beforeEach(function () {
    seedLeadPermissions();
    $this->tenant = leadTenant();
    $this->user = leadUser($this->tenant, ['leads.view', 'leads.create', 'leads.update', 'leads.delete', 'lead-stage.move']);
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
    $this->stageNew = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1, 'probability' => 10]);
    $this->stageQualified = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Qualified', 'sort_order' => 2, 'probability' => 50]);
    $this->stageWon = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Won', 'sort_order' => 5, 'probability' => 100, 'is_won_stage' => true]);
    $this->stageLost = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Lost', 'sort_order' => 6, 'probability' => 0, 'is_lost_stage' => true]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates a lead', function () {
    $this->postJson('/api/tenant/v1/crm/leads', [
        'title' => 'Big Deal',
        'description' => 'Potential large enterprise deal',
        'value' => 50000.00,
        'probability' => 10,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageNew->id,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists leads', function () {
    Lead::create(['owner_id' => $this->user->id, 'title' => 'Lead 1', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);
    Lead::create(['owner_id' => $this->user->id, 'title' => 'Lead 2', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->getJson('/api/tenant/v1/crm/leads')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a lead', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->getJson("/api/tenant/v1/crm/leads/{$lead->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a lead', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->putJson("/api/tenant/v1/crm/leads/{$lead->id}", [
        'title' => 'Updated Deal',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a lead', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->deleteJson("/api/tenant/v1/crm/leads/{$lead->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('searches leads', function () {
    Lead::create(['owner_id' => $this->user->id, 'title' => 'Enterprise Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);
    Lead::create(['owner_id' => $this->user->id, 'title' => 'Small Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->getJson('/api/tenant/v1/crm/leads?search=Enterprise')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters leads by pipeline_id', function () {
    Lead::create(['owner_id' => $this->user->id, 'title' => 'Lead 1', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->getJson('/api/tenant/v1/crm/leads?pipeline_id='.$this->pipeline->id)
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('moves a lead to a new stage', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id, 'probability' => 10]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->stageQualified->id,
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $lead->refresh();
    expect($lead->pipeline_stage_id)->toBe($this->stageQualified->id);
    expect($lead->probability)->toBe(50);
});

it('marks lead as won when moved to won stage', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->stageWon->id,
    ])->assertSuccessful();

    $lead->refresh();
    expect($lead->won_at)->not->toBeNull();
    expect($lead->lost_at)->toBeNull();
});

it('marks lead as lost when moved to lost stage', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->stageLost->id,
    ])->assertSuccessful();

    $lead->refresh();
    expect($lead->lost_at)->not->toBeNull();
    expect($lead->won_at)->toBeNull();
});

it('restores a soft-deleted lead', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);
    $lead->delete();

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures lead tenant isolation', function () {
    $tenant2 = leadTenant();
    tenancy()->initialize($tenant2);
    $pipeline2 = Pipeline::create(['name' => 'Other Pipeline', 'sort_order' => 1]);
    $stage2 = PipelineStage::create(['pipeline_id' => $pipeline2->id, 'name' => 'New', 'sort_order' => 1]);
    $lead2 = Lead::create(['owner_id' => $this->user->id, 'title' => 'Other Tenant Lead', 'pipeline_id' => $pipeline2->id, 'pipeline_stage_id' => $stage2->id]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/leads/{$lead2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for leads', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/leads')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for leads', function () {
    $guest = leadUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/leads')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for leads', function () {
    $guest = leadUser($this->tenant, ['leads.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/leads', ['title' => 'Test'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for leads', function () {
    $guest = leadUser($this->tenant, ['leads.view', 'leads.create']);
    $this->actingAs($guest, 'tenant-api');
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->putJson("/api/tenant/v1/crm/leads/{$lead->id}", ['title' => 'Hacked'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for leads', function () {
    $guest = leadUser($this->tenant, ['leads.view', 'leads.create', 'leads.update']);
    $this->actingAs($guest, 'tenant-api');
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->deleteJson("/api/tenant/v1/crm/leads/{$lead->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent lead', function () {
    $this->getJson('/api/tenant/v1/crm/leads/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating lead without title', function () {
    $this->postJson('/api/tenant/v1/crm/leads', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when moving lead to stage from different pipeline', function () {
    $pipeline2 = Pipeline::create(['name' => 'Other Pipeline', 'sort_order' => 2]);
    $stage2 = PipelineStage::create(['pipeline_id' => $pipeline2->id, 'name' => 'New', 'sort_order' => 1]);
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $stage2->id,
    ])->assertStatus(422)
        ->assertJson(['status' => false]);
});
