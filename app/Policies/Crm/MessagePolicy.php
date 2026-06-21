<?php

namespace App\Policies\Crm;

use App\Models\Crm\Message;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
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
        return $user->hasPermissionTo('communications.view');
    }

    public function view(User $user, Message $message): bool
    {
        return $user->hasPermissionTo('communications.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('communications.create');
    }

    public function update(User $user, ?Message $message = null): bool
    {
        if ($message === null) {
            return $user->hasPermissionTo('communications.update');
        }

        return $user->hasPermissionTo('communications.update') && $message->owner_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->hasPermissionTo('communications.delete') && $message->owner_id === $user->id;
    }
}
