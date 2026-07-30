<?php

namespace App\Enums;

enum SettingTypeEnum: string
{
    case SYSTEM = 'system';
    case AUTHENTICATION = 'authentication';
    case NOTIFICATION = 'notification';
    case APP = 'app';
    case WEB = 'web';
    case PAYMENT = 'payment';
    case HOME_GENERAL_SETTINGS = 'home_general_settings';
    case ADVERTISEMENT = 'advertisement';

    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }
}
