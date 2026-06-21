<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\CentralUser;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'invoices.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'invoices.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'subscriptions.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'subscriptions.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'subscriptions.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'subscriptions.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'coupons.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'payments.read', 'guard_name' => 'central-api']);
});

it('prevents duplicate invoices for same subscription within same period', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('invoices.list', 'invoices.create', 'invoices.read');
    $this->actingAs($user, 'central-api');

    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson('/api/central/v1/invoices', [
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 100.00,
        'status' => 'pending',
    ])->assertCreated();

    $this->postJson('/api/central/v1/invoices', [
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 100.00,
        'status' => 'pending',
    ])->assertCreated();
});

it('prevents negative totals on invoices', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('invoices.list', 'invoices.create');
    $this->actingAs($user, 'central-api');

    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/central/v1/invoices', [
        'tenant_id' => $tenant->id,
        'amount' => -100.00,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('prevents invalid subscription status transitions', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('subscriptions.update', 'subscriptions.list', 'subscriptions.read');
    $this->actingAs($user, 'central-api');

    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::TRIAL,
    ]);

    $response = $this->putJson("/api/central/v1/subscriptions/{$subscription->id}", [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'starts_at' => $subscription->starts_at->toDateTimeString(),
        'billing_cycle' => $subscription->billing_cycle->value,
        'status' => 'suspended',
    ]);

    $response->assertStatus(422);
});

it('validates coupon code before applying', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('coupons.list', 'coupons.read');
    $this->actingAs($user, 'central-api');

    $response = $this->postJson('/api/central/v1/coupons/validate', [
        'code' => 'INVALID-CODE-12345',
        'amount' => 100,
    ]);

    $response->assertJsonPath('data.valid', false);
});

it('prevents applying expired coupon', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('coupons.list', 'coupons.read');
    $this->actingAs($user, 'central-api');

    Coupon::factory()->create([
        'code' => 'EXPIRED',
        'is_active' => true,
        'starts_at' => now()->subDays(30),
        'expires_at' => now()->subDays(1),
    ]);

    $response = $this->postJson('/api/central/v1/coupons/validate', [
        'code' => 'EXPIRED',
        'amount' => 100,
    ]);

    $response->assertJsonPath('data.valid', false);
});

it('prevents duplicate payments', function () {
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('payments.create', 'payments.list', 'payments.read');
    $this->actingAs($user, 'central-api');

    $tenant = Tenant::factory()->create();
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'pending',
    ]);

    $this->postJson('/api/central/v1/payments', [
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'amount' => 100.00,
    ])->assertCreated();
});
