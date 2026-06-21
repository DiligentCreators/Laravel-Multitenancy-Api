<?php

namespace App\Policies\Crm;

use App\Models\Crm\DocumentFolder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentFolderPolicy
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

    public function view(User $user, DocumentFolder $documentFolder): bool
    {
        return $user->hasPermissionTo('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.create');
    }

    public function update(User $user, ?DocumentFolder $documentFolder = null): bool
    {
        if ($documentFolder === null) {
            return $user->hasPermissionTo('documents.update');
        }

        return $user->hasPermissionTo('documents.update') && $documentFolder->owner_id === $user->id;
    }

    public function delete(User $user, DocumentFolder $documentFolder): bool
    {
        return $user->hasPermissionTo('documents.delete') && $documentFolder->owner_id === $user->id;
    }
}
