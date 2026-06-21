<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum OverageChargeStatusEnum: string
{
    case PENDING = 'pending';
    case INVOICED = 'invoiced';
    case PAID = 'paid';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INVOICED => 'Invoiced',
            self::PAID => 'Paid',
            self::WAIVED => 'Waived',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
