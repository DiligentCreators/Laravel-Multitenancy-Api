<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SettingDefinitionTypeEnum: string
{
    case STRING = 'string';

    case INTEGER = 'integer';

    case BOOLEAN = 'boolean';

    case DECIMAL = 'decimal';

    public function label(): string
    {
        return match ($this) {
            self::STRING => 'String',
            self::INTEGER => 'Integer',
            self::BOOLEAN => 'Boolean',
            self::DECIMAL => 'Decimal',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
