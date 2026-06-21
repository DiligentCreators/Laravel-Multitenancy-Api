<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\WhatsAppAccount;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function waTestTenant(): Tenant
{
    $domain = 'watest-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function waTestUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedWaPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "whatsapp.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedWaPermissions();
    FeatureDefinition::create(['key' => 'whatsapp.enabled', 'name' => 'WhatsApp Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = waTestTenant();
    $this->user = waTestUser($this->tenant, ['whatsapp.view', 'whatsapp.create', 'whatsapp.update', 'whatsapp.delete']);
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
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('can list whatsapp accounts', function () {
    WhatsAppAccount::factory()->count(3)->create();

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-accounts');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can create a whatsapp account', function () {
    $response = $this->postJson('/api/tenant/v1/crm/whatsapp-accounts', [
        'business_account_id' => '123456789',
        'app_id' => '987654321',
        'app_secret' => 'secret-key-here',
        'access_token' => 'token-here',
        'webhook_verify_token' => 'verify-token-here',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.business_account_id', '123456789');
});

it('can show a whatsapp account', function () {
    $account = WhatsAppAccount::factory()->create();

    $response = $this->getJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $account->id);
});

it('can update a whatsapp account', function () {
    $account = WhatsAppAccount::factory()->create();

    $response = $this->putJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}", [
        'business_account_id' => 'updated-id',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.business_account_id', 'updated-id');
});

it('can delete a whatsapp account', function () {
    $account = WhatsAppAccount::factory()->create();

    $response = $this->deleteJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}");

    $response->assertOk();
    $this->assertSoftDeleted($account);
});

it('can restore a whatsapp account', function () {
    $account = WhatsAppAccount::factory()->create();
    $account->delete();

    $response = $this->postJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted($account);
});

it('can connect a whatsapp account', function () {
    $response = $this->postJson('/api/tenant/v1/crm/whatsapp-accounts/connect', [
        'business_account_id' => 'conn-123',
        'app_id' => 'app-456',
        'app_secret' => 'secret-key',
        'access_token' => 'access-token',
        'webhook_verify_token' => 'verify-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.business_account_id', 'conn-123');
});

it('can disconnect a whatsapp account', function () {
    $account = WhatsAppAccount::factory()->create(['status' => 'active']);

    $response = $this->postJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}/disconnect");

    $response->assertOk();
    $account->refresh();
    expect($account->status->value)->toBe('disconnected');
});

it('can sync phone numbers', function () {
    $account = WhatsAppAccount::factory()->create();

    $response = $this->postJson("/api/tenant/v1/crm/whatsapp-accounts/{$account->id}/sync-phone-numbers", [
        'phone_numbers' => [
            [
                'phone_number_id' => 'pn-001',
                'display_phone_number' => '+12025550199',
                'verified_name' => 'Test Business',
                'quality_rating' => 'green',
                'status' => 'connected',
            ],
        ],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('crm_whatsapp_phone_numbers', [
        'whatsapp_account_id' => $account->id,
        'display_phone_number' => '+12025550199',
    ]);
});

it('returns 422 with invalid whatsapp account data', function () {
    $response = $this->postJson('/api/tenant/v1/crm/whatsapp-accounts', [
        'business_account_id' => '',
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated for whatsapp accounts', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/whatsapp-accounts');

    $response->assertUnauthorized();
});

it('prevents cross-tenant access to whatsapp accounts', function () {
    $otherTenant = waTestTenant();
    $otherAccount = null;
    tenancy()->initialize($otherTenant);
    $otherAccount = WhatsAppAccount::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/whatsapp-accounts/{$otherAccount->id}");

    $response->assertNotFound();
});

it('records timeline event on whatsapp account creation', function () {
    $this->postJson('/api/tenant/v1/crm/whatsapp-accounts', [
        'business_account_id' => 'timeline-acc',
        'app_id' => 'app-id',
        'app_secret' => 'secret',
        'access_token' => 'token',
        'webhook_verify_token' => 'verify',
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'whatsapp.account_connected',
    ]);
});
