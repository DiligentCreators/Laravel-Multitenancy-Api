<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionBillingCycleEnum: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::YEARLY => 'Yearly',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
