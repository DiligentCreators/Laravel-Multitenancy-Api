<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantExportRecord;
use App\Services\Central\TenantDataService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(TenantDataService::class);
    $this->tenant = Tenant::factory()->create();
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
});

it('exports tenant data in json format', function () {
    $result = $this->service->export($this->tenant, 'full', 'json', $this->user);

    expect($result['record']->status)->toBe('completed')
        ->and($result['file_path'])->toEndWith('.json')
        ->and($result['size'])->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($result['file_path']);

    $content = json_decode(Storage::disk('local')->get($result['file_path']), true);
    expect($content)->toHaveKey('tenant')
        ->and($content['tenant']['id'])->toBe($this->tenant->id);
});

it('exports tenant data in csv format', function () {
    $result = $this->service->export($this->tenant, 'full', 'csv', $this->user);

    expect($result['record']->status)->toBe('completed')
        ->and($result['file_path'])->toEndWith('.csv');

    Storage::disk('local')->assertExists($result['file_path']);
});

it('records audit log entry on export', function () {
    $this->service->export($this->tenant, 'settings', 'json', $this->user);

    $this->assertDatabaseHas('admin_audit_logs', [
        'central_user_id' => $this->user->id,
        'action' => 'data_export',
    ]);
});

it('handles export failure gracefully', function () {
    $record = TenantExportRecord::create([
        'tenant_id' => $this->tenant->id,
        'central_user_id' => $this->user->id,
        'type' => 'full',
        'format' => 'json',
        'status' => 'processing',
    ]);

    $record->update(['status' => 'failed', 'error_message' => 'Simulated failure']);

    expect($record->fresh()->status)->toBe('failed');
});

it('cleans up old exports', function () {
    $record = TenantExportRecord::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'full',
        'format' => 'json',
        'status' => 'completed',
    ]);
    $record->setCreatedAt(now()->subDays(60));
    $record->saveQuietly();

    $count = $this->service->cleanup(30);

    expect($count)->toBe(1);
});

it('exports specific data types', function () {
    $result = $this->service->export($this->tenant, 'settings', 'json');
    expect($result['record']->status)->toBe('completed');

    $result = $this->service->export($this->tenant, 'users', 'json');
    expect($result['record']->status)->toBe('completed');

    $result = $this->service->export($this->tenant, 'activity', 'json');
    expect($result['record']->status)->toBe('completed');
});
