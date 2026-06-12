<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Subscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('subscriptions.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Subscription $subscription): bool
    {
        if ($centralUser->can('subscriptions.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('subscriptions.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Subscription $subscription): bool
    {
        if ($centralUser->can('subscriptions.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Subscription $subscription): bool
    {
        if ($centralUser->can('subscriptions.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Subscription $subscription): bool
    {
        if ($centralUser->can('subscriptions.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Subscription $subscription): bool
    {
        if ($centralUser->can('subscriptions.forceDelete')) {
            return true;
        }

        return false;
    }
}
