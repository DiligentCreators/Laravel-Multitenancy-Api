<?php

namespace App\Services;

use App\Enums\Central\NotificationChannelEnum;

class NotificationService
{
    public function send(
        mixed $notifiable,
        string $title,
        string $body,
        NotificationChannelEnum $channel = NotificationChannelEnum::IN_APP,
        ?array $data = null,
    ): void {}

    public function queue(
        mixed $notifiable,
        string $title,
        string $body,
        NotificationChannelEnum $channel = NotificationChannelEnum::IN_APP,
        ?array $data = null,
    ): void {}

    public function broadcast(
        string $event,
        ?array $data = null,
    ): void {}
}
