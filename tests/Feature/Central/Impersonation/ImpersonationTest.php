<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;

function impersonationAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('tenants.read');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'tenants.read', 'guard_name' => 'central-api']);
});

it('starts impersonation for a tenant', function () {
    $this->actingAs(impersonationAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();

    $response = $this->postJson("/api/central/v1/impersonation/start/{$tenant->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'token',
                'type',
                'tenant' => ['id', 'company_name'],
                'user' => ['id', 'name', 'email'],
            ],
        ]);
});

it('fails when tenant has no users', function () {
    $this->actingAs(impersonationAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();
    $tenant->users()->delete();

    $response = $this->postJson("/api/central/v1/impersonation/start/{$tenant->id}");

    $response->assertStatus(404);
});

it('stops impersonation', function () {
    $this->actingAs(impersonationAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/impersonation/stop')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('requires authentication for impersonation', function () {
    $this->postJson('/api/central/v1/impersonation/start/1')->assertStatus(401);
});
