<?php

use App\Enums\Central\ProrationTypeEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Central\ProrationService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->tenant = Tenant::factory()->create();
    $this->plan = Plan::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
    $this->subscription = Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatusEnum::ACTIVE,
        'starts_at' => now()->subDays(15),
        'ends_at' => now()->addDays(15),
        'billing_cycle' => 'monthly',
    ]);
    $this->service = app(ProrationService::class);
});

it('calculates upgrade proration correctly', function () {
    $newPlan = Plan::factory()->create(['monthly_price' => 200]);
    $result = $this->service->calculateProration(
        $this->subscription,
        $newPlan,
        ProrationTypeEnum::UPGRADE
    );

    expect($result['charge_amount'])->toBeGreaterThan(0)
        ->and($result['credit_amount'])->toBe(0)
        ->and($result['days_remaining'])->toBeGreaterThan(0)
        ->and($result['total_days'])->toBeGreaterThan(0);
});

it('calculates downgrade proration correctly', function () {
    $newPlan = Plan::factory()->create(['monthly_price' => 50]);
    $result = $this->service->calculateProration(
        $this->subscription,
        $newPlan,
        ProrationTypeEnum::DOWNGRADE
    );

    expect($result['credit_amount'])->toBeGreaterThan(0)
        ->and($result['charge_amount'])->toBe(0)
        ->and($result['days_remaining'])->toBeGreaterThan(0);
});

it('records proration and applies credit', function () {
    $prorationData = [
        'credit_amount' => 25,
        'charge_amount' => 0,
        'net_amount' => -25,
        'days_remaining' => 10,
        'total_days' => 30,
    ];

    $record = $this->service->recordProration(
        $this->subscription,
        $prorationData,
        ProrationTypeEnum::DOWNGRADE
    );

    expect((float) $record->credit_amount)->toBe(25.0)
        ->and($record->type)->toBe('downgrade')
        ->and($record->status)->toBe('pending');

    $this->service->applyCredit($record);
    expect($record->fresh()->status)->toBe('applied');

    $record->refresh();
    $tenant = $record->tenant;
    expect((float) $tenant->credit_balance)->toBe(25.0);
});

it('records upgrade proration with charge', function () {
    $prorationData = [
        'credit_amount' => 0,
        'charge_amount' => 50,
        'net_amount' => 50,
        'days_remaining' => 10,
        'total_days' => 30,
    ];

    $record = $this->service->recordProration(
        $this->subscription,
        $prorationData,
        ProrationTypeEnum::UPGRADE
    );

    expect((float) $record->charge_amount)->toBe(50.0)
        ->and($record->type)->toBe('upgrade')
        ->and((float) $record->net_amount)->toBe(50.0);
});
