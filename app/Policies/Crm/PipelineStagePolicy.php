<?php

namespace App\Policies\Crm;

use App\Models\Crm\PipelineStage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PipelineStagePolicy
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
        return $user->hasPermissionTo('pipeline-stages.view');
    }

    public function view(User $user, PipelineStage $pipelineStage): bool
    {
        return $user->hasPermissionTo('pipeline-stages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('pipeline-stages.create');
    }

    public function update(User $user, ?PipelineStage $pipelineStage = null): bool
    {
        return $user->hasPermissionTo('pipeline-stages.update');
    }

    public function delete(User $user, PipelineStage $pipelineStage): bool
    {
        return $user->hasPermissionTo('pipeline-stages.delete');
    }
}
