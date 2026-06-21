<?php

namespace App\Policies\Crm;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentVersionPolicy
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
        return $user->hasPermissionTo('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.create');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('documents.delete');
    }
}
