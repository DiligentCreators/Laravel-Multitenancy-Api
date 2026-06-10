<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('tenants.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Tenant $tenant): bool
    {
        if ($centralUser->can('tenants.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('tenants.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Tenant $tenant): bool
    {
        if ($centralUser->can('tenants.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Tenant $tenant): bool
    {
        if ($centralUser->can('tenants.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Tenant $tenant): bool
    {
        if ($centralUser->can('tenants.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Tenant $tenant): bool
    {
        if ($centralUser->can('tenants.force.delete')) {
            return true;
        }

        return false;
    }
}
