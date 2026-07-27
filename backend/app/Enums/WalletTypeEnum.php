<?php

namespace App\Enums;

enum WalletTypeEnum: string
{
    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case DELIVERY_BOY = 'delivery_boy';
    case SELLER_AD = 'seller_ad';
}