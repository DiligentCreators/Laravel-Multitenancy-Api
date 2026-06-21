<?php

use App\Models\CentralUser;
use App\Models\Coupon;
use Spatie\Permission\Models\Permission;

function couponAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('coupons.list');
    $user->givePermissionTo('coupons.read');
    $user->givePermissionTo('coupons.create');
    $user->givePermissionTo('coupons.update');
    $user->givePermissionTo('coupons.delete');
    $user->givePermissionTo('coupons.restore');
    $user->givePermissionTo('coupons.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'coupons.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.force.delete', 'guard_name' => 'central-api']);
});

it('lists coupons', function () {
    Coupon::factory()->count(3)->create();

    $this->actingAs(couponAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/coupons')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/coupons', [
        'code' => 'SAVE20',
        'type' => 'percentage',
        'amount' => 20.00,
        'usage_limit' => 100,
        'starts_at' => now()->toDateString(),
        'expires_at' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('coupons', ['code' => 'SAVE20']);
});

it('shows a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $coupon = Coupon::factory()->create();

    $this->getJson("/api/central/v1/coupons/{$coupon->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $coupon = Coupon::factory()->create(['amount' => 10.00]);

    $this->putJson("/api/central/v1/coupons/{$coupon->id}", ['amount' => 25.00])
        ->assertSuccessful();

    $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'amount' => 25.00]);
});

it('deletes a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $coupon = Coupon::factory()->create();
    $this->deleteJson("/api/central/v1/coupons/{$coupon->id}")->assertSuccessful();

    $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
});

it('restores a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $coupon = Coupon::factory()->create();
    $coupon->delete();

    $this->postJson("/api/central/v1/coupons/{$coupon->id}/restore")->assertSuccessful();

    $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'deleted_at' => null]);
});

it('force deletes a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $coupon = Coupon::factory()->create();
    $coupon->delete();

    $this->deleteJson("/api/central/v1/coupons/{$coupon->id}/force")->assertSuccessful();

    $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
});

it('validates a coupon code', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    Coupon::factory()->create(['code' => 'VALID10']);

    $response = $this->postJson('/api/central/v1/coupons/validate', [
        'code' => 'VALID10',
        'amount' => 50.00,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.valid', true);
});

it('applies a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    Coupon::factory()->create(['code' => 'DISCOUNT', 'type' => 'fixed', 'amount' => 10.00]);

    $response = $this->postJson('/api/central/v1/coupons/apply', [
        'code' => 'DISCOUNT',
        'amount' => 100.00,
    ]);

    $response->assertSuccessful();
});

it('requires authentication for coupons', function () {
    $this->getJson('/api/central/v1/coupons')->assertStatus(401);
});

it('validates required fields when creating a coupon', function () {
    $this->actingAs(couponAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/coupons', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'type', 'amount']);
});
