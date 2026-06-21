<?php

use App\Models\CentralUser;
use App\Models\OverageCharge;
use Spatie\Permission\Models\Permission;

function overageChargeAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('overage-charges.list');
    $user->givePermissionTo('overage-charges.read');
    $user->givePermissionTo('overage-charges.update');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'overage-charges.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'overage-charges.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'overage-charges.update', 'guard_name' => 'central-api']);
});

it('lists overage charges', function () {
    OverageCharge::factory()->count(3)->create();

    $this->actingAs(overageChargeAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/overage-charges');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('shows an overage charge', function () {
    $this->actingAs(overageChargeAuthUser(), 'central-api');

    $charge = OverageCharge::factory()->create();

    $response = $this->getJson("/api/central/v1/overage-charges/{$charge->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an overage charge status', function () {
    $this->actingAs(overageChargeAuthUser(), 'central-api');

    $charge = OverageCharge::factory()->create(['status' => 'pending']);

    $response = $this->putJson("/api/central/v1/overage-charges/{$charge->id}", [
        'status' => 'paid',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('overage_charges', ['id' => $charge->id, 'status' => 'paid']);
});

it('requires authentication for overage charges', function () {
    $this->getJson('/api/central/v1/overage-charges')->assertStatus(401);
});
