<?php

namespace App\Services;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

class SocialAuthService
{
    public function __construct(
        protected SettingService $settingService,
        protected OtpService $otpService
    ) {
    }

    public function loginOrRegisterFromGoogle(
        array $profile,
        ?string $friendsCode = null,
        array $extra = []
    ): array {
        return $this->loginOrRegister(
            $profile,
            UserLoginTypeEnum::GOOGLE,
            $friendsCode,
            $extra,
            true
        );
    }

    public function loginOrRegisterFromApple(
        array $profile,
        ?string $friendsCode = null,
        array $extra = []
    ): array {
        return $this->loginOrRegister(
            $profile,
            UserLoginTypeEnum::APPLE,
            $friendsCode,
            $extra,
            false
        );
    }

    public function loginWithPhone(
        array $profile,
        ?string $name = null
    ): array {
        $mobile = $this->otpService->sanitizeMobile(
            (string) ($profile['phone_number'] ?? '')
        );

        $user = User::query()
            ->whereIn(
                'mobile',
                $this->otpService->mobileCandidates($mobile)
            )
            ->first();

        if (! $user) {
            return [
                'user' => null,
                'is_new' => false,
            ];
        }

        $updates = [
            'firebase_uid' => $profile['uid'] ?? null,
            'country_code' => '+880',
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
        ];

        if ($name && ! $user->name) {
            $updates['name'] = $name;
        }

        $user->forceFill(
            array_filter(
                $updates,
                static fn ($value) => $value !== null
            )
        )->save();

        return [
            'user' => $user,
            'is_new' => false,
        ];
    }

    private function loginOrRegister(
        array $profile,
        UserLoginTypeEnum $type,
        ?string $friendsCode,
        array $extra,
        bool $preferEmail
    ): array {
        $uid = (string) ($profile['uid'] ?? '');
        $email = $profile['email'] ?? null;

        $user = null;

        if ($preferEmail && $email) {
            $user = User::query()
                ->where('email', $email)
                ->first();
        }

        if (! $user && $uid !== '') {
            $user = User::query()
                ->where('firebase_uid', $uid)
                ->first();
        }

        if (! $user && ! $preferEmail && $email) {
            $user = User::query()
                ->where('email', $email)
                ->first();
        }

        if ($user) {
            $updates = [];

            if (! $user->firebase_uid && $uid !== '') {
                $updates['firebase_uid'] = $uid;
            }

            if (! $user->email && $email) {
                $updates['email'] = $email;
            }

            if (
                ! $user->email_verified_at
                && ($profile['email_verified'] ?? false)
            ) {
                $updates['email_verified_at'] = now();
            }

            $updates['logged_in_type'] = $type->value;

            $user->forceFill($updates)->save();

            return [
                'user' => $user,
                'is_new' => false,
            ];
        }

        $user = User::query()->create([
            'name' => $profile['name']
                ?: ($email ?: ($type === UserLoginTypeEnum::APPLE
                    ? 'Apple User'
                    : 'User')),
            'email' => $email,
            'firebase_uid' => $uid,
            'email_verified_at' => (
                $profile['email_verified'] ?? false
            ) ? now() : null,
            'friends_code' => $friendsCode,
            'referral_code' => $this->generateReferralCode(),
            'country' => $extra['country'] ?? 'Bangladesh',
            'iso_2' => strtoupper($extra['iso_2'] ?? 'BD'),
            'country_code' => '+880',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => $type->value,
        ]);

        $user->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);

        $system = $this->settingService
            ->getSettingValues('system');

        Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => WalletTypeEnum::CUSTOMER->value,
            ],
            [
                'balance' => max(
                    0,
                    (float) (
                        $system['welcomeWalletBalanceAmount'] ?? 0
                    )
                ),
                'blocked_balance' => 0,
                'currency_code' => $system['currencyCode'] ?? 'BDT',
            ]
        );

        return [
            'user' => $user,
            'is_new' => true,
        ];
    }

    private function generateReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where(
            'referral_code',
            $code
        )->exists());

        return $code;
    }
}