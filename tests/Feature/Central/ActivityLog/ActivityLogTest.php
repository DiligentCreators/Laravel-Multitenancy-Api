<?php

use App\Models\CentralUser;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

function activityLogAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('users.list');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'users.list', 'guard_name' => 'central-api']);
});

it('lists activity logs', function () {
    Activity::create([
        'log_name' => 'default',
        'description' => 'Test activity',
        'subject_type' => 'App\Models\Tenant',
        'subject_id' => 1,
        'causer_type' => 'App\Models\CentralUser',
        'causer_id' => 1,
    ]);

    $this->actingAs(activityLogAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/activity-logs')
        ->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['current_page', 'data'],
        ]);
});

it('shows a single activity log', function () {
    $activity = Activity::create([
        'log_name' => 'default',
        'description' => 'View single activity',
        'subject_type' => 'App\Models\Tenant',
        'subject_id' => 1,
        'causer_type' => 'App\Models\CentralUser',
        'causer_id' => 1,
    ]);

    $this->actingAs(activityLogAuthUser(), 'central-api');

    $this->getJson("/api/central/v1/activity-logs/{$activity->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('requires authentication for activity logs', function () {
    $this->getJson('/api/central/v1/activity-logs')->assertStatus(401);
});
