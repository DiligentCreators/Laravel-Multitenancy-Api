<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Plan;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('plans.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Plan $plan): bool
    {
        if ($centralUser->can('plans.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('plans.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Plan $plan): bool
    {
        if ($centralUser->can('plans.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Plan $plan): bool
    {
        if ($centralUser->can('plans.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Plan $plan): bool
    {
        if ($centralUser->can('plans.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Plan $plan): bool
    {
        if ($centralUser->can('plans.force.delete')) {
            return true;
        }

        return false;
    }
}
