<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Jobs\RecordTimelineEntryJob;
use App\Models\Crm\Organization;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\EventDispatcher;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $domain = 'event-dispatcher-test-'.uniqid().'.localhost';
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

it('dispatches RecordTimelineEntryJob with occurredAt', function () {
    Queue::fake();

    $this->eventDispatcher->record($this->organization, 'test.event', 'Test Event', 'Description', ['key' => 'value'], $this->user->id);

    Queue::assertPushed(RecordTimelineEntryJob::class, function ($job) {
        return $job->eventType === 'test.event'
            && $job->entityType === $this->organization->getMorphClass()
            && $job->entityId === $this->organization->id
            && $job->occurredAt !== null;
    });
});

it('dispatches RecordTimelineEntryJob with occurredAt via recordGeneric', function () {
    Queue::fake();

    $this->eventDispatcher->recordGeneric(
        Organization::class,
        $this->organization->id,
        'generic.event',
        'Generic Event',
        null,
        null,
        $this->user->id,
    );

    Queue::assertPushed(RecordTimelineEntryJob::class, function ($job) {
        return $job->eventType === 'generic.event'
            && $job->occurredAt !== null;
    });
});

it('creates timeline entry when queue is sync with occurredAt', function () {
    $this->eventDispatcher->record($this->organization, 'test.event', 'Test Timeline Entry', 'Description', null, $this->user->id);

    $entry = TimelineEntry::where('entity_type', $this->organization->getMorphClass())
        ->where('entity_id', $this->organization->id)
        ->where('event_type', 'test.event')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Test Timeline Entry');
    expect($entry->occurred_at)->not->toBeNull();
});

it('is idempotent - duplicate timeline entries are not created', function () {
    $this->eventDispatcher->record($this->organization, 'test.event', 'First Attempt');
    $this->eventDispatcher->record($this->organization, 'test.event', 'First Attempt');

    $count = TimelineEntry::where('entity_type', $this->organization->getMorphClass())
        ->where('entity_id', $this->organization->id)
        ->where('event_type', 'test.event')
        ->count();

    expect($count)->toBe(1);
});

it('getEventType returns dotted notation for organization', function () {
    $type = $this->eventDispatcher->getEventType(Organization::class, 'updated');
    expect($type)->toBe('organization.updated');
});
