<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Feature;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeaturePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('features.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Feature $feature): bool
    {
        if ($centralUser->can('features.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('features.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Feature $feature): bool
    {
        if ($centralUser->can('features.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Feature $feature): bool
    {
        if ($centralUser->can('features.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Feature $feature): bool
    {
        if ($centralUser->can('features.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Feature $feature): bool
    {
        if ($centralUser->can('features.force.delete')) {
            return true;
        }

        return false;
    }
}
