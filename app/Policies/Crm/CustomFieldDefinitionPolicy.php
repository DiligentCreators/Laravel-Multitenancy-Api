<?php

namespace App\Policies\Crm;

use App\Models\Crm\CustomFieldDefinition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFieldDefinitionPolicy
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
        return $user->hasPermissionTo('custom-fields.view');
    }

    public function view(User $user, CustomFieldDefinition $customFieldDefinition): bool
    {
        return $user->hasPermissionTo('custom-fields.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('custom-fields.create');
    }

    public function update(User $user, CustomFieldDefinition $customFieldDefinition): bool
    {
        return $user->hasPermissionTo('custom-fields.update');
    }

    public function delete(User $user, CustomFieldDefinition $customFieldDefinition): bool
    {
        return $user->hasPermissionTo('custom-fields.delete');
    }
}
