<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Support\Facades\DB;

class UsageService
{
    public function __construct(
        protected UsageCounter $usageCounter,
    ) {}

    public function increment(Tenant $tenant, string $feature, int $amount = 1): UsageCounter
    {
        return DB::transaction(function () use ($tenant, $feature, $amount) {
            $counter = $this->getOrCreateCounter($tenant, $feature);
            $counter->increment('used', $amount);

            return $counter->fresh();
        });
    }

    public function decrement(Tenant $tenant, string $feature, int $amount = 1): UsageCounter
    {
        return DB::transaction(function () use ($tenant, $feature, $amount) {
            $counter = $this->getOrCreateCounter($tenant, $feature);
            $counter->decrement('used', $amount);

            return $counter->fresh();
        });
    }

    public function checkLimit(Tenant $tenant, string $feature): array
    {
        $counter = $this->usageCounter
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature)
            ->first();

        if ($counter === null) {
            return [
                'allowed' => true,
                'used' => 0,
                'limit' => 0,
                'remaining' => -1,
            ];
        }

        $remaining = $counter->limit > 0 ? $counter->limit - $counter->used : -1;

        return [
            'allowed' => $remaining === -1 || $remaining > 0,
            'used' => $counter->used,
            'limit' => $counter->limit,
            'remaining' => $remaining,
        ];
    }

    public function remaining(Tenant $tenant, string $feature): int
    {
        $counter = $this->usageCounter
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature)
            ->first();

        if ($counter === null || $counter->limit <= 0) {
            return -1;
        }

        return max(0, $counter->limit - $counter->used);
    }

    public function reset(Tenant $tenant, string $feature): void
    {
        $this->usageCounter
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature)
            ->update(['used' => 0, 'reset_at' => now()]);
    }

    public function resetAllForTenant(Tenant $tenant): void
    {
        $this->usageCounter
            ->query()
            ->where('tenant_id', $tenant->id)
            ->update(['used' => 0, 'reset_at' => now()]);
    }

    public function getOrCreateCounter(Tenant $tenant, string $feature): UsageCounter
    {
        return $this->usageCounter
            ->query()
            ->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'feature' => $feature,
                ],
                [
                    'used' => 0,
                    'limit' => 0,
                    'period' => 'monthly',
                    'reset_at' => now()->addMonth(),
                ]
            );
    }
}
