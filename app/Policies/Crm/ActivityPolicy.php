<?php

namespace App\Policies\Crm;

use App\Models\Crm\Activity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityPolicy
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
        return $user->hasPermissionTo('activities.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->hasPermissionTo('activities.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('activities.create');
    }

    public function update(User $user, ?Activity $activity = null): bool
    {
        if ($activity === null) {
            return $user->hasPermissionTo('activities.update');
        }

        return $user->hasPermissionTo('activities.update') && $activity->owner_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->hasPermissionTo('activities.delete') && $activity->owner_id === $user->id;
    }
}
