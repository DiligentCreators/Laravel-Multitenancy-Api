<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\MessageDirectionEnum;
use App\Enums\MessageStatusEnum;
use App\Models\Crm\Conversation;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Message;
use App\Models\Crm\MessageAttachment;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function msgTenant(): Tenant
{
    $domain = 'msg-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function msgUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedMsgPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "communications.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMsgPermissions();
    FeatureDefinition::create(['key' => 'communications.enabled', 'name' => 'Communications Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = msgTenant();
    $this->user = msgUser($this->tenant, ['communications.view', 'communications.create', 'communications.update', 'communications.delete']);
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

it('can list messages for a conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    Message::factory()->count(3)->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages");

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('returns empty list when conversation has no messages', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

it('can create a message', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Hello, this is a test message',
        'direction' => MessageDirectionEnum::OUTBOUND->value,
        'sender_type' => 'user',
        'sender_id' => $this->user->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.body', 'Hello, this is a test message')
        ->assertJsonPath('data.direction', MessageDirectionEnum::OUTBOUND->value)
        ->assertJsonPath('data.status', MessageStatusEnum::SENT->value);
});

it('can create an inbound message', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Incoming message',
        'direction' => MessageDirectionEnum::INBOUND->value,
        'sender_type' => 'user',
        'sender_id' => $this->user->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.direction', MessageDirectionEnum::INBOUND->value);
});

it('can show a message', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $message->id);
});

it('can update a message', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);

    $response = $this->putJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}", [
        'body' => 'Updated body',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.body', 'Updated body');
});

it('can mark message as read', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);

    $response = $this->putJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}", [
        'read_at' => now()->toISOString(),
    ]);

    $response->assertOk();
    $this->assertNotNull($response->json('data.read_at'));
});

it('can delete a message', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);

    $response = $this->deleteJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}");

    $response->assertOk();
    $this->assertSoftDeleted($message);
});

it('returns 404 when message not found', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/99999");

    $response->assertNotFound();
});

it('returns 422 when creating message without body', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'direction' => MessageDirectionEnum::OUTBOUND->value,
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages");

    $response->assertUnauthorized();
});

it('prevents cross-tenant message access', function () {
    $otherTenant = msgTenant();
    $otherConversation = null;
    $message = null;
    tenancy()->initialize($otherTenant);
    $otherConversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $otherConversation->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}");

    $response->assertNotFound();
});

it('returns 404 when message belongs to a different conversation', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $otherConversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $otherConversation->id]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}");

    $response->assertNotFound();
});

it('returns 422 when sender does not exist', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Test',
        'direction' => MessageDirectionEnum::OUTBOUND->value,
        'sender_type' => 'user',
        'sender_id' => 99999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sender_id']);
});

it('can create message with person sender', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $person = Person::create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Message from person',
        'direction' => MessageDirectionEnum::INBOUND->value,
        'sender_type' => 'person',
        'sender_id' => $person->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.sender_type', Person::class);
});

it('can create message with organization sender', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $org = Organization::create(['name' => fake()->company()]);

    $response = $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Message from organization',
        'direction' => MessageDirectionEnum::INBOUND->value,
        'sender_type' => 'organization',
        'sender_id' => $org->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.sender_type', Organization::class);
});

it('can filter messages by direction', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id, 'direction' => MessageDirectionEnum::INBOUND]);
    Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id, 'direction' => MessageDirectionEnum::OUTBOUND]);
    Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id, 'direction' => MessageDirectionEnum::OUTBOUND]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages?direction=outbound");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('updates conversation last_message_at on new message', function () {
    $conversation = Conversation::factory()->create(['last_message_at' => null]);

    $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Test message',
        'direction' => MessageDirectionEnum::OUTBOUND->value,
        'sender_type' => 'user',
        'sender_id' => $this->user->id,
    ]);

    $conversation->refresh();
    $this->assertNotNull($conversation->last_message_at);
});

it('records timeline event on message sent', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Test',
        'direction' => MessageDirectionEnum::OUTBOUND->value,
        'sender_type' => 'user',
        'sender_id' => $this->user->id,
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'message.sent',
    ]);
});

it('records timeline event on message received', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);

    $this->postJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages", [
        'body' => 'Test',
        'direction' => MessageDirectionEnum::INBOUND->value,
        'sender_type' => 'user',
        'sender_id' => $this->user->id,
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'message.received',
    ]);
});

it('records timeline event on message read', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id, 'read_at' => null]);

    $this->putJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}", [
        'read_at' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'message.read',
    ]);
});

it('can include attachments in message response', function () {
    $conversation = Conversation::factory()->create(['owner_id' => $this->user->id]);
    $message = Message::factory()->create(['owner_id' => $this->user->id, 'conversation_id' => $conversation->id]);
    MessageAttachment::create([
        'tenant_id' => tenant()->id,
        'message_id' => $message->id,
        'file_name' => 'test.pdf',
        'file_path' => '/uploads/test.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    $response = $this->getJson("/api/tenant/v1/crm/conversations/{$conversation->id}/messages/{$message->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['attachments']]);
});
