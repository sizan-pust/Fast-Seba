<?php

namespace App\Enums;

enum SettingTypeEnum: string
{
    case SYSTEM = 'system';
    case AUTHENTICATION = 'authentication';
    case NOTIFICATION = 'notification';
    case APP = 'app';

    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }
}