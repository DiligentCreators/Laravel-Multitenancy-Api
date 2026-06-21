<?php

namespace App\Policies\Crm;

use App\Models\Crm\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
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
        return $user->hasPermissionTo('comments.view');
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->hasPermissionTo('comments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('comments.create');
    }

    public function update(User $user, ?Comment $comment = null): bool
    {
        if ($comment === null) {
            return $user->hasPermissionTo('comments.update');
        }

        return $user->hasPermissionTo('comments.update') && $comment->owner_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasPermissionTo('comments.delete') && $comment->owner_id === $user->id;
    }
}
