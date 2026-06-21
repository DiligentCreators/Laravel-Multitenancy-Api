<?php

namespace App\Policies\Crm;

use App\Models\Crm\TimelineEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimelineEntryPolicy
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
        return $user->hasPermissionTo('timeline.view');
    }

    public function view(User $user, TimelineEntry $timelineEntry): bool
    {
        return $user->hasPermissionTo('timeline.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TimelineEntry $timelineEntry): bool
    {
        return false;
    }

    public function delete(User $user, TimelineEntry $timelineEntry): bool
    {
        return false;
    }
}
