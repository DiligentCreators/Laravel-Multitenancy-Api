<?php

namespace App\Policies\Crm;

use App\Models\Crm\Lead;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadPolicy
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
        return $user->hasPermissionTo('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermissionTo('leads.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('leads.create');
    }

    public function update(User $user, ?Lead $lead = null): bool
    {
        if ($lead === null) {
            return $user->hasPermissionTo('leads.update');
        }

        return $user->hasPermissionTo('leads.update') && $lead->owner_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermissionTo('leads.delete') && $lead->owner_id === $user->id;
    }
}
