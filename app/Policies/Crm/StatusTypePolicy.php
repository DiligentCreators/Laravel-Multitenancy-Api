<?php

namespace App\Policies\Crm;

use App\Models\Crm\StatusType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StatusTypePolicy
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
        return $user->hasPermissionTo('statuses.view');
    }

    public function view(User $user, StatusType $statusType): bool
    {
        return $user->hasPermissionTo('statuses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('statuses.create');
    }

    public function update(User $user, StatusType $statusType): bool
    {
        return $user->hasPermissionTo('statuses.update');
    }

    public function delete(User $user, StatusType $statusType): bool
    {
        return $user->hasPermissionTo('statuses.delete');
    }
}
