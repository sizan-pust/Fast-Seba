<?php

namespace App\Services;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Models\User;
use App\Models\UserOtp;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(
        protected SmsService $smsService,
        protected SettingService $settingService
    ) {
    }

    public function sanitizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile);

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public function sendOtp(string $mobile): array
    {
        $mobile = $this->sanitizeMobile($mobile);

        if (! preg_match('/^01[3-9]\d{8}$/', $mobile)) {
            return [
                'success' => false,
                'message' => 'Invalid mobile number.',
            ];
        }

        $existing = UserOtp::query()
            ->forMobile($mobile)
            ->first();

        $cooldown = (int) config(
            'fastsheba.otp.resend_cooldown_seconds',
            60
        );

        if (
            $existing?->last_sent_at
            && $existing->last_sent_at->gt(
                now()->subSeconds($cooldown)
            )
        ) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
                'retry_after' => max(
                    1,
                    now()->diffInSeconds(
                        $existing->last_sent_at->addSeconds($cooldown),
                        false
                    )
                ),
            ];
        }

        $otp = config('fastsheba.otp.test_mode')
            ? (string) config('fastsheba.otp.test_code', '123456')
            : str_pad(
                (string) random_int(0, 999999),
                6,
                '0',
                STR_PAD_LEFT
            );

        $expiresIn = (int) config(
            'fastsheba.otp.expires_in_seconds',
            600
        );

        UserOtp::query()->updateOrCreate(
            ['mobile' => $mobile],
            [
                'otp' => Hash::make($otp),
                'expires_at' => now()->addSeconds($expiresIn),
                'verified_at' => null,
                'attempts' => 0,
                'last_sent_at' => now(),
            ]
        );

        if (! config('fastsheba.otp.test_mode')) {
            $auth = $this->settingService
                ->getSettingValues('authentication');

            $template = $auth['customSmsTextFormatData']
                ?? 'Your OTP is: {otp}. Valid for {minutes} minutes.';

            $message = strtr($template, [
                '{otp}' => $otp,
                '{minutes}' => (string) ceil($expiresIn / 60),
                '{brand}' => 'FastSheba',
            ]);

            $result = $this->smsService->sendSms(
                '+88'.$mobile,
                $message
            );

            if (! $result['success']) {
                return $result;
            }
        }

        $data = [
            'mobile' => $mobile,
            'expires_in' => $expiresIn,
        ];

        if (
            app()->environment('local', 'testing')
            && config('fastsheba.otp.test_mode')
        ) {
            $data['debug_otp'] = $otp;
        }

        return [
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => $data,
        ];
    }

    public function verifyOtp(
        string $mobile,
        string $otp,
        bool $consume = true
    ): array {
        $mobile = $this->sanitizeMobile($mobile);

        $record = UserOtp::query()
            ->forMobile($mobile)
            ->active()
            ->latest('created_at')
            ->first();

        if (! $record) {
            return [
                'success' => false,
                'message' => 'No active OTP found. Please request a new OTP.',
            ];
        }

        if ($record->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.',
            ];
        }

        if ($record->maxAttemptsReached()) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts reached.',
            ];
        }

        if (! $record->matches($otp)) {
            $record->incrementAttempts();

            return [
                'success' => false,
                'message' => 'Invalid OTP.',
                'remaining_attempts' => max(
                    0,
                    config('fastsheba.otp.max_attempts', 3)
                    - $record->attempts
                ),
            ];
        }

        if ($consume) {
            $record->markAsVerified();
        }

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'mobile' => $mobile,
        ];
    }

    public function resolveMobileUser(
        string $mobile,
        array $validated
    ): array {
        $user = User::query()
            ->whereIn('mobile', $this->mobileCandidates($mobile))
            ->first();

        if ($user) {
            if ($user->status !== 'active') {
                throw new \RuntimeException(
                    'Your account is inactive.'
                );
            }

            if (! $user->mobile_verified_at) {
                $user->forceFill([
                    'mobile' => $mobile,
                    'country_code' => '+880',
                    'mobile_verified_at' => now(),
                ])->save();
            }

            return [
                'status' => 'existing',
                'user' => $user,
            ];
        }

        if (
            empty($validated['name'])
            || empty($validated['password'])
        ) {
            return [
                'status' => 'pending',
                'mobile' => $mobile,
            ];
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile' => $mobile,
            'country_code' => '+880',
            'password' => $validated['password'],
            'country' => $validated['country'] ?? 'Bangladesh',
            'iso_2' => strtoupper($validated['iso_2'] ?? 'BD'),
            'friends_code' => $validated['friends_code'] ?? null,
            'referral_code' => $this->generateReferralCode(),
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
            'mobile_verified_at' => now(),
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
            'status' => 'created',
            'user' => $user,
        ];
    }

    public function mobileCandidates(string $mobile): array
    {
        $local = $this->sanitizeMobile($mobile);
        $withoutZero = ltrim($local, '0');

        return array_values(array_unique([
            $local,
            $withoutZero,
            '880'.$withoutZero,
            '+880'.$withoutZero,
        ]));
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