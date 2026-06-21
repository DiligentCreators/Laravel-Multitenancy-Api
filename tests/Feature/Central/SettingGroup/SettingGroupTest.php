<?php

use App\Models\CentralUser;
use App\Models\SettingGroup;
use Spatie\Permission\Models\Permission;

function settingGroupAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('settings-groups.list');
    $user->givePermissionTo('settings-groups.create');
    $user->givePermissionTo('settings-groups.read');
    $user->givePermissionTo('settings-groups.update');
    $user->givePermissionTo('settings-groups.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'settings-groups.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings-groups.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings-groups.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings-groups.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings-groups.delete', 'guard_name' => 'central-api']);
});

it('lists setting groups', function () {
    SettingGroup::factory()->count(3)->create();

    $this->actingAs(settingGroupAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/settings-groups');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a setting group', function () {
    $this->actingAs(settingGroupAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/settings-groups', [
        'name' => 'Test Group',
        'slug' => 'test-group',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('settings_groups', ['slug' => 'test-group']);
});

it('shows a setting group', function () {
    $this->actingAs(settingGroupAuthUser(), 'central-api');

    $group = SettingGroup::factory()->create();

    $response = $this->getJson("/api/central/v1/settings-groups/{$group->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a setting group', function () {
    $this->actingAs(settingGroupAuthUser(), 'central-api');

    $group = SettingGroup::factory()->create();

    $response = $this->putJson("/api/central/v1/settings-groups/{$group->id}", [
        'name' => 'Updated Group',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('settings_groups', ['id' => $group->id, 'name' => 'Updated Group']);
});

it('deletes a setting group', function () {
    $this->actingAs(settingGroupAuthUser(), 'central-api');

    $group = SettingGroup::factory()->create();

    $response = $this->deleteJson("/api/central/v1/settings-groups/{$group->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('settings_groups', ['id' => $group->id]);
});

it('requires authentication for setting groups', function () {
    $this->getJson('/api/central/v1/settings-groups')->assertStatus(401);
});
