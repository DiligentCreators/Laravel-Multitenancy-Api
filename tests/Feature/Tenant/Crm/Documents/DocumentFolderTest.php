<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\DocumentFolder;
use App\Models\Crm\FeatureDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

function dfTenant(): Tenant
{
    $domain = 'df-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function dfUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedDfPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "documents.{$action}", 'guard_name' => 'tenant-api']);
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDfPermissions();
    FeatureDefinition::create(['key' => 'documents.upload', 'name' => 'Documents Upload', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    FeatureDefinition::create(['key' => 'documents.storage_mb', 'name' => 'Documents Storage MB', 'type' => 'number', 'default_value' => 100, 'is_usage_limit' => true]);
    $this->tenant = dfTenant();
    $this->user = dfUser($this->tenant, ['documents.view', 'documents.create', 'documents.update', 'documents.delete']);
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

it('can list document folders', function () {
    DocumentFolder::factory()->count(3)->create(['owner_id' => $this->user->id]);

    $response = $this->getJson('/api/tenant/v1/crm/document-folders');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can create a document folder', function () {
    $response = $this->postJson('/api/tenant/v1/crm/document-folders', [
        'name' => 'Invoices',
        'description' => 'All invoices',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Invoices')
        ->assertJsonPath('data.description', 'All invoices');
});

it('can show a document folder', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/document-folders/{$folder->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $folder->id);
});

it('can update a document folder', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->putJson("/api/tenant/v1/crm/document-folders/{$folder->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

it('can delete a document folder', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->deleteJson("/api/tenant/v1/crm/document-folders/{$folder->id}");

    $response->assertOk();
    $this->assertSoftDeleted($folder);
});

it('can create a subfolder', function () {
    $parent = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->postJson('/api/tenant/v1/crm/document-folders', [
        'name' => 'Subfolder',
        'parent_id' => $parent->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('can list root folders by parent_id filter', function () {
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'Root A', 'parent_id' => null]);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'Root B', 'parent_id' => null]);
    $parent = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'Child', 'parent_id' => $parent->id]);

    $response = $this->getJson('/api/tenant/v1/crm/document-folders?parent_id=null');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can move a folder to a different parent', function () {
    $parent = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->putJson("/api/tenant/v1/crm/document-folders/{$folder->id}/move", [
        'parent_id' => $parent->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('can restore a document folder', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);
    $folder->delete();

    $response = $this->postJson("/api/tenant/v1/crm/document-folders/{$folder->id}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted($folder);
});

it('soft deletes a document folder', function () {
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $this->deleteJson("/api/tenant/v1/crm/document-folders/{$folder->id}");

    $this->assertSoftDeleted($folder);
});

it('returns 404 when document folder not found', function () {
    $response = $this->getJson('/api/tenant/v1/crm/document-folders/99999');

    $response->assertNotFound();
});

it('returns 422 with invalid data', function () {
    $response = $this->postJson('/api/tenant/v1/crm/document-folders', []);

    $response->assertUnprocessable();
});

it('returns 401 when unauthenticated', function () {
    $this->app->make('auth')->guard('tenant-api')->forgetUser();

    $response = $this->getJson('/api/tenant/v1/crm/document-folders');

    $response->assertUnauthorized();
});

it('prevents cross-tenant access', function () {
    $otherTenant = dfTenant();
    $folder = null;
    tenancy()->initialize($otherTenant);
    $folder = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/api/tenant/v1/crm/document-folders/{$folder->id}");

    $response->assertNotFound();
});

it('can search document folders', function () {
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'Important Documents']);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'Miscellaneous']);

    $response = $this->getJson('/api/tenant/v1/crm/document-folders?search=Important');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter by parent_id', function () {
    $parent = DocumentFolder::factory()->create(['owner_id' => $this->user->id]);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'parent_id' => $parent->id]);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id]);

    $response = $this->getJson("/api/tenant/v1/crm/document-folders?parent_id={$parent->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can sort document folders', function () {
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'B Folder', 'sort_order' => 1]);
    DocumentFolder::factory()->create(['owner_id' => $this->user->id, 'name' => 'A Folder', 'sort_order' => 0]);

    $response = $this->getJson('/api/tenant/v1/crm/document-folders?sort_by=name&sort_order=asc');

    $response->assertOk();
    $this->assertEquals('A Folder', $response->json('data')[0]['name']);
    $this->assertEquals('B Folder', $response->json('data')[1]['name']);
});
