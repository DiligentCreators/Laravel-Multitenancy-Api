<?php

namespace App\Services\Central;

use App\Enums\Central\ProrationTypeEnum;
use App\Models\Plan;
use App\Models\ProrationRecord;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ProrationService
{
    /**
     * Calculate prorated amounts for a plan change.
     *
     * @return array{credit_amount: float, charge_amount: float, net_amount: float, days_remaining: int, total_days: int}
     */
    public function calculateProration(
        Subscription $subscription,
        Plan $newPlan,
        ProrationTypeEnum $type,
    ): array {
        $now = now();
        $periodStart = $subscription->starts_at ?? $subscription->created_at;
        $periodEnd = $subscription->ends_at ?? $periodStart->copy()->addMonth();

        $totalDays = (int) $periodStart->diffInDays($periodEnd);
        $daysRemaining = (int) max(0, $now->diffInDays($periodEnd, false));

        if ($totalDays <= 0) {
            return [
                'credit_amount' => 0,
                'charge_amount' => 0,
                'net_amount' => 0,
                'days_remaining' => 0,
                'total_days' => 1,
            ];
        }

        $oldPlan = $subscription->plan;

        $oldDailyRate = $oldPlan->monthly_price / $totalDays;
        $newDailyRate = $newPlan->monthly_price / $totalDays;

        $oldRemainingValue = round($oldDailyRate * $daysRemaining, 2);
        $newRemainingValue = round($newDailyRate * $daysRemaining, 2);

        $creditAmount = 0;
        $chargeAmount = 0;

        if ($type === ProrationTypeEnum::UPGRADE) {
            $chargeAmount = max(0, $newRemainingValue - $oldRemainingValue);
        } elseif ($type === ProrationTypeEnum::DOWNGRADE) {
            $creditAmount = max(0, $oldRemainingValue - $newRemainingValue);
        }

        $netAmount = $chargeAmount - $creditAmount;

        return [
            'credit_amount' => $creditAmount,
            'charge_amount' => $chargeAmount,
            'net_amount' => $netAmount,
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
        ];
    }

    public function recordProration(
        Subscription $subscription,
        array $prorationData,
        ProrationTypeEnum $type,
        ?array $details = null,
    ): ProrationRecord {
        return ProrationRecord::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'type' => $type->value,
            'credit_amount' => $prorationData['credit_amount'],
            'charge_amount' => $prorationData['charge_amount'],
            'net_amount' => $prorationData['net_amount'],
            'currency' => 'USD',
            'details' => $details,
            'status' => 'pending',
        ]);
    }

    public function applyCredit(ProrationRecord $record): void
    {
        DB::transaction(function () use ($record) {
            if ($record->credit_amount > 0) {
                $tenant = $record->tenant;
                $tenant->increment('credit_balance', $record->credit_amount);
            }

            $record->update(['status' => 'applied']);
        });
    }
}
