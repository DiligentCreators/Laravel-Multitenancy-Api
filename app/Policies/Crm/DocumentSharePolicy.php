<?php

namespace App\Policies\Crm;

use App\Models\Crm\DocumentShare;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentSharePolicy
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

    public function view(User $user, DocumentShare $documentShare): bool
    {
        return $user->hasPermissionTo('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.create');
    }

    public function update(User $user, ?DocumentShare $documentShare = null): bool
    {
        return $user->hasPermissionTo('documents.update');
    }

    public function delete(User $user, DocumentShare $documentShare): bool
    {
        return $user->hasPermissionTo('documents.delete');
    }
}
