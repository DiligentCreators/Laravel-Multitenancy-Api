<?php

namespace App\Console\Commands;

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire')]
#[Description('Mark subscriptions as expired when their ends_at date has passed')]
class ExpireSubscriptions extends Command
{
    public function handle(): int
    {
        $count = Subscription::active()
            ->where('ends_at', '<', Carbon::now())
            ->update([
                'status' => SubscriptionStatusEnum::EXPIRED,
            ]);

        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
