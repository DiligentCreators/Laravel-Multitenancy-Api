<?php

namespace App\Policies\Crm;

use App\Models\Crm\MessageTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessageTemplatePolicy
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
        return $user->hasPermissionTo('message_templates.view');
    }

    public function view(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->hasPermissionTo('message_templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('message_templates.create');
    }

    public function update(User $user, ?MessageTemplate $messageTemplate = null): bool
    {
        return $user->hasPermissionTo('message_templates.update');
    }

    public function delete(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->hasPermissionTo('message_templates.delete');
    }
}
