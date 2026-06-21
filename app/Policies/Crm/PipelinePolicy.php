<?php

namespace App\Policies\Crm;

use App\Models\Crm\Pipeline;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PipelinePolicy
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
        return $user->hasPermissionTo('pipelines.view');
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $user->hasPermissionTo('pipelines.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('pipelines.create');
    }

    public function update(User $user, ?Pipeline $pipeline = null): bool
    {
        return $user->hasPermissionTo('pipelines.update');
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $user->hasPermissionTo('pipelines.delete');
    }
}
