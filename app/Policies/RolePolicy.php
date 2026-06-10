<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('roles.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Role $role): bool
    {
        if ($centralUser->can('roles.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('roles.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Role $role): bool
    {
        if ($centralUser->can('roles.update')) {
            return true;
        }

        return false;
    }
}
