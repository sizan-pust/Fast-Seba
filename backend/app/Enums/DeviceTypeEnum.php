<?php

namespace App\Enums;

enum DeviceTypeEnum: string
{
    case ANDROID = 'android';
    case IOS = 'ios';
    case WEB = 'web';
}