<?php

use App\Models\CentralUser;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;

function invoiceAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('invoices.list');
    $user->givePermissionTo('invoices.read');
    $user->givePermissionTo('invoices.create');
    $user->givePermissionTo('invoices.update');
    $user->givePermissionTo('invoices.delete');
    $user->givePermissionTo('invoices.restore');
    $user->givePermissionTo('invoices.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'invoices.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.force.delete', 'guard_name' => 'central-api']);
});

it('lists invoices', function () {
    Invoice::factory()->count(3)->create();

    $this->actingAs(invoiceAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/invoices');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson('/api/central/v1/invoices', [
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 100.00,
        'currency' => 'USD',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('invoices', [
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'draft',
    ]);
});

it('shows an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();

    $response = $this->getJson("/api/central/v1/invoices/{$invoice->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();

    $response = $this->putJson("/api/central/v1/invoices/{$invoice->id}", [
        'amount' => 200.00,
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'amount' => 200.00]);
});

it('deletes an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();

    $response = $this->deleteJson("/api/central/v1/invoices/{$invoice->id}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
});

it('restores an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();
    $invoice->delete();

    $response = $this->postJson("/api/central/v1/invoices/{$invoice->id}/restore");

    $response->assertSuccessful();

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
});

it('force deletes an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create();
    $invoice->delete();

    $response = $this->deleteJson("/api/central/v1/invoices/{$invoice->id}/force");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
});

it('marks an invoice as paid', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create(['status' => 'pending']);

    $response = $this->postJson("/api/central/v1/invoices/{$invoice->id}/mark-paid");

    $response->assertSuccessful();

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
});

it('marks an invoice as overdue', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $invoice = Invoice::factory()->create(['status' => 'pending']);

    $response = $this->postJson("/api/central/v1/invoices/{$invoice->id}/mark-overdue");

    $response->assertSuccessful();

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'overdue']);
});

it('requires authentication for invoices', function () {
    $this->getJson('/api/central/v1/invoices')->assertStatus(401);
});

it('validates required fields when creating an invoice', function () {
    $this->actingAs(invoiceAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/invoices', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id', 'amount']);
});
