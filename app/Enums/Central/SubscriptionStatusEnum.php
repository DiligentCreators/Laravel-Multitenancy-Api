<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionStatusEnum: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TRIAL => 'Trial',
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
