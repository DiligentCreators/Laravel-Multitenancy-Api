<?php

namespace App\Enums;

enum RoleScopeEnum: string
{
    case CENTRAL = 'central';
    case TENANT = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::CENTRAL => 'Central',
            self::TENANT => 'Tenant',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
