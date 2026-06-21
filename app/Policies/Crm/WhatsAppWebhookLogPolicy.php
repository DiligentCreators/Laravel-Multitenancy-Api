<?php

namespace App\Policies\Crm;

use App\Models\Crm\WhatsAppWebhookLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppWebhookLogPolicy
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

    public function view(User $user, WhatsAppWebhookLog $log): bool
    {
        return $user->hasPermissionTo('whatsapp.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ?WhatsAppWebhookLog $log = null): bool
    {
        return false;
    }

    public function delete(User $user, WhatsAppWebhookLog $log): bool
    {
        return false;
    }
}
