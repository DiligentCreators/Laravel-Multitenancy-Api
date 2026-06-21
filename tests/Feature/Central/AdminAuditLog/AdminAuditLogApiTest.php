<?php

use App\Models\AdminAuditLog;
use App\Models\CentralUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('requires authentication', function () {
    $response = $this->getJson('/api/central/v1/admin-audit-logs');

    $response->assertUnauthorized();
});

it('lists audit logs', function () {
    AdminAuditLog::create([
        'central_user_id' => $this->user->id,
        'action' => 'login',
        'ip_address' => '127.0.0.1',
        'context' => [],
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/admin-audit-logs');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters audit logs by action', function () {
    AdminAuditLog::create(['central_user_id' => $this->user->id, 'action' => 'login', 'context' => []]);
    AdminAuditLog::create(['central_user_id' => $this->user->id, 'action' => 'logout', 'context' => []]);

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/admin-audit-logs?action=login');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.action'))->toBe('login');
});

it('filters by action type', function () {
    AdminAuditLog::create(['central_user_id' => $this->user->id, 'action' => 'login', 'context' => []]);
    AdminAuditLog::create(['central_user_id' => $this->user->id, 'action' => 'logout', 'context' => []]);

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/admin-audit-logs?action=login');

    expect($response->json('data'))->not->toBeNull();
});

it('paginates audit logs', function () {
    AdminAuditLog::factory()->count(5)->create(['central_user_id' => $this->user->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/admin-audit-logs?per_page=2');

    expect($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.per_page'))->toBe(2);
});
