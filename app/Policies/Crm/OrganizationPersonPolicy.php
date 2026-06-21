<?php

namespace App\Policies\Crm;

use App\Models\Crm\OrganizationPerson;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPersonPolicy
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
        return $user->hasPermissionTo('organization-people.manage');
    }

    public function view(User $user, OrganizationPerson $organizationPerson): bool
    {
        return $user->hasPermissionTo('organization-people.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('organization-people.manage');
    }

    public function update(User $user, OrganizationPerson $organizationPerson): bool
    {
        return $user->hasPermissionTo('organization-people.manage');
    }

    public function delete(User $user, OrganizationPerson $organizationPerson): bool
    {
        return $user->hasPermissionTo('organization-people.manage');
    }
}
