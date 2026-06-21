<?php

namespace App\Policies\Crm;

use App\Models\Crm\WhatsAppPhoneNumber;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppPhoneNumberPolicy
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

    public function view(User $user, WhatsAppPhoneNumber $phoneNumber): bool
    {
        return $user->hasPermissionTo('whatsapp.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('whatsapp.create');
    }

    public function update(User $user, ?WhatsAppPhoneNumber $phoneNumber = null): bool
    {
        return $user->hasPermissionTo('whatsapp.update');
    }

    public function delete(User $user, WhatsAppPhoneNumber $phoneNumber): bool
    {
        return $user->hasPermissionTo('whatsapp.delete');
    }
}
