<?php

use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\TaxRegion;
use App\Services\Central\TaxCalculationService;

beforeEach(function () {
    $this->service = app(TaxCalculationService::class);
});

it('returns zero tax when no regions exist', function () {
    $invoice = Invoice::factory()->create(['amount' => 100]);

    $result = $this->service->calculate($invoice);

    expect($result['tax_amount'])->toBe(0.0)
        ->and($result['rate_applied'])->toBe(0.0)
        ->and($result['region'])->toBeNull();
});

it('calculates tax for default active region', function () {
    $region = TaxRegion::factory()->create(['code' => 'US', 'is_active' => true]);
    TaxRate::factory()->create([
        'tax_region_id' => $region->id,
        'rate' => 10,
        'type' => 'percentage',
        'is_active' => true,
        'effective_from' => now()->subYear(),
    ]);

    $invoice = Invoice::factory()->create(['amount' => 100]);

    $result = $this->service->calculate($invoice);

    expect($result['tax_amount'])->toBe(10.0)
        ->and($result['rate_applied'])->toBe(10.0)
        ->and($result['region']->id)->toBe($region->id);
});

it('picks highest rate when multiple rates exist', function () {
    $region = TaxRegion::factory()->create(['code' => 'CA', 'is_active' => true]);
    TaxRate::factory()->create([
        'tax_region_id' => $region->id,
        'rate' => 5,
        'is_active' => true,
        'effective_from' => now()->subYear(),
    ]);
    TaxRate::factory()->create([
        'tax_region_id' => $region->id,
        'rate' => 8,
        'is_active' => true,
        'effective_from' => now()->subYear(),
    ]);

    $invoice = Invoice::factory()->create(['amount' => 200]);

    $result = $this->service->calculate($invoice);

    expect($result['rate_applied'])->toBe(8.0)
        ->and($result['tax_amount'])->toBe(16.0);
});

it('calculates by region code', function () {
    $usRegion = TaxRegion::factory()->create(['code' => 'US', 'is_active' => true]);
    TaxRate::factory()->create([
        'tax_region_id' => $usRegion->id,
        'rate' => 10,
        'is_active' => true,
        'effective_from' => now()->subYear(),
    ]);

    $euRegion = TaxRegion::factory()->create(['code' => 'DE', 'is_active' => true]);
    TaxRate::factory()->create([
        'tax_region_id' => $euRegion->id,
        'rate' => 19,
        'is_active' => true,
        'effective_from' => now()->subYear(),
    ]);

    $invoice = Invoice::factory()->create(['amount' => 100]);

    $result = $this->service->calculate($invoice, 'DE');

    expect($result['rate_applied'])->toBe(19.0)
        ->and($result['tax_amount'])->toBe(19.0)
        ->and($result['region']->code)->toBe('DE');
});

it('respects effective date ranges', function () {
    $region = TaxRegion::factory()->create(['code' => 'UK', 'is_active' => true]);

    TaxRate::factory()->create([
        'tax_region_id' => $region->id,
        'rate' => 20,
        'is_active' => true,
        'effective_from' => now()->addMonth(),
    ]);

    TaxRate::factory()->create([
        'tax_region_id' => $region->id,
        'rate' => 15,
        'is_active' => true,
        'effective_from' => now()->subYear(),
        'effective_to' => now()->subDay(),
    ]);

    $invoice = Invoice::factory()->create(['amount' => 100]);

    $result = $this->service->calculate($invoice, 'UK');

    expect($result['tax_amount'])->toBe(0.0)
        ->and($result['rate_applied'])->toBe(0.0)
        ->and($result['details'])->toBeEmpty();
});

it('returns default region', function () {
    $region = TaxRegion::factory()->create(['code' => 'US', 'is_active' => true]);

    $default = $this->service->getDefaultRegion();

    expect($default->id)->toBe($region->id);
});
