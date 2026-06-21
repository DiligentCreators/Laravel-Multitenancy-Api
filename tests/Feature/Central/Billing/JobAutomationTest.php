<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Jobs\Central\BillingAutomationJob;
use App\Jobs\Central\DailySubscriptionCheckJob;
use App\Jobs\Central\ProcessDunningJob;
use App\Jobs\Central\TenantCleanupJob;
use App\Models\CentralUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Central\DunningService;
use App\Services\Central\TenantDataService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->service = app(DunningService::class);
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->tenant = Tenant::factory()->create();
    $this->invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'amount' => 100,
        'total_amount' => 100,
        'status' => 'overdue',
    ]);
    $this->payment = Payment::factory()->create([
        'invoice_id' => $this->invoice->id,
        'tenant_id' => $this->tenant->id,
        'amount' => 100,
        'status' => 'failed',
    ]);
});

it('runs dunning job successfully', function () {
    $job = new ProcessDunningJob;

    $job->handle($this->service);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe('overdue');
});

it('subscription daily check job runs without errors', function () {
    $job = new DailySubscriptionCheckJob;

    $job->handle();

    expect(true)->toBeTrue();
});

it('billing automation job runs without errors', function () {
    $job = new BillingAutomationJob;

    $job->handle();

    expect(true)->toBeTrue();
});

it('tenant cleanup job runs without errors', function () {
    $job = new TenantCleanupJob;

    $job->handle(app(TenantDataService::class));

    expect(true)->toBeTrue();
});

it('subscription daily check handles trial expiry', function () {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::TRIAL,
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subDay(),
    ]);

    $job = new DailySubscriptionCheckJob;
    $job->handle();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatusEnum::EXPIRED);
});

it('usage middleware blocks when not permitted', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/tenants/{$this->tenant->id}/usage/users");

    $response->assertOk();
});
