<?php

namespace App\Models\Crm\Concerns;

use App\Models\User;
use App\Notifications\CrmActionNotification;
use Illuminate\Support\Facades\Notification;

trait NotifiesActions
{
    public function notifyAction(string $title, string $body, int $userId): void
    {
        $user = User::find($userId);

        if ($user) {
            Notification::send($user, new CrmActionNotification($title, $body, $this));
        }
    }
}
