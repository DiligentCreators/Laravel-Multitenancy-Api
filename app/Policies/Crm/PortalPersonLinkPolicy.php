<?php

namespace App\Policies\Crm;

use App\Models\Crm\PortalPersonLink;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PortalPersonLinkPolicy
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

    public function view(User $user, PortalPersonLink $portalPersonLink): bool
    {
        return $user->hasPermissionTo('portal-users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('portal-users.update');
    }

    public function update(User $user, PortalPersonLink $portalPersonLink): bool
    {
        return $user->hasPermissionTo('portal-users.update');
    }

    public function delete(User $user, PortalPersonLink $portalPersonLink): bool
    {
        return $user->hasPermissionTo('portal-users.update');
    }
}
