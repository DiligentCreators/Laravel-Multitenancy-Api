<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\ConversationChannelEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\MessageTemplate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function tmplTenant(): Tenant
{
    $domain = 'tmpl-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function tmplUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTmplPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "message_templates.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedTmplPermissions();
    FeatureDefinition::create(['key' => 'message_templates.enabled', 'name' => 'Message Templates Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = tmplTenant();
    $this->user = tmplUser($this->tenant, ['message_templates.view', 'message_templates.create', 'message_templates.update', 'message_templates.delete']);
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

it('can list message templates', function () {
    MessageTemplate::factory()->count(3)->create();

    $response = $this->getJson('/api/tenant/v1/crm/message-templates');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can create a message template', function () {
    $response = $this->postJson('/api/tenant/v1/crm/message-templates', [
        'name' => 'Welcome Email',
        'channel' => ConversationChannelEnum::EMAIL->value,
        'body' => 'Hello {{name}}, welcome to our platform!',
        'variables' => ['name'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Welcome Email')
        ->assertJsonPath('data.channel', ConversationChannelEnum::EMAIL->value);
});

it('can show a message template', function () {
    $template = MessageTemplate::factory()->create();

    $response = $this->getJson("/api/tenant/v1/crm/message-templates/{$template->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $template->id);
});

it('can update a message template', function () {
    $template = MessageTemplate::factory()->create();

    $response = $this->putJson("/api/tenant/v1/crm/message-templates/{$template->id}", [
        'name' => 'Updated Template',
        'body' => 'Updated body',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Template');
});

it('can delete a message template', function () {
    $template = MessageTemplate::factory()->create();

    $response = $this->deleteJson("/api/tenant/v1/crm/message-templates/{$template->id}");

    $response->assertOk();
    $this->assertSoftDeleted($template);
});

it('can restore a message template', function () {
    $template = MessageTemplate::factory()->create();
    $template->delete();

    $response = $this->postJson("/api/tenant/v1/crm/message-templates/{$template->id}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted($template);
});

it('returns 404 when message template not found', function () {
    $response = $this->getJson('/api/tenant/v1/crm/message-templates/99999');

    $response->assertNotFound();
});

it('returns 422 with invalid template data', function () {
    $response = $this->postJson('/api/tenant/v1/crm/message-templates', [
        'channel' => 'invalid-channel',
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/message-templates');

    $response->assertUnauthorized();
});

it('prevents cross-tenant access for message templates', function () {
    $otherTenant = tmplTenant();
    $template = null;
    tenancy()->initialize($otherTenant);
    $template = MessageTemplate::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/message-templates/{$template->id}");

    $response->assertNotFound();
});

it('can filter templates by channel', function () {
    MessageTemplate::factory()->create(['channel' => ConversationChannelEnum::EMAIL->value]);
    MessageTemplate::factory()->create(['channel' => ConversationChannelEnum::SMS->value]);

    $response = $this->getJson('/api/tenant/v1/crm/message-templates?channel='.ConversationChannelEnum::EMAIL->value);

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter templates by is_active', function () {
    MessageTemplate::factory()->create(['is_active' => true]);
    MessageTemplate::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/tenant/v1/crm/message-templates?is_active=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can search templates', function () {
    MessageTemplate::factory()->create(['name' => 'Welcome Email']);
    MessageTemplate::factory()->create(['name' => 'Follow Up']);

    $response = $this->getJson('/api/tenant/v1/crm/message-templates?search=Welcome');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter templates by category', function () {
    MessageTemplate::factory()->create(['category' => 'onboarding']);
    MessageTemplate::factory()->create(['category' => 'support']);

    $response = $this->getJson('/api/tenant/v1/crm/message-templates?category=onboarding');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});
