<?php

use App\Models\CentralUser;
use App\Models\TaxRegion;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('lists tax regions', function () {
    TaxRegion::factory()->count(3)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/tax-regions');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a tax region', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/central/v1/tax-regions', [
            'name' => 'United States',
            'code' => 'US',
            'description' => 'US tax region',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'US');

    $this->assertDatabaseHas('tax_regions', ['code' => 'US']);
});

it('validates unique region code', function () {
    TaxRegion::factory()->create(['code' => 'US']);

    $response = $this->withToken($this->token)
        ->postJson('/api/central/v1/tax-regions', [
            'name' => 'United States',
            'code' => 'US',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('shows a tax region with rates', function () {
    $region = TaxRegion::factory()->create();
    $region->taxRates()->create([
        'name' => 'VAT',
        'rate' => 20,
        'type' => 'percentage',
        'is_active' => true,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/tax-regions/{$region->id}");

    $response->assertOk()
        ->assertJsonPath('data.code', $region->code);
    expect($response->json('data'))->toHaveKey('tax_rates');
});

it('updates a tax region', function () {
    $region = TaxRegion::factory()->create();

    $response = $this->withToken($this->token)
        ->putJson("/api/central/v1/tax-regions/{$region->id}", [
            'name' => 'Updated Region',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Region');
});

it('deletes a tax region', function () {
    $region = TaxRegion::factory()->create();

    $response = $this->withToken($this->token)
        ->deleteJson("/api/central/v1/tax-regions/{$region->id}");

    $response->assertOk();
    $this->assertSoftDeleted($region);
});
