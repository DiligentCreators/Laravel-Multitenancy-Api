<?php

namespace App\Policies\Crm;

use App\Models\Crm\PortalUser;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PortalUserPolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->hasRole('owner') || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('portal-users.view');
    }

    public function view(User $user, PortalUser $portalUser): bool
    {
        return $user->hasPermissionTo('portal-users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('portal-users.create');
    }

    public function update(User $user, PortalUser $portalUser): bool
    {
        return $user->hasPermissionTo('portal-users.update');
    }

    public function delete(User $user, PortalUser $portalUser): bool
    {
        return $user->hasPermissionTo('portal-users.delete');
    }
}
