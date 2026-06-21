<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SettingTypeEnum: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case SELECT = 'select';
    case JSON = 'json';
    case EMAIL = 'email';
    case URL = 'url';
    case FILE = 'file';
    case PASSWORD = 'password';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::TEXTAREA => 'Textarea',
            self::NUMBER => 'Number',
            self::BOOLEAN => 'Boolean',
            self::SELECT => 'Select',
            self::JSON => 'JSON',
            self::EMAIL => 'Email',
            self::URL => 'URL',
            self::FILE => 'File',
            self::PASSWORD => 'Password',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
