<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Jobs\RecordTimelineEntryJob;
use App\Jobs\TriggerWorkflowJob;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\EventDispatcher;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $domain = 'event-test-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
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
    Permission::firstOrCreate(['name' => 'events.test', 'guard_name' => 'tenant-api']);
    $this->organization = Organization::create(['name' => 'Acme Corp']);
    $this->eventDispatcher = app(EventDispatcher::class);
});

afterEach(function () {
    tenancy()->end();
});

it('dispatches RecordTimelineEntryJob via record', function () {
    Queue::fake();

    $this->eventDispatcher->record($this->organization, 'test.event', 'Test Event', 'Description', ['key' => 'value'], $this->user->id);

    Queue::assertPushed(RecordTimelineEntryJob::class, function ($job) {
        return $job->eventType === 'test.event'
            && $job->entityType === $this->organization->getMorphClass()
            && $job->entityId === $this->organization->id;
    });
});

it('dispatches TriggerWorkflowJob via record', function () {
    Queue::fake();

    $this->eventDispatcher->record($this->organization, 'test.event', 'Test Event');

    Queue::assertPushed(TriggerWorkflowJob::class, function ($job) {
        return $job->tenantId === $this->tenant->id
            && $job->event === 'test.event'
            && $job->entityClass === get_class($this->organization)
            && $job->entityKey === $this->organization->id;
    });
});

it('creates timeline entry when queue is sync', function () {
    $this->eventDispatcher->record($this->organization, 'test.event', 'Test Timeline Entry', 'Description', null, $this->user->id);

    $entry = TimelineEntry::where('entity_type', $this->organization->getMorphClass())
        ->where('entity_id', $this->organization->id)
        ->where('event_type', 'test.event')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Test Timeline Entry');
    expect($entry->description)->toBe('Description');
    expect($entry->caused_by)->toBe($this->user->id);
});

it('records timeline via recordGeneric', function () {
    $this->eventDispatcher->recordGeneric(
        Organization::class,
        $this->organization->id,
        'generic.event',
        'Generic Event',
        null,
        null,
        $this->user->id,
    );

    $entry = TimelineEntry::where('entity_type', Organization::class)
        ->where('entity_id', $this->organization->id)
        ->where('event_type', 'generic.event')
        ->first();

    expect($entry)->not->toBeNull();
});

it('getEventType returns dotted notation', function () {
    $type = $this->eventDispatcher->getEventType(Person::class, 'created');
    expect($type)->toBe('person.created');

    $type2 = $this->eventDispatcher->getEventType(Organization::class, 'updated');
    expect($type2)->toBe('organization.updated');
});
