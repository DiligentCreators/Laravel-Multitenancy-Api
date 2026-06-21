<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum NotificationChannelEnum: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';
    case WHATSAPP = 'whatsapp';
    case IN_APP = 'in_app';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::PUSH => 'Push',
            self::WHATSAPP => 'WhatsApp',
            self::IN_APP => 'In-App',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
