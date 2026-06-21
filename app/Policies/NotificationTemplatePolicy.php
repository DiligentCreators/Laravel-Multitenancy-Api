<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\NotificationTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('notification-templates.list');
    }

    public function view(CentralUser $centralUser, NotificationTemplate $notificationTemplate): bool
    {
        return $centralUser->can('notification-templates.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('notification-templates.create');
    }

    public function update(CentralUser $centralUser, NotificationTemplate $notificationTemplate): bool
    {
        return $centralUser->can('notification-templates.update');
    }

    public function delete(CentralUser $centralUser, NotificationTemplate $notificationTemplate): bool
    {
        return $centralUser->can('notification-templates.delete');
    }
}
