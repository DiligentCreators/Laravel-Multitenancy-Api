<?php

use App\Models\CentralUser;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

function auditLogAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('users.list');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'users.list', 'guard_name' => 'central-api']);
});

it('lists audit logs', function () {
    Activity::create([
        'log_name' => 'audit',
        'description' => 'Updated tenant settings',
        'subject_type' => 'App\Models\Tenant',
        'subject_id' => 1,
        'causer_type' => 'App\Models\CentralUser',
        'causer_id' => 1,
        'properties' => json_encode([
            'old' => ['timezone' => 'UTC'],
            'attributes' => ['timezone' => 'America/New_York'],
        ]),
    ]);

    $this->actingAs(auditLogAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/audit-logs')
        ->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['current_page', 'data'],
        ]);
});

it('requires authentication for audit logs', function () {
    $this->getJson('/api/central/v1/audit-logs')->assertStatus(401);
});
