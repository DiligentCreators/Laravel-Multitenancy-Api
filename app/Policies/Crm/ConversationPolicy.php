<?php

namespace App\Policies\Crm;

use App\Models\Crm\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
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

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->hasPermissionTo('communications.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('communications.create');
    }

    public function update(User $user, ?Conversation $conversation = null): bool
    {
        if ($conversation === null) {
            return $user->hasPermissionTo('communications.update');
        }

        return $user->hasPermissionTo('communications.update') && $conversation->owner_id === $user->id;
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $user->hasPermissionTo('communications.delete') && $conversation->owner_id === $user->id;
    }
}
