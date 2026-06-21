<?php

use App\Models\CentralUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('returns dashboard analytics', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'status', 'message', 'data' => [
                'stats' => ['tenants', 'revenue', 'subscriptions'],
                'module_usage',
                'recent_activity',
            ],
        ]);
});

it('includes module usage stats', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.module_usage', fn ($v) => is_array($v));
});

it('includes revenue data', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/dashboard');

    $response->assertOk();
    $revenue = $response->json('data.stats.revenue');
    expect($revenue)->toHaveKey('mrr')
        ->and($revenue)->toHaveKey('arr');
});
