<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\DocumentStatusEnum;
use App\Models\Crm\Document;
use App\Models\Crm\DocumentFolder;
use App\Models\Crm\DocumentVersion;
use App\Models\Crm\FeatureDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function docTestTenant(): Tenant
{
    $domain = 'doctest-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function docTestUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedDocTestPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "documents.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDocTestPermissions();
    FeatureDefinition::create(['key' => 'documents.upload', 'name' => 'Document Upload', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    FeatureDefinition::create(['key' => 'documents.storage_mb', 'name' => 'Document Storage MB', 'type' => 'numeric', 'default_value' => 100, 'is_usage_limit' => true]);
    $this->tenant = docTestTenant();
    $this->user = docTestUser($this->tenant, ['documents.view', 'documents.create', 'documents.update', 'documents.delete']);
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

it('can list documents', function () {
    Document::factory()->count(3)->create(['owner_id' => $this->user->id]);

    $response = $this->getJson('/api/tenant/v1/crm/documents');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can create a document', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson('/api/tenant/v1/crm/documents', [
        'folder_id' => $folder->id,
        'name' => 'Annual Report',
        'file_name' => 'report.pdf',
        'file_path' => '/uploads/report.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'extension' => 'pdf',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Annual Report')
        ->assertJsonPath('data.extension', 'pdf');
});

it('can show a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$document->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $document->id);
});

it('can update a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->putJson("/api/tenant/v1/crm/documents/{$document->id}", [
        'name' => 'Updated Document Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Document Name');
});

it('can delete a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->deleteJson("/api/tenant/v1/crm/documents/{$document->id}");

    $response->assertOk();
    $this->assertSoftDeleted($document);
});

it('can lock a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id, 'is_locked' => false]);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/lock");

    $response->assertOk();
    $this->assertTrue($response->json('data.is_locked'));
});

it('can unlock a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id, 'is_locked' => true]);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/unlock");

    $response->assertOk();
    $this->assertFalse($response->json('data.is_locked'));
});

it('can move a document to a folder', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);
    $newFolder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->putJson("/api/tenant/v1/crm/documents/{$document->id}/move", [
        'folder_id' => $newFolder->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.folder_id', $newFolder->id);
});

it('can publish a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id, 'status' => DocumentStatusEnum::DRAFT]);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/publish");

    $response->assertOk();
    $this->assertEquals(DocumentStatusEnum::PUBLISHED->value, $response->json('data.status'));
});

it('can archive a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id, 'status' => DocumentStatusEnum::PUBLISHED]);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/archive");

    $response->assertOk();
    $this->assertEquals(DocumentStatusEnum::ARCHIVED->value, $response->json('data.status'));
});

it('can create a document version', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/versions", [
        'file_name' => 'v2.pdf',
        'file_path' => '/uploads/v2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.version', '1.1');
});

it('can list document versions', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);
    DocumentVersion::factory()->count(2)->create(['document_id' => $document->id]);

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$document->id}/versions");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('bumps version on new version', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id, 'version' => '2.0']);

    $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/versions", [
        'file_name' => 'v3.pdf',
        'file_path' => '/uploads/v3.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 3072,
    ]);

    $document->refresh();
    expect($document->version)->toBe('2.1');
});

it('can restore a document', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);
    $document->delete();

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted($document);
});

it('returns 404 when document not found', function () {
    $response = $this->getJson('/api/tenant/v1/crm/documents/99999');

    $response->assertNotFound();
});

it('returns 422 with invalid data', function () {
    $response = $this->postJson('/api/tenant/v1/crm/documents', [
        'name' => '',
    ]);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/documents');

    $response->assertUnauthorized();
});

it('prevents cross-tenant access', function () {
    $otherTenant = docTestTenant();
    $otherDocument = null;
    tenancy()->initialize($otherTenant);
    $otherDocument = Document::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$otherDocument->id}");

    $response->assertNotFound();
});

it('can search documents', function () {
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'Alpha Project Report']);
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'Beta Meeting Notes']);
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'Gamma Design Doc']);

    $response = $this->getJson('/api/tenant/v1/crm/documents?search=Project');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alpha Project Report');
});

it('can filter by status', function () {
    Document::factory()->create(['owner_id' => $this->user->id, 'status' => DocumentStatusEnum::DRAFT]);
    Document::factory()->create(['owner_id' => $this->user->id, 'status' => DocumentStatusEnum::PUBLISHED]);
    Document::factory()->create(['owner_id' => $this->user->id, 'status' => DocumentStatusEnum::PUBLISHED]);

    $response = $this->getJson('/api/tenant/v1/crm/documents?status=published');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('can filter by mime_type', function () {
    Document::factory()->create(['owner_id' => $this->user->id, 'mime_type' => 'application/pdf']);
    Document::factory()->create(['owner_id' => $this->user->id, 'mime_type' => 'image/png']);
    Document::factory()->create(['owner_id' => $this->user->id, 'mime_type' => 'image/png']);

    $response = $this->getJson('/api/tenant/v1/crm/documents?mime_type=image/png');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('can sort documents', function () {
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'C Document']);
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'A Document']);
    Document::factory()->create(['owner_id' => $this->user->id, 'name' => 'B Document']);

    $response = $this->getJson('/api/tenant/v1/crm/documents?sort_by=name&sort_order=asc');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'A Document')
        ->assertJsonPath('data.1.name', 'B Document')
        ->assertJsonPath('data.2.name', 'C Document');
});

it('records timeline event on document creation', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $this->postJson('/api/tenant/v1/crm/documents', [
        'folder_id' => $folder->id,
        'name' => 'Timeline Doc',
        'file_name' => 'timeline.pdf',
        'file_path' => '/uploads/timeline.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 512,
        'extension' => 'pdf',
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'document.created',
    ]);
});

it('records timeline event on document update', function () {
    $document = Document::factory()->create(['owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/documents/{$document->id}", [
        'name' => 'Updated Timeline Doc',
    ]);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'document.updated',
    ]);
});
