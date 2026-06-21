<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\ConversationChannelEnum;
use App\Enums\ConversationStatusEnum;
use App\Models\Crm\Conversation;
use App\Models\Crm\ConversationParticipant;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function commsTenant(): Tenant
{
    $domain = 'comms-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function commsUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedCommsPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "communications.{$action}", 'guard_name' => 'tenant-api']);
        Permission::firstOrCreate(['name' => "message_templates.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedCommsPermissions();
    FeatureDefinition::create(['key' => 'communications.enabled', 'name' => 'Communications Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    FeatureDefinition::create(['key' => 'message_templates.enabled', 'name' => 'Message Templates Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = commsTenant();
    $this->user = commsUser($this->tenant, ['communications.view', 'communications.create', 'communications.update', 'communications.delete']);
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

function seedCommsPlanFeature(Tenant $tenant): void
{
    $feature = FeatureDefinition::where('key', 'communications.enabled')->first();
    $plan = Plan::where('is_active', true)->first();
    if ($feature && $plan) {
        $plan->features()->updateOrCreate(
            ['feature_id' => $feature->id],
            ['value' => true]
        );
    }
}

afterEach(function () {
    tenancy()->end();
});

it('can list conversations', function () {
    Conversation::factory()->count(3)->create(['owner_id' => $this->user->id]);

    $response = $this->getJson('/api/tenant/v1/crm/conversations');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can create a conversation', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    $user = User::factory()->create();

    $response = $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Test Conversation',
        'participants' => [
            ['type' => 'person', 'id' => $person->id, 'is_primary' => true],
            ['type' => 'user', 'id' => $user->id, 'is_primary' => false],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.subject', 'Test Conversation')
        ->assertJsonPath('data.channel', ConversationChannelEnum::EMAIL->value)
        ->assertJsonPath('data.status', ConversationStatusEnum::OPEN->value);
});

it('can show a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $conversation->id);
});

it('can update a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->putJson("/api/tenant/v1/crm/conversations/{$conversation->id}", [
        'subject' => 'Updated Subject',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.subject', 'Updated Subject');
});

it('can delete a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->deleteJson("/api/tenant/v1/crm/conversations/{$conversation->id}");

    $response->assertOk();
    $this->assertSoftDeleted($conversation);
});

it('can close a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/close");

    $response->assertOk()
        ->assertJsonPath('data.status', ConversationStatusEnum::CLOSED->value);
});

it('can restore a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $conversation->delete();

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted($conversation);
});

it('returns 404 when conversation not found', function () {
    $response = $this->getJson('/api/tenant/v1/crm/conversations/99999');

    $response->assertNotFound();
});

it('returns 422 with invalid data', function () {
    $response = $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => 'invalid-channel',
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/conversations');

    $response->assertUnauthorized();
});

it('prevents cross-tenant access', function () {
    $otherTenant = commsTenant();
    $conversation = null;
    tenancy()->initialize($otherTenant);
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}");

    $response->assertNotFound();
});

it('can filter by channel', function () {
    Conversation::factory()->create(['owner_id' => $this->user->id, 'channel' => ConversationChannelEnum::EMAIL]);
    Conversation::factory()->create(['owner_id' => $this->user->id, 'channel' => ConversationChannelEnum::INTERNAL]);

    $response = $this->getJson('/api/tenant/v1/crm/conversations?channel=email');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter by status', function () {
    Conversation::factory()->create(['owner_id' => $this->user->id, 'status' => ConversationStatusEnum::OPEN]);
    Conversation::factory()->create(['owner_id' => $this->user->id, 'status' => ConversationStatusEnum::CLOSED]);

    $response = $this->getJson('/api/tenant/v1/crm/conversations?status=closed');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can search conversations', function () {
    Conversation::factory()->create(['owner_id' => $this->user->id, 'subject' => 'Important Support Ticket']);
    Conversation::factory()->create(['owner_id' => $this->user->id, 'subject' => 'General Inquiry']);

    $response = $this->getJson('/api/tenant/v1/crm/conversations?search=Important');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter participants in conversation', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    ConversationParticipant::create([
        'tenant_id' => tenant()->id,
        'conversation_id' => $conversation->id,
        'participant_type' => Person::class,
        'participant_id' => $person->id,
        'is_primary' => true,
    ]);

    $response = $this->getJson('/api/tenant/v1/crm/conversations?participant_type='.urlencode(Person::class));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('respects max pagination limit', function () {
    Conversation::factory()->count(5)->create(['owner_id' => $this->user->id]);

    $response = $this->getJson('/api/tenant/v1/crm/conversations?per_page=200');

    $response->assertOk();
    $this->assertLessThanOrEqual(100, count($response->json('data')));
});

it('records timeline event on conversation creation', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    $user = User::factory()->create();

    $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Timeline Test',
        'participants' => [
            ['type' => 'person', 'id' => $person->id, 'is_primary' => true],
        ],
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'conversation.created',
    ]);
});

it('records timeline event on conversation close', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/close");

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'conversation.closed',
        'entity_type' => Conversation::class,
        'entity_id' => $conversation->id,
    ]);
});

it('can create conversation with multiple participants', function () {
    $person1 = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    $person2 = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    $org = Organization::create(['name' => fake()->company()]);
    $user = User::factory()->create();

    $response = $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Multi-Participant Conversation',
        'participants' => [
            ['type' => 'person', 'id' => $person1->id, 'is_primary' => true],
            ['type' => 'person', 'id' => $person2->id, 'is_primary' => false],
            ['type' => 'organization', 'id' => $org->id, 'is_primary' => false],
            ['type' => 'user', 'id' => $user->id, 'is_primary' => false],
        ],
    ]);

    $response->assertCreated();
    $conversationId = $response->json('data.id');
    $this->assertDatabaseCount('crm_conversation_participants', 4);
    $this->assertDatabaseHas('crm_conversation_participants', [
        'conversation_id' => $conversationId,
        'participant_type' => Person::class,
        'participant_id' => $person1->id,
        'is_primary' => true,
    ]);
});

it('enforces unique participant constraint across conversations', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);

    $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Conversation A',
        'participants' => [
            ['type' => 'person', 'id' => $person->id, 'is_primary' => true],
        ],
    ])->assertCreated();

    tenancy()->initialize($this->tenant);

    $conversation2 = Conversation::create([
        'owner_id' => $this->user->id,
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Conversation B',
    ]);

    ConversationParticipant::create([
        'conversation_id' => $conversation2->id,
        'participant_type' => Person::class,
        'participant_id' => $person->id,
        'is_primary' => true,
    ]);

    $this->assertDatabaseCount('crm_conversation_participants', 2);
});

it('includes participants in create response', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);

    $response = $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'Participants Test',
        'participants' => [
            ['type' => 'person', 'id' => $person->id, 'is_primary' => true],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['participants']])
        ->assertJsonCount(1, 'data.participants');
});

it('generates uuid on creation', function () {
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);

    $response = $this->postJson('/api/tenant/v1/crm/conversations', [
        'channel' => ConversationChannelEnum::EMAIL->value,
        'subject' => 'UUID Test',
        'participants' => [
            ['type' => 'person', 'id' => $person->id, 'is_primary' => true],
        ],
    ]);

    $response->assertCreated();
    $uuid = $response->json('data.uuid');
    $this->assertTrue(Str::isUuid($uuid));
});

it('includes participants in response when loaded', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
    ConversationParticipant::create([
        'tenant_id' => tenant()->id,
        'conversation_id' => $conversation->id,
        'participant_type' => Person::class,
        'participant_id' => $person->id,
        'is_primary' => true,
    ]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['participants']]);
});
