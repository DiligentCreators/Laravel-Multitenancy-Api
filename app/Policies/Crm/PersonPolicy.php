<?php

namespace App\Policies\Crm;

use App\Models\Crm\Person;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPolicy
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
        return $user->hasPermissionTo('people.view');
    }

    public function view(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('people.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('people.create');
    }

    public function update(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('people.update') && $person->owner_id === $user->id;
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('people.delete') && $person->owner_id === $user->id;
    }
}
