<?php

use App\Models\CentralUser;
use App\Models\Setting;
use App\Models\SettingGroup;
use Spatie\Permission\Models\Permission;

function settingAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('settings.list');
    $user->givePermissionTo('settings.create');
    $user->givePermissionTo('settings.read');
    $user->givePermissionTo('settings.update');
    $user->givePermissionTo('settings.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'settings.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'settings.delete', 'guard_name' => 'central-api']);
});

it('lists system settings', function () {
    $group = SettingGroup::factory()->create();
    Setting::factory()->count(3)->create(['group_id' => $group->id]);

    $this->actingAs(settingAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/system-settings');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a system setting', function () {
    $this->actingAs(settingAuthUser(), 'central-api');

    $group = SettingGroup::factory()->create();

    $response = $this->postJson('/api/central/v1/system-settings', [
        'group_id' => $group->id,
        'key' => 'test_key',
        'label' => 'Test Setting',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('system_settings', ['key' => 'test_key']);
});

it('shows a system setting', function () {
    $this->actingAs(settingAuthUser(), 'central-api');

    $setting = Setting::factory()->create();

    $response = $this->getJson("/api/central/v1/system-settings/{$setting->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a system setting', function () {
    $this->actingAs(settingAuthUser(), 'central-api');

    $setting = Setting::factory()->create();

    $response = $this->putJson("/api/central/v1/system-settings/{$setting->id}", [
        'label' => 'Updated Label',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('system_settings', ['id' => $setting->id, 'label' => 'Updated Label']);
});

it('deletes a system setting', function () {
    $this->actingAs(settingAuthUser(), 'central-api');

    $setting = Setting::factory()->create();

    $response = $this->deleteJson("/api/central/v1/system-settings/{$setting->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('system_settings', ['id' => $setting->id]);
});

it('requires authentication for system settings', function () {
    $this->getJson('/api/central/v1/system-settings')->assertStatus(401);
});
