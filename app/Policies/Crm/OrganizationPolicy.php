<?php

namespace App\Policies\Crm;

use App\Models\Crm\Organization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
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
        return $user->hasPermissionTo('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('organizations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('organizations.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('organizations.update') && $organization->owner_id === $user->id;
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('organizations.delete') && $organization->owner_id === $user->id;
    }
}
