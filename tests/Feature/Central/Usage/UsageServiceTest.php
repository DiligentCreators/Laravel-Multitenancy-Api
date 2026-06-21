<?php

use App\Models\Tenant;
use App\Services\Central\UsageService;

beforeEach(function () {
    $this->usageService = app(UsageService::class);
    $this->tenant = Tenant::factory()->create();
});

it('increments usage counter', function () {
    $counter = $this->usageService->increment($this->tenant, 'users');
    expect($counter->used)->toBe(1);

    $this->usageService->increment($this->tenant, 'users', 5);
    $counter->refresh();
    expect($counter->used)->toBe(6);
});

it('decrements usage counter', function () {
    $this->usageService->increment($this->tenant, 'api_calls', 10);
    $this->usageService->decrement($this->tenant, 'api_calls', 3);

    $usage = $this->usageService->checkLimit($this->tenant, 'api_calls');
    expect($usage['used'])->toBe(7);
});

it('checks limit correctly', function () {
    $counter = $this->usageService->getOrCreateCounter($this->tenant, 'storage');
    $counter->update(['limit' => 100, 'used' => 50]);

    $result = $this->usageService->checkLimit($this->tenant, 'storage');
    expect($result['allowed'])->toBeTrue()
        ->and($result['remaining'])->toBe(50);
});

it('blocks when limit exceeded', function () {
    $counter = $this->usageService->getOrCreateCounter($this->tenant, 'contacts');
    $counter->update(['limit' => 10, 'used' => 10]);

    $result = $this->usageService->checkLimit($this->tenant, 'contacts');
    expect($result['allowed'])->toBeFalse()
        ->and($result['remaining'])->toBe(0);
});

it('returns remaining count', function () {
    $counter = $this->usageService->getOrCreateCounter($this->tenant, 'forms');
    $counter->update(['limit' => 50, 'used' => 20]);

    $remaining = $this->usageService->remaining($this->tenant, 'forms');
    expect($remaining)->toBe(30);
});

it('returns -1 for unlimited features', function () {
    $remaining = $this->usageService->remaining($this->tenant, 'unlimited_feature');
    expect($remaining)->toBe(-1);
});

it('resets usage counter for a feature', function () {
    $this->usageService->increment($this->tenant, 'api_calls', 100);
    $this->usageService->reset($this->tenant, 'api_calls');

    $usage = $this->usageService->checkLimit($this->tenant, 'api_calls');
    expect($usage['used'])->toBe(0);
});

it('resets all counters for a tenant', function () {
    $this->usageService->increment($this->tenant, 'users', 10);
    $this->usageService->increment($this->tenant, 'contacts', 20);

    $this->usageService->resetAllForTenant($this->tenant);

    expect($this->usageService->checkLimit($this->tenant, 'users')['used'])->toBe(0);
    expect($this->usageService->checkLimit($this->tenant, 'contacts')['used'])->toBe(0);
});
