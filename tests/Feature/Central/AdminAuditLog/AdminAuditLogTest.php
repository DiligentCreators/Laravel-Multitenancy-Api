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
});

it('logs an audit entry', function () {
    $log = AdminAuditLog::create([
        'central_user_id' => $this->user->id,
        'action' => 'login',
        'ip_address' => '127.0.0.1',
        'context' => ['browser' => 'Chrome'],
    ]);

    expect($log->action)->toBe('login')
        ->and($log->central_user_id)->toBe($this->user->id);
});

it('logs different action types', function () {
    $actions = ['login', 'logout', 'impersonate_start', 'impersonate_stop', 'config_change', 'data_export'];

    foreach ($actions as $action) {
        $log = AdminAuditLog::create([
            'central_user_id' => $this->user->id,
            'action' => $action,
            'context' => ['test' => true],
        ]);

        expect($log->action)->toBe($action);
    }
});

it('belongs to a central user', function () {
    $log = AdminAuditLog::factory()->create([
        'central_user_id' => $this->user->id,
    ]);

    expect($log->centralUser->id)->toBe($this->user->id);
});

it('stores ip address and user agent', function () {
    $log = AdminAuditLog::create([
        'central_user_id' => $this->user->id,
        'action' => 'login',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0 Test Browser',
        'context' => [],
    ]);

    expect($log->ip_address)->toBe('192.168.1.1')
        ->and($log->user_agent)->toBe('Mozilla/5.0 Test Browser');
});

it('stores JSON context', function () {
    $context = ['tenant_id' => 'test-123', 'changes' => ['field' => 'value']];

    $log = AdminAuditLog::create([
        'central_user_id' => $this->user->id,
        'action' => 'config_change',
        'context' => $context,
    ]);

    expect($log->context)->toBe($context);
});
