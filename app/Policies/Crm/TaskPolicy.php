<?php

namespace App\Policies\Crm;

use App\Models\Crm\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
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

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermissionTo('tasks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tasks.create');
    }

    public function update(User $user, ?Task $task = null): bool
    {
        if ($task === null) {
            return $user->hasPermissionTo('tasks.update');
        }

        return $user->hasPermissionTo('tasks.update') && $task->owner_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermissionTo('tasks.delete') && $task->owner_id === $user->id;
    }
}
