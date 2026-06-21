<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\WhatsAppMessageDirectionEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\WhatsAppAccount;
use App\Models\Crm\WhatsAppMessage;
use App\Models\Crm\WhatsAppPhoneNumber;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function waMsgTestTenant(): Tenant
{
    $domain = 'wamsg-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function waMsgTestUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedWaMsgPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "whatsapp.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedWaMsgPermissions();
    FeatureDefinition::create(['key' => 'whatsapp.enabled', 'name' => 'WhatsApp Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = waMsgTestTenant();
    $this->user = waMsgTestUser($this->tenant, ['whatsapp.view', 'whatsapp.create', 'whatsapp.update', 'whatsapp.delete']);
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

    $this->account = WhatsAppAccount::factory()->create();
    $this->phoneNumber = WhatsAppPhoneNumber::factory()->create([
        'whatsapp_account_id' => $this->account->id,
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('can list whatsapp messages', function () {
    WhatsAppMessage::factory()->count(3)->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
    ]);

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-messages');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can show a whatsapp message', function () {
    $message = WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
        'content' => 'Test message content',
    ]);

    $response = $this->getJson("/api/tenant/v1/crm/whatsapp-messages/{$message->id}");

    $response->assertOk()
        ->assertJsonPath('data.content', 'Test message content');
});

it('cannot create a whatsapp message via API', function () {
    $response = $this->postJson('/api/tenant/v1/crm/whatsapp-messages', [
        'content' => 'New message',
    ]);

    $response->assertStatus(405);
});

it('cannot update a whatsapp message via API', function () {
    $message = WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
    ]);

    $response = $this->putJson("/api/tenant/v1/crm/whatsapp-messages/{$message->id}", [
        'content' => 'Updated',
    ]);

    $response->assertStatus(405);
});

it('cannot delete a whatsapp message via API', function () {
    $message = WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
    ]);

    $response = $this->deleteJson("/api/tenant/v1/crm/whatsapp-messages/{$message->id}");

    $response->assertStatus(405);
});

it('can filter whatsapp messages by direction', function () {
    WhatsAppMessage::factory()->count(2)->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
        'direction' => WhatsAppMessageDirectionEnum::INBOUND,
    ]);
    WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
        'direction' => WhatsAppMessageDirectionEnum::OUTBOUND,
    ]);

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-messages?direction=inbound');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('can search whatsapp messages', function () {
    WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
        'content' => 'Unique searchable content',
    ]);
    WhatsAppMessage::factory()->count(2)->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
    ]);

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-messages?search=Unique');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('prevents cross-tenant access to whatsapp messages', function () {
    $otherTenant = waMsgTestTenant();
    $otherMessage = null;
    tenancy()->initialize($otherTenant);
    $otherAccount = WhatsAppAccount::factory()->create();
    $otherPhone = WhatsAppPhoneNumber::factory()->create(['whatsapp_account_id' => $otherAccount->id]);
    $otherMessage = WhatsAppMessage::factory()->create(['whatsapp_phone_number_id' => $otherPhone->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/whatsapp-messages/{$otherMessage->id}");

    $response->assertNotFound();
});

it('returns 401 when unauthenticated for whatsapp messages', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-messages');

    $response->assertUnauthorized();
});
