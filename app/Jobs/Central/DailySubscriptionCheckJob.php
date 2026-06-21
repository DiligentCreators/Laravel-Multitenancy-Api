<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DailySubscriptionCheckJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function handle(): void
    {
        $this->expireTrials();
        $this->expireSubscriptions();
        $this->handleGracePeriod();
    }

    private function expireTrials(): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatusEnum::TRIAL)
            ->where('ends_at', '<', Carbon::now())
            ->chunk(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update([
                        'status' => SubscriptionStatusEnum::EXPIRED,
                        'expired_at' => Carbon::now(),
                    ]);

                    Log::info('Trial expired for subscription', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                    ]);
                }
            });
    }

    private function expireSubscriptions(): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatusEnum::ACTIVE)
            ->where('ends_at', '<', Carbon::now())
            ->chunk(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update([
                        'status' => SubscriptionStatusEnum::EXPIRED,
                        'expired_at' => Carbon::now(),
                    ]);

                    Log::info('Subscription expired', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                    ]);
                }
            });
    }

    private function handleGracePeriod(): void
    {
        $gracePeriodDays = config('central.billing.grace_period', 7);

        Subscription::query()
            ->where('status', SubscriptionStatusEnum::EXPIRED)
            ->where('ends_at', '<', Carbon::now()->subDays($gracePeriodDays))
            ->chunk(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update([
                        'status' => SubscriptionStatusEnum::SUSPENDED,
                        'suspended_at' => Carbon::now(),
                    ]);

                    Log::info('Subscription suspended after grace period', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                    ]);
                }
            });
    }

    public function retryUntil(): Carbon
    {
        return now()->addMinutes(5);
    }

    public function backoff(): array
    {
        return [2, 5, 10, 30];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DailySubscriptionCheckJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
