<?php

use App\Models\CentralUser;
use App\Models\Module;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;

function moduleAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('modules.list');
    $user->givePermissionTo('modules.read');
    $user->givePermissionTo('modules.create');
    $user->givePermissionTo('modules.update');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'modules.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'modules.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'modules.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'modules.update', 'guard_name' => 'central-api']);
});

it('lists modules', function () {
    Module::factory()->count(3)->create();

    $this->actingAs(moduleAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/modules');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['current_page', 'data'],
        ]);

    $this->assertCount(3, $response->json('data.data'));
});

it('shows a module', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $module = Module::factory()->create();

    $this->getJson("/api/central/v1/modules/{$module->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('enables a module', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $module = Module::factory()->create(['is_enabled' => false]);

    $this->postJson("/api/central/v1/modules/{$module->id}/enable")->assertSuccessful();

    $this->assertDatabaseHas('modules', ['id' => $module->id, 'is_enabled' => true]);
});

it('disables a module', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $module = Module::factory()->create(['is_enabled' => true]);

    $this->postJson("/api/central/v1/modules/{$module->id}/disable")->assertSuccessful();

    $this->assertDatabaseHas('modules', ['id' => $module->id, 'is_enabled' => false]);
});

it('enables a module for a tenant', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $module = Module::factory()->create();
    $tenant = Tenant::factory()->create();

    $this->postJson("/api/central/v1/modules/{$module->id}/enable-for-tenant", [
        'tenant_id' => $tenant->id,
    ])->assertSuccessful();

    $this->assertDatabaseHas('tenant_module', [
        'module_id' => $module->id,
        'tenant_id' => $tenant->id,
        'is_enabled' => true,
    ]);
});

it('disables a module for a tenant', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $module = Module::factory()->create();
    $tenant = Tenant::factory()->create();
    $module->enableForTenant($tenant);

    $this->postJson("/api/central/v1/modules/{$module->id}/disable-for-tenant", [
        'tenant_id' => $tenant->id,
    ])->assertSuccessful();

    $this->assertDatabaseHas('tenant_module', [
        'module_id' => $module->id,
        'tenant_id' => $tenant->id,
        'is_enabled' => false,
    ]);
});

it('seeds default modules', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/modules/seed')->assertSuccessful();

    $this->assertDatabaseHas('modules', ['slug' => 'crm-core']);
    $this->assertDatabaseHas('modules', ['slug' => 'solar']);
    $this->assertDatabaseHas('modules', ['slug' => 'agency']);
    $this->assertDatabaseHas('modules', ['slug' => 'real-estate']);
});

it('does not duplicate modules on seed', function () {
    $this->actingAs(moduleAuthUser(), 'central-api');

    Module::create(['name' => 'CRM Core', 'slug' => 'crm-core', 'description' => 'Existing']);

    $this->postJson('/api/central/v1/modules/seed')->assertSuccessful();

    $this->assertDatabaseCount('modules', 4);
});

it('requires authentication for modules', function () {
    $this->getJson('/api/central/v1/modules')->assertStatus(401);
});
