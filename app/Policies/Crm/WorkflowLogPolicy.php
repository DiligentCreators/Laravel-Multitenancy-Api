<?php

namespace App\Policies\Crm;

use App\Models\Crm\WorkflowLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowLogPolicy
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

    public function view(User $user, WorkflowLog $workflowLog): bool
    {
        return $user->hasPermissionTo('workflows.view');
    }
}
