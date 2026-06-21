<?php

namespace App\Policies\Crm;

use App\Models\Crm\Tag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
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
        return $user->hasPermissionTo('tags.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasPermissionTo('tags.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tags.create');
    }

    public function update(User $user, ?Tag $tag = null): bool
    {
        return $user->hasPermissionTo('tags.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasPermissionTo('tags.delete');
    }
}
