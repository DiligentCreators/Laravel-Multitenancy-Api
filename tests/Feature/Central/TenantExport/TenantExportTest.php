<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantExportRecord;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->tenant = Tenant::factory()->create();
});

it('exports tenant data via API', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/central/v1/tenants/{$this->tenant->id}/exports", [
            'type' => 'full',
            'format' => 'json',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('shows export history', function () {
    TenantExportRecord::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'full',
        'format' => 'json',
        'status' => 'completed',
        'central_user_id' => $this->user->id,
        'completed_at' => now(),
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/tenants/{$this->tenant->id}/exports");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exports via API with different types', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/central/v1/tenants/{$this->tenant->id}/exports", [
            'type' => 'settings',
            'format' => 'csv',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('requires authentication for exports', function () {
    $response = $this->postJson("/api/central/v1/tenants/{$this->tenant->id}/exports");

    $response->assertUnauthorized();
});
