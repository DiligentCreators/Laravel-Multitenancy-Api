<?php

use App\Models\ApiKey;
use App\Models\CentralUser;
use Spatie\Permission\Models\Permission;

function apiKeyAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('api-keys.list');
    $user->givePermissionTo('api-keys.read');
    $user->givePermissionTo('api-keys.create');
    $user->givePermissionTo('api-keys.update');
    $user->givePermissionTo('api-keys.delete');
    $user->givePermissionTo('api-keys.restore');
    $user->givePermissionTo('api-keys.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'api-keys.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'api-keys.force.delete', 'guard_name' => 'central-api']);
});

it('lists api keys', function () {
    ApiKey::factory()->count(3)->create();

    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/api-keys')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/api-keys', [
        'name' => 'Production API Key',
        'permissions' => ['read', 'write'],
        'expires_at' => now()->addYear()->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('api_keys', ['name' => 'Production API Key']);
});

it('shows an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();

    $this->getJson("/api/central/v1/api-keys/{$apiKey->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/central/v1/api-keys/{$apiKey->id}", ['name' => 'New Name'])
        ->assertSuccessful();

    $this->assertDatabaseHas('api_keys', ['id' => $apiKey->id, 'name' => 'New Name']);
});

it('deletes an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();
    $this->deleteJson("/api/central/v1/api-keys/{$apiKey->id}")->assertSuccessful();

    $this->assertSoftDeleted('api_keys', ['id' => $apiKey->id]);
});

it('restores an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();
    $apiKey->delete();

    $this->postJson("/api/central/v1/api-keys/{$apiKey->id}/restore")->assertSuccessful();

    $this->assertDatabaseHas('api_keys', ['id' => $apiKey->id, 'deleted_at' => null]);
});

it('force deletes an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();
    $apiKey->delete();

    $this->deleteJson("/api/central/v1/api-keys/{$apiKey->id}/force")->assertSuccessful();

    $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id]);
});

it('regenerates an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();
    $originalKey = $apiKey->key;

    $this->postJson("/api/central/v1/api-keys/{$apiKey->id}/regenerate")->assertSuccessful();

    $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id, 'key' => $originalKey]);
});

it('revokes an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $apiKey = ApiKey::factory()->create();
    $this->postJson("/api/central/v1/api-keys/{$apiKey->id}/revoke")->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('requires authentication for api keys', function () {
    $this->getJson('/api/central/v1/api-keys')->assertStatus(401);
});

it('validates required fields when creating an api key', function () {
    $this->actingAs(apiKeyAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/api-keys', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
