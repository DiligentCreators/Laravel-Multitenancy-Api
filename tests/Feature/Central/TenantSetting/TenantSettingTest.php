<?php

use App\Models\CentralUser;
use App\Models\SettingDefinition;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;

function tenantSettingAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('tenant.list');
    $user->givePermissionTo('tenant.update');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'tenant.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tenant.update', 'guard_name' => 'central-api']);
});

it('lists settings for a tenant', function () {
    $this->actingAs(tenantSettingAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();
    SettingDefinition::factory()->create([
        'group' => 'general',
        'key' => 'site_name',
        'type' => 'string',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/central/v1/tenants/{$tenant->id}/settings");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['tenant_id', 'settings'],
        ]);
});

it('updates settings for a tenant', function () {
    $this->actingAs(tenantSettingAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();
    $definition = SettingDefinition::factory()->create([
        'group' => 'general',
        'key' => 'timezone',
        'type' => 'string',
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/central/v1/tenants/{$tenant->id}/settings", [
        'settings' => [
            ['setting_definition_id' => $definition->id, 'value' => 'America/New_York'],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('tenant_settings', [
        'tenant_id' => $tenant->id,
        'setting_definition_id' => $definition->id,
        'value' => 'America/New_York',
    ]);
});

it('requires authentication for tenant settings', function () {
    $this->getJson('/api/central/v1/tenants/1/settings')->assertStatus(401);
});

it('validates settings is required when updating', function () {
    $this->actingAs(tenantSettingAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();

    $this->putJson("/api/central/v1/tenants/{$tenant->id}/settings", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['settings']);
});
