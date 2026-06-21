<?php

namespace App\Policies\Crm;

use App\Models\Crm\Document;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
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

    public function view(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.create');
    }

    public function update(User $user, ?Document $document = null): bool
    {
        if ($document === null) {
            return $user->hasPermissionTo('documents.update');
        }

        return $user->hasPermissionTo('documents.update') && $document->owner_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('documents.delete') && $document->owner_id === $user->id;
    }
}
