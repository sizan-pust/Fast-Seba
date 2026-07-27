<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use App\Enums\NotificationRoleTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'role_type',
    ];

    protected function casts(): array
    {
        return [
            'device_type' => DeviceTypeEnum::class,
            'role_type' => NotificationRoleTypeEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}