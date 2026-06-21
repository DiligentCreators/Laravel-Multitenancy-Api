<?php

namespace App\Policies\Crm;

use App\Models\Crm\Source;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SourcePolicy
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
        return $user->hasPermissionTo('sources.view');
    }

    public function view(User $user, Source $source): bool
    {
        return $user->hasPermissionTo('sources.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sources.create');
    }

    public function update(User $user, Source $source): bool
    {
        return $user->hasPermissionTo('sources.update');
    }

    public function delete(User $user, Source $source): bool
    {
        return $user->hasPermissionTo('sources.delete');
    }
}
