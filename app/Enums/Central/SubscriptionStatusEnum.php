<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionStatusEnum: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::TRIAL => 'Trial',
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
            self::SUSPENDED => 'Suspended',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isTrial(): bool
    {
        return $this === self::TRIAL;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isValidForAccess(): bool
    {
        return $this === self::ACTIVE || $this === self::TRIAL;
    }
}
