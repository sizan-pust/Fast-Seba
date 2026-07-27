<?php

namespace App\Enums;

enum DefaultSystemRolesEnum: string
{
    case SUPER_ADMIN = 'Super Admin';
    case SELLER = 'seller';
    case CUSTOMER = 'customer';
}