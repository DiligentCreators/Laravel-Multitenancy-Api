<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Document;
use App\Models\Crm\DocumentShare;
use App\Models\Crm\FeatureDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function shareTenant(): Tenant
{
    $domain = 'share-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function shareUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedSharePermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "documents.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedSharePermissions();
    FeatureDefinition::create(['key' => 'documents.upload', 'name' => 'Document Upload', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = shareTenant();
    $this->user = shareUser($this->tenant, ['documents.view', 'documents.create', 'documents.update', 'documents.delete']);
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

// --- Share CRUD ---

it('can create a document share', function () {
    $document = Document::factory()->create();

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/shares", []);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['share_token', 'access_count']]);
});

it('can list document shares', function () {
    $document = Document::factory()->create();
    DocumentShare::factory()->count(3)->create(['document_id' => $document->id]);

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$document->id}/shares");

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can show a document share', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::factory()->create(['document_id' => $document->id]);

    $response = $this->getJson("/api/tenant/v1/crm/documents/shares/{$share->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $share->id);
});

it('can delete a document share', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::factory()->create(['document_id' => $document->id]);

    $response = $this->deleteJson("/api/tenant/v1/crm/documents/shares/{$share->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('crm_document_shares', ['id' => $share->id]);
});

// --- Public Access ---

it('can access a document via share token', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::factory()->create([
        'document_id' => $document->id,
        'share_token' => 'test-token-123',
    ]);

    $response = $this->postJson('/api/tenant/v1/crm/documents/shared/test-token-123');

    $response->assertOk()
        ->assertJsonPath('data.id', $document->id);
});

it('returns 404 for invalid share token', function () {
    $response = $this->postJson('/api/tenant/v1/crm/documents/shared/invalid-token');

    $response->assertNotFound();
});

it('returns 403 for expired share link', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::factory()->create([
        'document_id' => $document->id,
        'share_token' => 'expired-token',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/tenant/v1/crm/documents/shared/expired-token');

    $response->assertForbidden();
});

it('can access password protected share with correct password', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::create([
        'tenant_id' => tenant()->id,
        'document_id' => $document->id,
        'share_token' => 'password-test-token',
        'password' => bcrypt('secret123'),
        'password_protected' => true,
    ]);

    $response = $this->postJson('/api/tenant/v1/crm/documents/shared/password-test-token', [
        'password' => 'secret123',
    ]);

    $response->assertOk();
});

it('returns 403 for password protected share with wrong password', function () {
    $document = Document::factory()->create();
    $share = DocumentShare::create([
        'tenant_id' => tenant()->id,
        'document_id' => $document->id,
        'share_token' => 'password-test-token-2',
        'password' => bcrypt('secret123'),
        'password_protected' => true,
    ]);

    $response = $this->postJson('/api/tenant/v1/crm/documents/shared/password-test-token-2', [
        'password' => 'wrong-password',
    ]);

    $response->assertForbidden();
});

// --- Timeline Events ---

it('records timeline event on document share', function () {
    $document = Document::factory()->create();

    $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/shares", []);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'document.shared',
    ]);
});

// --- 422 / 401 ---

it('returns 422 with invalid share data', function () {
    $document = Document::factory()->create();

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/shares", [
        'expires_at' => 'not-a-date',
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $document = Document::factory()->create();
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$document->id}/shares");

    $response->assertUnauthorized();
});

// --- Tenant Isolation ---

it('prevents cross-tenant share access', function () {
    $otherTenant = shareTenant();
    $otherShare = null;
    tenancy()->initialize($otherTenant);
    $otherDocument = Document::factory()->create();
    $otherShare = DocumentShare::factory()->create(['document_id' => $otherDocument->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/documents/shares/{$otherShare->id}");

    $response->assertNotFound();
});
