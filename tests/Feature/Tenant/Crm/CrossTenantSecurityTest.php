<?php

use App\Jobs\ExecuteWorkflowJob;
use App\Jobs\RecordTimelineEntryJob;
use App\Jobs\TriggerWorkflowJob;
use App\Models\Crm\Organization;
use App\Models\Crm\TimelineEntry;
use App\Models\Crm\WorkflowDefinition;
use App\Models\Crm\WorkflowLog;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Crm\EventDispatcher;
use App\Services\Crm\WorkflowService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantA->domains()->create(['domain' => 'tenant-a-'.uniqid().'.localhost']);
    $this->tenantB = Tenant::factory()->create();
    $this->tenantB->domains()->create(['domain' => 'tenant-b-'.uniqid().'.localhost']);

    tenancy()->initialize($this->tenantA);
    $this->orgA = Organization::create(['name' => 'Org A']);
    $this->workflowA = WorkflowDefinition::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Test Workflow A',
        'entity_type' => 'organization',
        'trigger_event' => 'test.event',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
        'is_active' => true,
    ]);
    tenancy()->end();

    tenancy()->initialize($this->tenantB);
    $this->orgB = Organization::withoutGlobalScope(TenantScope::class)
        ->create(['name' => 'Org B', 'tenant_id' => $this->tenantB->id]);
    WorkflowDefinition::withoutGlobalScope(TenantScope::class)->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Test Workflow B',
        'entity_type' => 'organization',
        'trigger_event' => 'test.event',
        'actions' => [['type' => 'assign_owner', 'user_id' => 1]],
        'is_active' => true,
    ]);
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

// 1. WorkflowService::execute rejects cross-tenant entity
it('rejects cross-tenant workflow execution in execute()', function () {
    $this->workflowA->setRelation('actions', [['type' => 'assign_owner', 'user_id' => 1]]);

    $service = app(WorkflowService::class);

    $service->execute($this->workflowA, $this->orgB);
})->throws(RuntimeException::class, 'Cross-tenant workflow execution denied');

// 2. ExecuteWorkflowJob rejects when tenantId does not match workflow tenant
it('rejects ExecuteWorkflowJob when tenantId mismatches workflow', function () {
    $job = new ExecuteWorkflowJob($this->tenantB->id, $this->workflowA->id, Organization::class, $this->orgA->id);

    $service = app(WorkflowService::class);

    try {
        $job->handle($service);
    } catch (ModelNotFoundException) {
    }

    $log = WorkflowLog::where('workflow_id', $this->workflowA->id)->first();
    expect($log)->toBeNull();
});

// 3. RecordTimelineEntryJob writes to explicit tenantId, not current context
it('writes timeline entry to explicit tenantId', function () {
    $job = new RecordTimelineEntryJob(
        $this->tenantA->id,
        Organization::class,
        $this->orgA->id,
        'test.event',
        'Test Title',
    );

    tenancy()->initialize($this->tenantB);
    $job->handle();
    tenancy()->end();

    $entryB = TimelineEntry::where('tenant_id', $this->tenantB->id)
        ->where('entity_type', Organization::class)
        ->where('entity_id', $this->orgA->id)
        ->first();
    expect($entryB)->toBeNull();

    $entryA = TimelineEntry::where('tenant_id', $this->tenantA->id)
        ->where('entity_type', Organization::class)
        ->where('entity_id', $this->orgA->id)
        ->first();
    expect($entryA)->not->toBeNull();
});

// 5. EventDispatcher passes tenantId to both timeline and workflow jobs
it('passes tenantId from EventDispatcher to all jobs', function () {
    Queue::fake();

    tenancy()->initialize($this->tenantA);
    $orgA = Organization::create(['name' => 'Org A4']);

    $dispatcher = app(EventDispatcher::class);
    $dispatcher->record($orgA, 'test.event', 'Test');

    tenancy()->end();

    Queue::assertPushed(RecordTimelineEntryJob::class, function ($job) use ($orgA) {
        return $job->tenantId === $this->tenantA->id
            && $job->entityId === $orgA->id;
    });

    Queue::assertPushed(TriggerWorkflowJob::class, function ($job) use ($orgA) {
        return $job->tenantId === $this->tenantA->id
            && $job->entityKey === $orgA->id;
    });
});

// 6. WorkflowService::trigger only loads same-tenant workflows (not tenant B's)
it('only loads same-tenant workflows in trigger()', function () {
    tenancy()->initialize($this->tenantA);
    $orgA = Organization::create(['name' => 'Org A5']);

    Bus::fake(ExecuteWorkflowJob::class);

    $service = app(WorkflowService::class);
    $service->trigger('test.event', $orgA);

    tenancy()->end();

    Bus::assertDispatched(ExecuteWorkflowJob::class, function ($job) {
        return $job->tenantId === $this->tenantA->id
            && $job->workflowId === $this->workflowA->id;
    });
});

// 7. Tenant A workflow cannot create log entries in Tenant B
it('cannot create cross-tenant workflow logs', function () {
    tenancy()->initialize($this->tenantA);

    $service = app(WorkflowService::class);

    try {
        $service->execute($this->workflowA, $this->orgB);
    } catch (RuntimeException) {
    }

    tenancy()->end();

    $log = WorkflowLog::where('tenant_id', $this->tenantB->id)->first();
    expect($log)->toBeNull();
});
