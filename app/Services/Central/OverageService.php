<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Central\OverageChargeStatusEnum;
use App\Models\OverageCharge;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class OverageService
{
    public function __construct(
        protected OverageCharge $overageCharge,
    ) {}

    public function calculateAndCharge(Tenant $tenant, string $feature, int $overageQuantity, float $unitPrice): OverageCharge
    {
        $amount = $overageQuantity * $unitPrice;

        return $this->overageCharge->create([
            'tenant_id' => $tenant->id,
            'feature' => $feature,
            'quantity' => $overageQuantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'status' => OverageChargeStatusEnum::PENDING,
        ]);
    }

    public function markInvoiced(OverageCharge $overageCharge): OverageCharge
    {
        $overageCharge->update(['status' => OverageChargeStatusEnum::INVOICED]);

        return $overageCharge->fresh();
    }

    public function markPaid(OverageCharge $overageCharge): OverageCharge
    {
        $overageCharge->update(['status' => OverageChargeStatusEnum::PAID]);

        return $overageCharge->fresh();
    }

    public function markWaived(OverageCharge $overageCharge): OverageCharge
    {
        $overageCharge->update(['status' => OverageChargeStatusEnum::WAIVED]);

        return $overageCharge->fresh();
    }

    public function getPendingCharges(Tenant $tenant): Collection
    {
        return $this->overageCharge
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('status', OverageChargeStatusEnum::PENDING)
            ->get();
    }

    public function getTotalPending(Tenant $tenant): float
    {
        return (float) $this->overageCharge
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('status', OverageChargeStatusEnum::PENDING)
            ->sum('amount');
    }
}
