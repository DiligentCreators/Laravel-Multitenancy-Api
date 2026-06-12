<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Subscription;

class SubscriptionObserver
{
    public function creating(Subscription $subscription): void {}

    public function created(Subscription $subscription): void {}

    public function updating(Subscription $subscription): void {}

    public function updated(Subscription $subscription): void {}

    public function saving(Subscription $subscription): void {}

    public function saved(Subscription $subscription): void {}

    public function deleting(Subscription $subscription): void {}

    public function deleted(Subscription $subscription): void {}

    public function restoring(Subscription $subscription): void {}

    public function restored(Subscription $subscription): void {}

    public function forceDeleted(Subscription $subscription): void {}
}
