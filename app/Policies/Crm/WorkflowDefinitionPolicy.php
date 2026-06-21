<?php

namespace App\Policies\Crm;

use App\Models\Crm\WorkflowDefinition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowDefinitionPolicy
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
        return $user->hasPermissionTo('workflows.view');
    }

    public function view(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasPermissionTo('workflows.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('workflows.create');
    }

    public function update(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasPermissionTo('workflows.update');
    }

    public function delete(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasPermissionTo('workflows.delete');
    }
}
