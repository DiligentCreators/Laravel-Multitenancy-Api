<?php

namespace App\Policies\Crm;

use App\Models\Crm\WhatsAppMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppMessagePolicy
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

    public function view(User $user, WhatsAppMessage $message): bool
    {
        return $user->hasPermissionTo('whatsapp.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ?WhatsAppMessage $message = null): bool
    {
        return false;
    }

    public function delete(User $user, WhatsAppMessage $message): bool
    {
        return false;
    }
}
