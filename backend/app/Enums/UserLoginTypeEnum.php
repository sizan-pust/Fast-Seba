<?php

namespace App\Enums;

enum UserLoginTypeEnum: string
{
    case GOOGLE = 'google';
    case APPLE = 'apple';
    case PLATFORM = 'platform';
}