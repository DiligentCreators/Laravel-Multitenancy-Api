<?php

namespace App\Policies\Crm;

use App\Models\Crm\FeatureDefinition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeatureDefinitionPolicy
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
        return $user->hasPermissionTo('features.view');
    }

    public function view(User $user, FeatureDefinition $featureDefinition): bool
    {
        return $user->hasPermissionTo('features.view');
    }
}
