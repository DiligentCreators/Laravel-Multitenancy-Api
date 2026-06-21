<?php

namespace App\Policies\Crm;

use App\Models\Crm\Address;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AddressPolicy
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
        return $user->hasPermissionTo('addresses.view');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.update');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('addresses.delete');
    }
}
