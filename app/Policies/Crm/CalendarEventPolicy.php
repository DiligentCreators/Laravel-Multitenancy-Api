<?php

namespace App\Policies\Crm;

use App\Models\Crm\CalendarEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy
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
        return $user->hasPermissionTo('calendar.view');
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->hasPermissionTo('calendar.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('calendar.create');
    }

    public function update(User $user, ?CalendarEvent $calendarEvent = null): bool
    {
        if ($calendarEvent === null) {
            return $user->hasPermissionTo('calendar.update');
        }

        return $user->hasPermissionTo('calendar.update') && $calendarEvent->owner_id === $user->id;
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->hasPermissionTo('calendar.delete') && $calendarEvent->owner_id === $user->id;
    }
}
