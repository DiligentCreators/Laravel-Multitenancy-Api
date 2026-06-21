<?php

namespace App\Policies\Crm;

use App\Models\Crm\TaskReminder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskReminderPolicy
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
        return $user->hasPermissionTo('tasks.view');
    }

    public function view(User $user, TaskReminder $taskReminder): bool
    {
        return $user->hasPermissionTo('tasks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tasks.update');
    }

    public function update(User $user, TaskReminder $taskReminder): bool
    {
        return $user->hasPermissionTo('tasks.update') && $taskReminder->owner_id === $user->id;
    }

    public function delete(User $user, TaskReminder $taskReminder): bool
    {
        return $user->hasPermissionTo('tasks.delete') && $taskReminder->owner_id === $user->id;
    }
}
