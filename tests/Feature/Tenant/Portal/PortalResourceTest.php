<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\CalendarEvent;
use App\Models\Crm\Conversation;
use App\Models\Crm\ConversationParticipant;
use App\Models\Crm\Document;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Message;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\PortalPersonLink;
use App\Models\Crm\PortalUser;
use App\Models\Crm\Task;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    FeatureDefinition::create(['key' => 'portal.enabled', 'name' => 'Portal Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);

    $domain = 'portal-resource-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);

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

    $this->person = Person::create(['first_name' => 'Portal', 'last_name' => 'User', 'email' => 'portal@test.com']);
    $this->portalUser = PortalUser::factory()->create();
    PortalPersonLink::factory()->create([
        'portal_user_id' => $this->portalUser->id,
        'person_id' => $this->person->id,
    ]);

    $this->token = $this->portalUser->createToken('portal-token')->plainTextToken;
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('lists documents linked to portal user person', function () {
    $document = Document::factory()->create([
        'documentable_type' => (new Person)->getMorphClass(),
        'documentable_id' => $this->person->id,
    ]);

    Document::factory()->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/documents');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('lists documents linked to portal user organization', function () {
    $org = Organization::create(['name' => 'Test Org']);
    PortalPersonLink::factory()->create([
        'portal_user_id' => $this->portalUser->id,
        'organization_id' => $org->id,
    ]);

    $document = Document::factory()->create([
        'documentable_type' => (new Organization)->getMorphClass(),
        'documentable_id' => $org->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/documents');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('shows document linked to portal user', function () {
    $document = Document::factory()->create([
        'documentable_type' => (new Person)->getMorphClass(),
        'documentable_id' => $this->person->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/documents/{$document->id}");

    $response->assertOk();
});

it('hides document not linked to portal user', function () {
    $document = Document::factory()->create();

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/documents/{$document->id}");

    $response->assertNotFound();
});

it('lists conversations linked to portal user person', function () {
    $conversation = Conversation::factory()->create();
    ConversationParticipant::create([
        'conversation_id' => $conversation->id,
        'participant_type' => (new Person)->getMorphClass(),
        'participant_id' => $this->person->id,
        'is_primary' => true,
    ]);

    Conversation::factory()->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/conversations');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('shows conversation linked to portal user', function () {
    $conversation = Conversation::factory()->create();
    ConversationParticipant::create([
        'conversation_id' => $conversation->id,
        'participant_type' => (new Person)->getMorphClass(),
        'participant_id' => $this->person->id,
        'is_primary' => true,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/conversations/{$conversation->id}");

    $response->assertOk();
});

it('hides conversation not linked to portal user', function () {
    $conversation = Conversation::factory()->create();

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/conversations/{$conversation->id}");

    $response->assertNotFound();
});

it('lists messages from linked conversations', function () {
    $conversation = Conversation::factory()->create();
    ConversationParticipant::create([
        'conversation_id' => $conversation->id,
        'participant_type' => (new Person)->getMorphClass(),
        'participant_id' => $this->person->id,
        'is_primary' => true,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/messages');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('shows message from linked conversation', function () {
    $conversation = Conversation::factory()->create();
    ConversationParticipant::create([
        'conversation_id' => $conversation->id,
        'participant_type' => (new Person)->getMorphClass(),
        'participant_id' => $this->person->id,
        'is_primary' => true,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/messages/{$message->id}");

    $response->assertOk();
});

it('hides message from unlinked conversation', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/messages/{$message->id}");

    $response->assertNotFound();
});

it('lists tasks linked to portal user person', function () {
    $task = Task::create([
        'title' => 'Portal Task',
        'taskable_type' => (new Person)->getMorphClass(),
        'taskable_id' => $this->person->id,
    ]);

    Task::create(['title' => 'Other Task']);

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/tasks');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('shows task linked to portal user', function () {
    $task = Task::create([
        'title' => 'Portal Task',
        'taskable_type' => (new Person)->getMorphClass(),
        'taskable_id' => $this->person->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/tasks/{$task->id}");

    $response->assertOk();
});

it('hides task not linked to portal user', function () {
    $task = Task::create(['title' => 'Other Task']);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/tasks/{$task->id}");

    $response->assertNotFound();
});

it('lists calendar events linked to portal user person', function () {
    $event = CalendarEvent::create([
        'title' => 'Portal Event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'eventable_type' => (new Person)->getMorphClass(),
        'eventable_id' => $this->person->id,
    ]);

    CalendarEvent::create([
        'title' => 'Other Event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/tenant/v1/portal/calendar-events');

    $response->assertOk();
    $items = $response->json('data.data') ?? $response->json('data');
    expect($items)->toHaveCount(1);
});

it('shows calendar event linked to portal user', function () {
    $event = CalendarEvent::create([
        'title' => 'Portal Event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'eventable_type' => (new Person)->getMorphClass(),
        'eventable_id' => $this->person->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/calendar-events/{$event->id}");

    $response->assertOk();
});

it('hides calendar event not linked to portal user', function () {
    $event = CalendarEvent::create([
        'title' => 'Other Event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/tenant/v1/portal/calendar-events/{$event->id}");

    $response->assertNotFound();
});
