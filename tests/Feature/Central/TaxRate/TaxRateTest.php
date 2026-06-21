<?php

use App\Models\CentralUser;
use App\Models\TaxRate;
use App\Models\TaxRegion;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->region = TaxRegion::factory()->create();
});

it('lists tax rates', function () {
    TaxRate::factory()->count(3)->create(['tax_region_id' => $this->region->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/central/v1/tax-rates');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a tax rate', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/central/v1/tax-rates', [
            'tax_region_id' => $this->region->id,
            'name' => 'Standard VAT',
            'rate' => 20.00,
            'type' => 'percentage',
            'is_active' => true,
        ]);

    $response->assertCreated();
    expect((float) $response->json('data.rate'))->toBe(20.0);

    $this->assertDatabaseHas('tax_rates', [
        'tax_region_id' => $this->region->id,
        'rate' => 20.00,
    ]);
});

it('validates tax rate max value', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/central/v1/tax-rates', [
            'tax_region_id' => $this->region->id,
            'name' => 'Too High',
            'rate' => 150,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('rate');
});

it('shows a tax rate with region', function () {
    $rate = TaxRate::factory()->create(['tax_region_id' => $this->region->id]);

    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/tax-rates/{$rate->id}");

    $response->assertOk()
        ->assertJsonPath('data.tax_region.id', $this->region->id);
});

it('updates a tax rate', function () {
    $rate = TaxRate::factory()->create(['tax_region_id' => $this->region->id]);

    $response = $this->withToken($this->token)
        ->putJson("/api/central/v1/tax-rates/{$rate->id}", [
            'rate' => 15.50,
        ]);

    $response->assertOk();
    expect((float) $response->json('data.rate'))->toBe(15.5);
});

it('deletes a tax rate', function () {
    $rate = TaxRate::factory()->create(['tax_region_id' => $this->region->id]);

    $response = $this->withToken($this->token)
        ->deleteJson("/api/central/v1/tax-rates/{$rate->id}");

    $response->assertOk();
    $this->assertSoftDeleted($rate);
});

it('validates effective dates', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/central/v1/tax-rates', [
            'tax_region_id' => $this->region->id,
            'name' => 'Test',
            'rate' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => '2025-01-01',
        ]);

    $response->assertStatus(422);
});
