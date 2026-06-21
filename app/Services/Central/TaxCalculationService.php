<?php

namespace App\Services\Central;

use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\TaxRegion;

class TaxCalculationService
{
    /**
     * @param  array<int, array{amount: float, item_type?: string, item_id?: int}>  $lineItems
     * @return array{tax_amount: float, rate_applied: float, region: TaxRegion|null, details: array}
     */
    public function calculate(Invoice $invoice, ?string $regionCode = null): array
    {
        $region = null;
        $rateApplied = 0;
        $taxAmount = 0;
        $details = [];

        if ($regionCode) {
            $region = TaxRegion::where('code', $regionCode)
                ->where('is_active', true)
                ->first();
        }

        if (! $region) {
            $region = TaxRegion::where('is_active', true)->first();
        }

        if (! $region) {
            return [
                'tax_amount' => 0.0,
                'rate_applied' => 0.0,
                'region' => null,
                'details' => [],
            ];
        }

        $taxRate = TaxRate::where('tax_region_id', $region->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderByDesc('rate')
            ->first();

        if (! $taxRate) {
            return [
                'tax_amount' => 0.0,
                'rate_applied' => 0.0,
                'region' => $region,
                'details' => [],
            ];
        }

        $rateApplied = (float) $taxRate->rate;
        $taxAmount = round($invoice->amount * $rateApplied / 100, 2);

        $details = [
            'region' => $region->name,
            'region_code' => $region->code,
            'rate_name' => $taxRate->name,
            'rate_percentage' => $rateApplied,
            'base_amount' => $invoice->amount,
        ];

        return [
            'tax_amount' => $taxAmount,
            'rate_applied' => $rateApplied,
            'region' => $region,
            'details' => $details,
        ];
    }

    public function getDefaultRegion(): ?TaxRegion
    {
        return TaxRegion::where('is_active', true)->first();
    }
}
