<?php

use App\Models\CentralUser;
use Spatie\Permission\Models\Permission;

function dashboardAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('tenants.list');
    $user->givePermissionTo('users.list');
    $user->givePermissionTo('plans.read');
    $user->givePermissionTo('subscriptions.list');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'tenants.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'users.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'plans.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'subscriptions.list', 'guard_name' => 'central-api']);
});

it('returns dashboard stats', function () {
    $this->actingAs(dashboardAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'stats' => [
                    'tenants' => ['total', 'active', 'trial', 'suspended', 'expired'],
                    'users' => ['total', 'active'],
                    'plans' => ['total', 'active'],
                    'features' => ['total', 'active'],
                    'subscriptions' => ['total', 'active'],
                    'revenue' => ['mrr', 'arr'],
                ],
                'recent_activity' => ['new_tenants', 'new_subscriptions'],
            ],
        ]);
});

it('requires authentication', function () {
    $this->getJson('/api/central/v1/dashboard')->assertStatus(401);
});
