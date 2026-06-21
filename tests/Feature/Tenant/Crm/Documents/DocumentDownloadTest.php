<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Document;
use App\Models\Crm\DocumentVersion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $domain = 'doc-download-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
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

    Permission::firstOrCreate(['name' => 'documents.view', 'guard_name' => 'tenant-api']);
    Permission::firstOrCreate(['name' => 'documents.create', 'guard_name' => 'tenant-api']);

    $this->user->givePermissionTo('documents.view', 'documents.create');

    $this->actingAs($this->user, 'tenant-api');

    $this->document = Document::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Doc',
        'file_name' => 'test.pdf',
        'file_path' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'version' => '1.0',
    ]);

    $this->version = DocumentVersion::create([
        'tenant_id' => $this->tenant->id,
        'document_id' => $this->document->id,
        'version' => '1.0',
        'file_name' => 'test.pdf',
        'file_path' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);
});

afterEach(function () {
    tenancy()->end();
});

it('generates a signed download URL for a document', function () {
    $response = $this->getJson("/api/tenant/v1/crm/documents/{$this->document->id}/download");

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['url']]);

    expect($response->json('data.url'))->toContain('signature');
});

it('generates a signed download URL for a document version', function () {
    $response = $this->getJson("/api/tenant/v1/crm/documents/versions/{$this->version->id}/download");

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['url']]);

    expect($response->json('data.url'))->toContain('signature');
});

it('rejects download without documents.view permission', function () {
    $guest = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($guest, 'tenant-api');

    $response = $this->getJson("/api/tenant/v1/crm/documents/{$this->document->id}/download");

    $response->assertStatus(403);
});

it('rejects serve with invalid signature', function () {
    $response = $this->getJson("/api/tenant/v1/crm/documents/{$this->document->id}/serve?signature=invalid");

    $response->assertStatus(401);
});

it('rejects version serve with invalid signature', function () {
    $response = $this->getJson("/api/tenant/v1/crm/documents/versions/{$this->version->id}/serve?signature=invalid");

    $response->assertStatus(401);
});
