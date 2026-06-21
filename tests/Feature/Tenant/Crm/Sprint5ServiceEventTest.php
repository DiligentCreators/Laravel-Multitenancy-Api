<?php

use App\Enums\Central\NotificationChannelEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Lead;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Spatie\Permission\Models\Permission;

function s5Tenant(): Tenant
{
    $domain = 's5-event-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function s5User(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function s5Permissions(): void
{
    foreach (['people.view', 'people.create', 'people.update', 'people.delete',
        'organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete',
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'lead-stage.move',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    s5Permissions();
    $this->tenant = s5Tenant();
    $this->user = s5User($this->tenant, [
        'people.view', 'people.create', 'people.update', 'people.delete',
        'organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete',
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'lead-stage.move',
    ]);
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
    Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);
    $this->leadStageNew = PipelineStage::create(['pipeline_id' => 1, 'name' => 'New', 'sort_order' => 1, 'probability' => 10]);
    $this->leadStageWon = PipelineStage::create(['pipeline_id' => 1, 'name' => 'Won', 'sort_order' => 5, 'probability' => 100, 'is_won_stage' => true]);
    $this->leadStageLost = PipelineStage::create(['pipeline_id' => 1, 'name' => 'Lost', 'sort_order' => 6, 'probability' => 0, 'is_lost_stage' => true]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Service-Level Event Dispatching ---

it('creates timeline entry via person creation', function () {
    $this->postJson('/api/tenant/v1/crm/people', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ])->assertCreated();

    $entry = TimelineEntry::where('event_type', 'person.created')->first();
    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Person Created');
});

it('creates timeline entry via person update', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);

    $this->putJson("/api/tenant/v1/crm/people/{$person->id}", [
        'first_name' => 'Jane',
    ])->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'person.updated')->where('entity_id', $person->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via person deletion', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);

    $this->deleteJson("/api/tenant/v1/crm/people/{$person->id}")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'person.deleted')->where('entity_id', $person->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via person restore', function () {
    $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);
    $person->delete();

    $this->postJson("/api/tenant/v1/crm/people/{$person->id}/restore")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'person.restored')->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via organization creation', function () {
    $this->postJson('/api/tenant/v1/crm/organizations', [
        'name' => 'Acme Corp',
    ])->assertCreated();

    $entry = TimelineEntry::where('event_type', 'organization.created')->first();
    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Organization Created');
});

it('creates timeline entry via organization update', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);

    $this->putJson("/api/tenant/v1/crm/organizations/{$org->id}", [
        'name' => 'Acme Inc',
    ])->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'organization.updated')->where('entity_id', $org->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via organization deletion', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);

    $this->deleteJson("/api/tenant/v1/crm/organizations/{$org->id}")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'organization.deleted')->where('entity_id', $org->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via organization restore', function () {
    $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);
    $org->delete();

    $this->postJson("/api/tenant/v1/crm/organizations/{$org->id}/restore")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'organization.restored')->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via lead creation', function () {
    $this->postJson('/api/tenant/v1/crm/leads', [
        'title' => 'Big Deal',
        'pipeline_id' => 1,
        'pipeline_stage_id' => $this->leadStageNew->id,
    ])->assertCreated();

    $entry = TimelineEntry::where('event_type', 'lead.created')->first();
    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Lead Created');
});

it('creates timeline entry via lead update', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);

    $this->putJson("/api/tenant/v1/crm/leads/{$lead->id}", [
        'title' => 'Bigger Deal',
    ])->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'lead.updated')->where('entity_id', $lead->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via lead deletion', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);

    $this->deleteJson("/api/tenant/v1/crm/leads/{$lead->id}")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'lead.deleted')->where('entity_id', $lead->id)->first();
    expect($entry)->not->toBeNull();
});

it('creates timeline entry via lead restore', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);
    $lead->delete();

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/restore")->assertSuccessful();

    $entry = TimelineEntry::where('event_type', 'lead.restored')->first();
    expect($entry)->not->toBeNull();
});

// --- Lead Won/Lost Events ---

it('creates lead.won timeline entry when moved to won stage', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->leadStageWon->id,
    ])->assertSuccessful();

    $wonEntry = TimelineEntry::where('event_type', 'lead.won')->where('entity_id', $lead->id)->first();
    expect($wonEntry)->not->toBeNull();
    expect($wonEntry->title)->toBe('Lead Won');
});

it('creates lead.lost timeline entry when moved to lost stage', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->leadStageLost->id,
    ])->assertSuccessful();

    $lostEntry = TimelineEntry::where('event_type', 'lead.lost')->where('entity_id', $lead->id)->first();
    expect($lostEntry)->not->toBeNull();
    expect($lostEntry->title)->toBe('Lead Lost');
});

it('creates stage_moved but not won/lost for neutral stage transition', function () {
    $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => 1, 'pipeline_stage_id' => $this->leadStageNew->id]);

    $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
        'pipeline_stage_id' => $this->leadStageNew->id,
    ])->assertSuccessful();

    $stageEntry = TimelineEntry::where('event_type', 'lead.stage_moved')->where('entity_id', $lead->id)->first();
    expect($stageEntry)->not->toBeNull();
    expect(TimelineEntry::where('event_type', 'lead.won')->where('entity_id', $lead->id)->count())->toBe(0);
    expect(TimelineEntry::where('event_type', 'lead.lost')->where('entity_id', $lead->id)->count())->toBe(0);
});

// --- NotificationService Stub ---

it('notification service send method is callable', function () {
    $service = app(NotificationService::class);
    $service->send($this->user, 'Test Title', 'Test Body', NotificationChannelEnum::IN_APP);

    expect(true)->toBeTrue();
});

it('notification service queue method is callable', function () {
    $service = app(NotificationService::class);
    $service->queue($this->user, 'Test Title', 'Test Body', NotificationChannelEnum::EMAIL);

    expect(true)->toBeTrue();
});

it('notification service broadcast method is callable', function () {
    $service = app(NotificationService::class);
    $service->broadcast('test.event', ['key' => 'value']);

    expect(true)->toBeTrue();
});
