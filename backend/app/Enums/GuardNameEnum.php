<?php

namespace App\Enums;

enum GuardNameEnum: string
{
    case ADMIN = 'admin';
    case SELLER = 'seller';
    case WEB = 'web';

    public static function fromString(?string $guardName): self
    {
        return match ($guardName) {
            'admin' => self::ADMIN,
            'seller' => self::SELLER,
            default => self::WEB,
        };
    }
}