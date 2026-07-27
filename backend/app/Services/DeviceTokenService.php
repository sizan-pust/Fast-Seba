<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFcmToken;

class DeviceTokenService
{
    public function sync(
        User $user,
        string $token,
        ?string $deviceType,
        ?string $roleType,
        ?string $previousToken = null
    ): UserFcmToken {
        if ($previousToken && $previousToken !== $token) {
            UserFcmToken::query()
                ->where('user_id', $user->id)
                ->where('fcm_token', $previousToken)
                ->delete();
        }

        return UserFcmToken::query()->updateOrCreate(
            ['fcm_token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
                'role_type' => $roleType,
            ]
        );
    }

    public function forget(User $user, string $token): void
    {
        UserFcmToken::query()
            ->where('user_id', $user->id)
            ->where('fcm_token', $token)
            ->delete();
    }
}