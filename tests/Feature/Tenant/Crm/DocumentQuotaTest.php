<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Document;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\PlanFeature;
use App\Models\Crm\UsageCounter;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\DocumentStorageService;
use App\Services\Crm\FeatureGateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('documents');

    $domain = 'quota-test-'.uniqid().'.localhost';
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

    Permission::firstOrCreate(['name' => 'documents.create', 'guard_name' => 'tenant-api']);
    Permission::firstOrCreate(['name' => 'documents.view', 'guard_name' => 'tenant-api']);

    $this->user->givePermissionTo('documents.create', 'documents.view');

    $this->actingAs($this->user, 'tenant-api');

    $storageDef = FeatureDefinition::create([
        'key' => 'documents.storage_mb',
        'name' => 'Document Storage',
        'type' => 'integer',
        'default_value' => 0,
        'is_usage_limit' => true,
    ]);

    PlanFeature::create([
        'plan_id' => $plan->id,
        'feature_id' => $storageDef->id,
        'value' => 5,
    ]);

    $uploadDef = FeatureDefinition::create([
        'key' => 'documents.upload',
        'name' => 'Document Upload',
        'type' => 'boolean',
        'default_value' => true,
        'is_usage_limit' => false,
    ]);

    PlanFeature::create([
        'plan_id' => $plan->id,
        'feature_id' => $uploadDef->id,
        'value' => true,
    ]);
});

afterEach(function () {
    tenancy()->end();
});

it('allows upload within storage quota', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1);

    $response = $this->postJson('/api/tenant/v1/crm/documents', [
        'name' => 'Test Document',
        'file' => $file,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('crm_documents', [
        'name' => 'Test Document',
    ]);

    $counter = UsageCounter::where('tenant_id', $this->tenant->id)
        ->where('feature_key', 'documents.storage_mb')
        ->first();

    expect($counter)->not->toBeNull();
    expect($counter->count)->toBe(1);
});

it('rejects upload exceeding storage quota', function () {
    UsageCounter::create([
        'tenant_id' => $this->tenant->id,
        'feature_key' => 'documents.storage_mb',
        'count' => 5,
    ]);

    $file = UploadedFile::fake()->create('large.pdf', 1);

    $response = $this->postJson('/api/tenant/v1/crm/documents', [
        'name' => 'Large Document',
        'file' => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('rejects version upload when storage quota exceeded', function () {
    $document = Document::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Doc',
        'file_name' => 'test.pdf',
        'file_path' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'version' => '1.0',
    ]);

    UsageCounter::create([
        'tenant_id' => $this->tenant->id,
        'feature_key' => 'documents.storage_mb',
        'count' => 5,
    ]);

    $file = UploadedFile::fake()->create('v2.pdf', 1);

    $response = $this->postJson("/api/tenant/v1/crm/documents/{$document->id}/versions", [
        'file' => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('calculates remaining quota correctly', function () {
    $service = app(DocumentStorageService::class);
    $featureGate = app(FeatureGateService::class);

    $remaining = $featureGate->remaining($this->tenant, 'documents.storage_mb');

    expect($remaining)->toBe(5);

    UsageCounter::create([
        'tenant_id' => $this->tenant->id,
        'feature_key' => 'documents.storage_mb',
        'count' => 3,
    ]);

    $featureGate->invalidate($this->tenant, 'documents.storage_mb');

    $remaining = $featureGate->remaining($this->tenant, 'documents.storage_mb');

    expect($remaining)->toBe(2);
});
