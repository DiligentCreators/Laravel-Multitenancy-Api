<?php

namespace App\Policies\Crm;

use App\Models\Crm\WhatsAppAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppAccountPolicy
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
        return $user->hasPermissionTo('whatsapp.view');
    }

    public function view(User $user, WhatsAppAccount $account): bool
    {
        return $user->hasPermissionTo('whatsapp.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('whatsapp.create');
    }

    public function update(User $user, ?WhatsAppAccount $account = null): bool
    {
        return $user->hasPermissionTo('whatsapp.update');
    }

    public function delete(User $user, WhatsAppAccount $account): bool
    {
        return $user->hasPermissionTo('whatsapp.delete');
    }
}
