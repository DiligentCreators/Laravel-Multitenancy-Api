<?php

namespace App\Policies\Crm;

use App\Models\Crm\Note;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotePolicy
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
        return $user->hasPermissionTo('notes.view');
    }

    public function view(User $user, Note $note): bool
    {
        return $user->hasPermissionTo('notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('notes.create');
    }

    public function update(User $user, ?Note $note = null): bool
    {
        if ($note === null) {
            return $user->hasPermissionTo('notes.update');
        }

        return $user->hasPermissionTo('notes.update') && $note->owner_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $user->hasPermissionTo('notes.delete') && $note->owner_id === $user->id;
    }
}
