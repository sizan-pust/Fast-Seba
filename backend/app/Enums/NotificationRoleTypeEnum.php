<?php

namespace App\Enums;

enum NotificationRoleTypeEnum: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case RIDER = 'rider';
}