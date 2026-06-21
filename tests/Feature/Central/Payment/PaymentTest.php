<?php

use App\Models\CentralUser;
use App\Models\Invoice;
use App\Models\Payment;
use Spatie\Permission\Models\Permission;

function paymentAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('payments.list');
    $user->givePermissionTo('payments.read');
    $user->givePermissionTo('payments.create');
    $user->givePermissionTo('payments.update');
    $user->givePermissionTo('payments.delete');
    $user->givePermissionTo('payments.restore');
    $user->givePermissionTo('payments.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'payments.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.force.delete', 'guard_name' => 'central-api']);
});

it('lists payments', function () {
    Payment::factory()->count(3)->create();

    $this->actingAs(paymentAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/payments');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();
    $tenant = $invoice->tenant;

    $response = $this->postJson('/api/central/v1/payments', [
        'invoice_id' => $invoice->id,
        'tenant_id' => $tenant->id,
        'amount' => 100.00,
        'currency' => 'USD',
        'gateway' => 'stripe',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('payments', [
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'pending',
    ]);
});

it('shows a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create();

    $response = $this->getJson("/api/central/v1/payments/{$payment->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create();

    $this->putJson("/api/central/v1/payments/{$payment->id}", [
        'transaction_id' => 'txn_updated',
    ])->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'transaction_id' => 'txn_updated']);
});

it('deletes a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create();

    $this->deleteJson("/api/central/v1/payments/{$payment->id}")->assertSuccessful();

    $this->assertSoftDeleted('payments', ['id' => $payment->id]);
});

it('restores a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create();
    $payment->delete();

    $this->postJson("/api/central/v1/payments/{$payment->id}/restore")->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'deleted_at' => null]);
});

it('force deletes a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create();
    $payment->delete();

    $this->deleteJson("/api/central/v1/payments/{$payment->id}/force")->assertSuccessful();

    $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
});

it('completes a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->postJson("/api/central/v1/payments/{$payment->id}/complete", [
        'transaction_id' => 'txn_'.fake()->uuid(),
    ])->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'completed']);
});

it('fails a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->postJson("/api/central/v1/payments/{$payment->id}/fail")->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
});

it('refunds a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $payment = Payment::factory()->create(['status' => 'completed']);

    $this->postJson("/api/central/v1/payments/{$payment->id}/refund")->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded']);
});

it('requires authentication for payments', function () {
    $this->getJson('/api/central/v1/payments')->assertStatus(401);
});

it('validates required fields when creating a payment', function () {
    $this->actingAs(paymentAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/payments', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id', 'amount']);
});
