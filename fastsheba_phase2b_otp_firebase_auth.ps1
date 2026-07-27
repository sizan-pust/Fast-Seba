param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $directory = Split-Path $Path -Parent
    if ($directory) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Backup-File([string]$Path, [string]$BackupRoot) {
    if (Test-Path $Path) {
        $relative = $Path.Substring($BackendPath.Length).TrimStart("\")
        $destination = Join-Path $BackupRoot $relative
        $directory = Split-Path $destination -Parent
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
        Copy-Item $Path $destination -Force
    }
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase2b_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$targets = @(
    "$BackendPath\routes\api.php",
    "$BackendPath\config\fastsheba.php",
    "$BackendPath\database\seeders\DatabaseSeeder.php",
    "$BackendPath\app\Http\Controllers\Api\User\AuthApiController.php",
    "$BackendPath\app\Http\Controllers\Api\User\OtpApiController.php",
    "$BackendPath\app\Models\UserOtp.php",
    "$BackendPath\app\Services\OtpService.php",
    "$BackendPath\app\Services\SmsService.php",
    "$BackendPath\app\Services\FirebaseAuthService.php",
    "$BackendPath\app\Services\SocialAuthService.php",
    "$BackendPath\tests\Feature\Api\OtpSocialAuthApiTest.php"
)

foreach ($path in $targets) {
    Backup-File $path $backupRoot
}

Write-Utf8NoBom -Path "$BackendPath\config\fastsheba.php" -Content @'
<?php

return [
    'apps' => [
        'customer' => [
            'latest_version' => env('CUSTOMER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('CUSTOMER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('CUSTOMER_ANDROID_URL', ''),
            'ios_url' => env('CUSTOMER_IOS_URL', ''),
        ],
        'seller' => [
            'latest_version' => env('SELLER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('SELLER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('SELLER_ANDROID_URL', ''),
            'ios_url' => env('SELLER_IOS_URL', ''),
        ],
        'rider' => [
            'latest_version' => env('RIDER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('RIDER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('RIDER_ANDROID_URL', ''),
            'ios_url' => env('RIDER_IOS_URL', ''),
        ],
        'web' => [
            'latest_version' => env('WEB_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('WEB_APP_MIN_VERSION', '1.0.0'),
            'android_url' => '',
            'ios_url' => '',
        ],
    ],

    'otp' => [
        'expires_in_seconds' => (int) env('OTP_EXPIRES_IN', 600),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 3),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'test_mode' => (bool) env('OTP_TEST_MODE', false),
        'test_code' => (string) env('OTP_TEST_CODE', '123456'),
    ],

    'firebase' => [
        'service_account_path' => env(
            'FIREBASE_CREDENTIALS',
            storage_path('app/private/settings/service-account-file.json')
        ),
    ],
];
'@

Write-Utf8NoBom -Path "$BackendPath\database\migrations\2026_07_28_020000_create_user_otps_table.php" -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile')->index();
            $table->string('otp');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['mobile', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\UserOtp.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserOtp extends Model
{
    protected $fillable = [
        'mobile',
        'otp',
        'expires_at',
        'verified_at',
        'attempts',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('expires_at', '>', now())
            ->whereNull('verified_at');
    }

    public function scopeForMobile(
        Builder $query,
        string $mobile
    ): Builder {
        return $query->where('mobile', $mobile);
    }

    public function matches(string $otp): bool
    {
        return Hash::check($otp, $this->otp);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function maxAttemptsReached(): bool
    {
        return $this->attempts >= config(
            'fastsheba.otp.max_attempts',
            3
        );
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        $this->refresh();
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Requests\Auth\SendOtpRequest.php" -Content @'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:32'],
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Requests\Auth\VerifyOtpRequest.php" -Content @'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Requests\Auth\GoogleCallbackRequest.php" -Content @'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idToken' => ['required', 'string'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Requests\Auth\AppleCallbackRequest.php" -Content @'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AppleCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idToken' => ['required', 'string'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\SmsService.php" -Content @'
<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(
        protected SettingService $settingService
    ) {
    }

    public function sendSms(
        string $mobile,
        string $message
    ): array {
        $config = $this->settingService
            ->getSettingValues('authentication');

        if (empty($config['customSms'])) {
            return [
                'success' => false,
                'message' => 'Custom SMS is not enabled.',
            ];
        }

        return $this->sendCustomSms($mobile, $message, $config);
    }

    private function sendCustomSms(
        string $mobile,
        string $message,
        array $config
    ): array {
        try {
            $url = (string) ($config['customSmsUrl'] ?? '');

            if ($url === '') {
                return [
                    'success' => false,
                    'message' => 'SMS gateway URL is missing.',
                ];
            }

            $method = strtoupper(
                (string) ($config['customSmsMethod'] ?? 'GET')
            );

            $bag = [
                '{mobile}' => $mobile,
                '{message}' => $message,
            ];

            $headers = $this->buildPairs(
                $config['customSmsHeaderKey'] ?? [],
                $config['customSmsHeaderValue'] ?? [],
                $bag
            );

            $query = $this->buildPairs(
                $config['customSmsParamsKey'] ?? [],
                $config['customSmsParamsValue'] ?? [],
                $bag
            );

            $body = $this->buildPairs(
                $config['customSmsBodyKey'] ?? [],
                $config['customSmsBodyValue'] ?? [],
                $bag
            );

            $url = strtr(
                $url,
                array_map('urlencode', $bag)
            );

            $request = Http::withHeaders($headers)
                ->timeout(8)
                ->connectTimeout(3);

            $encoded = $this->applyBodyEncoding(
                $request,
                $headers
            );

            $response = match ($method) {
                'POST' => $encoded->post(
                    $url.($query ? '?'.http_build_query($query) : ''),
                    $body
                ),
                'PUT' => $encoded->put($url, $body),
                'PATCH' => $encoded->patch($url, $body),
                default => $request->get(
                    $url,
                    array_merge($query, $body)
                ),
            };

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully.',
                ];
            }

            Log::error('Custom SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS gateway returned an error.',
            ];
        } catch (\Throwable $e) {
            Log::error('Custom SMS exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function applyBodyEncoding(
        PendingRequest $request,
        array $headers
    ): PendingRequest {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, 'Content-Type') !== 0) {
                continue;
            }

            $contentType = strtolower((string) $value);

            if (str_contains($contentType, 'application/json')) {
                return $request->asJson();
            }

            if (str_contains(
                $contentType,
                'multipart/form-data'
            )) {
                return $request->asMultipart();
            }
        }

        return $request->asForm();
    }

    private function buildPairs(
        array $keys,
        array $values,
        array $bag
    ): array {
        $output = [];

        foreach ($keys as $index => $key) {
            if ($key === null || $key === '') {
                continue;
            }

            $value = $values[$index] ?? '';

            $output[(string) $key] = is_string($value)
                ? strtr($value, $bag)
                : $value;
        }

        return $output;
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\OtpService.php" -Content @'
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
        string $otp
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

        $record->markAsVerified();

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
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\FirebaseAuthService.php" -Content @'
<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    public function verify(string $idToken): array
    {
        $path = (string) config(
            'fastsheba.firebase.service_account_path'
        );

        if (! is_file($path)) {
            throw new \RuntimeException(
                'Firebase service account file was not found.'
            );
        }

        $auth = (new Factory())
            ->withServiceAccount($path)
            ->createAuth();

        $verified = $auth->verifyIdToken($idToken);
        $uid = (string) $verified->claims()->get('sub');
        $record = $auth->getUser($uid);
        $claims = $verified->claims()->all();

        return [
            'uid' => $uid,
            'email' => $record->email ?? ($claims['email'] ?? null),
            'name' => $record->displayName
                ?? ($claims['name'] ?? null),
            'phone_number' => $record->phoneNumber
                ?? ($claims['phone_number'] ?? null),
            'email_verified' => (bool) (
                $record->emailVerified
                ?? ($claims['email_verified'] ?? false)
            ),
            'claims' => $claims,
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\SocialAuthService.php" -Content @'
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
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\OtpApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DeviceTokenService;
use App\Services\OtpService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class OtpApiController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected DeviceTokenService $deviceTokenService
    ) {
    }

    public function sendOtp(
        SendOtpRequest $request
    ): JsonResponse {
        $result = $this->otpService->sendOtp(
            $request->validated('mobile')
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? [
                'retry_after' => $result['retry_after'] ?? null,
            ],
            $result['success'] ? 200 : 422
        );
    }

    public function verifyOtp(
        VerifyOtpRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $mobile = $this->otpService->sanitizeMobile(
            $validated['mobile']
        );

        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp']
        );

        if (! $verification['success']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $verification['message'],
                $verification,
                422
            );
        }

        $authedUser = auth('sanctum')->user();

        if ($authedUser) {
            $conflict = User::query()
                ->where('id', '!=', $authedUser->id)
                ->whereIn(
                    'mobile',
                    $this->otpService->mobileCandidates($mobile)
                )
                ->exists();

            if ($conflict) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'This mobile number is already in use.',
                    [],
                    422
                );
            }

            $authedUser->forceFill([
                'mobile' => $mobile,
                'country_code' => '+880',
                'mobile_verified_at' => now(),
                'name' => $validated['name']
                    ?? $authedUser->name,
                'friends_code' => $validated['friends_code']
                    ?? $authedUser->friends_code,
            ])->save();

            return ApiResponseType::sendJsonResponse(
                true,
                'Mobile verified successfully.',
                new UserResource($authedUser->fresh())
            );
        }

        $resolved = $this->otpService->resolveMobileUser(
            $mobile,
            $validated
        );

        if ($resolved['status'] === 'pending') {
            return ApiResponseType::sendJsonResponse(
                true,
                'OTP verified. Registration details are required.',
                [
                    'new_user' => true,
                    'mobile' => $mobile,
                    'is_register' => true,
                ]
            );
        }

        $user = $resolved['user'];

        if (! empty($validated['fcm_token'])) {
            $this->deviceTokenService->sync(
                $user,
                $validated['fcm_token'],
                $validated['device_type'] ?? null,
                'customer'
            );
        }

        return $this->tokenResponse(
            $user,
            $resolved['status'] === 'created'
        );
    }

    private function tokenResponse(
        User $user,
        bool $isRegister
    ): JsonResponse {
        $data = (new UserResource($user->fresh()))
            ->resolve(request());

        $data['is_register'] = $isRegister;

        return response()->json([
            'success' => true,
            'message' => $isRegister
                ? 'Registration successful.'
                : 'Verified successfully.',
            'access_token' => $user
                ->createToken($user->mobile ?? 'mobile-api')
                ->plainTextToken,
            'token_type' => 'Bearer',
            'data' => $data,
        ]);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\AuthApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AppleCallbackRequest;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DeviceTokenService;
use App\Services\FirebaseAuthService;
use App\Services\OtpService;
use App\Services\SettingService;
use App\Services\SocialAuthService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly DeviceTokenService $deviceTokenService,
        private readonly FirebaseAuthService $firebaseAuthService,
        private readonly SocialAuthService $socialAuthService,
        private readonly OtpService $otpService
    ) {
    }

    public function verifyUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:email,mobile'],
            'value' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $validated['type'] === 'email'
            ? User::query()
                ->where('email', $validated['value'])
                ->first()
            : User::query()
                ->whereIn(
                    'mobile',
                    $this->buildMobileCandidates(
                        $validated['value'],
                        $validated['country_code'] ?? null
                    )
                )
                ->first();

        return ApiResponseType::sendJsonResponse(
            $user !== null,
            $user ? 'User found.' : 'User not found.',
            [
                'exists' => $user !== null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'country_code' => $validated['country_code'] ?? null,
            ]
        );
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:mobile', 'nullable', 'email'],
            'mobile' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        $query = User::query()
            ->where('access_panel', GuardNameEnum::WEB->value);

        if (! empty($validated['email'])) {
            $query->where('email', $validated['email']);
        } else {
            $query->whereIn(
                'mobile',
                $this->otpService->mobileCandidates(
                    $validated['mobile']
                )
            );
        }

        $user = $query->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid credentials.',
                [],
                401
            );
        }

        if ($user->status !== 'active') {
            return ApiResponseType::sendJsonResponse(
                false,
                'Your account is inactive.',
                [],
                403
            );
        }

        $this->storeFcmToken($request, $user);

        return $this->respondWithToken(
            $request,
            $user,
            false,
            'Login successful.'
        );
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
            ],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        $mobile = $this->otpService->sanitizeMobile(
            $validated['mobile']
        );

        if (User::query()->whereIn(
            'mobile',
            $this->otpService->mobileCandidates($mobile)
        )->exists()) {
            throw ValidationException::withMessages([
                'mobile' => [
                    'The mobile number has already been taken.',
                ],
            ]);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $mobile,
            'password' => $validated['password'],
            'country' => $validated['country'] ?? 'Bangladesh',
            'iso_2' => strtoupper($validated['iso_2'] ?? 'BD'),
            'country_code' => '+880',
            'referral_code' => $this->generateReferralCode(),
            'friends_code' => $validated['friends_code'] ?? null,
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
        ]);

        $user->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);

        $system = $this->settingService
            ->getSettingValues('system');

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => WalletTypeEnum::CUSTOMER->value,
            'balance' => max(
                0,
                (float) (
                    $system['welcomeWalletBalanceAmount'] ?? 0
                )
            ),
            'blocked_balance' => 0,
            'currency_code' => $system['currencyCode'] ?? 'BDT',
        ]);

        $this->storeFcmToken($request, $user);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
        }

        return $this->respondWithToken(
            $request,
            $user,
            true,
            'Registration successful. Verification email sent.'
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return ApiResponseType::sendJsonResponse(
            $status === Password::RESET_LINK_SENT,
            __($status),
            []
        );
    }

    public function googleCallback(
        GoogleCallbackRequest $request
    ): JsonResponse {
        $auth = $this->settingService
            ->getSettingValues('authentication');

        if (empty($auth['googleLogin'])) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Google login is not enabled.',
                [],
                422
            );
        }

        try {
            $profile = $this->firebaseAuthService->verify(
                $request->validated('idToken')
            );

            $result = $this->socialAuthService
                ->loginOrRegisterFromGoogle(
                    $profile,
                    $request->validated('friends_code'),
                    [
                        'country' => $request->validated('country'),
                        'iso_2' => $request->validated('iso_2'),
                    ]
                );

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                $result['is_new'],
                $result['is_new']
                    ? 'Registration successful.'
                    : 'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
    }

    public function appleCallback(
        AppleCallbackRequest $request
    ): JsonResponse {
        $auth = $this->settingService
            ->getSettingValues('authentication');

        if (empty($auth['appleLogin'])) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Apple login is not enabled.',
                [],
                422
            );
        }

        try {
            $profile = $this->firebaseAuthService->verify(
                $request->validated('idToken')
            );

            $result = $this->socialAuthService
                ->loginOrRegisterFromApple(
                    $profile,
                    $request->validated('friends_code'),
                    [
                        'country' => $request->validated('country'),
                        'iso_2' => $request->validated('iso_2'),
                    ]
                );

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                $result['is_new'],
                $result['is_new']
                    ? 'Registration successful.'
                    : 'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
    }

    public function phoneCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idToken' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        try {
            $profile = $this->firebaseAuthService->verify(
                $validated['idToken']
            );

            if (empty($profile['phone_number'])) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'Phone number was not found in Firebase.',
                    [],
                    422
                );
            }

            $authedUser = auth('sanctum')->user();

            if ($authedUser) {
                $mobile = $this->otpService->sanitizeMobile(
                    $profile['phone_number']
                );

                $conflict = User::query()
                    ->where('id', '!=', $authedUser->id)
                    ->whereIn(
                        'mobile',
                        $this->otpService->mobileCandidates($mobile)
                    )
                    ->exists();

                if ($conflict) {
                    return ApiResponseType::sendJsonResponse(
                        false,
                        'This mobile number is already in use.',
                        [],
                        422
                    );
                }

                $authedUser->forceFill([
                    'mobile' => $mobile,
                    'country_code' => '+880',
                    'firebase_uid' => $profile['uid'],
                    'mobile_verified_at' => now(),
                    'name' => $validated['name']
                        ?? $authedUser->name,
                ])->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Mobile verified successfully.',
                    'token' => $request->bearerToken(),
                    'data' => new UserResource(
                        $authedUser->fresh()
                    ),
                ]);
            }

            $result = $this->socialAuthService->loginWithPhone(
                $profile,
                $validated['name'] ?? null
            );

            if (! $result['user']) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'User not found.',
                    [],
                    404
                );
            }

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                false,
                'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['fcm_token'])) {
            $this->deviceTokenService->forget(
                $request->user(),
                $validated['fcm_token']
            );
        }

        $request->user()->currentAccessToken()?->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Logout successful.',
            []
        );
    }

    private function respondWithToken(
        Request $request,
        User $user,
        bool $isRegister,
        string $message
    ): JsonResponse {
        $data = (new UserResource($user->fresh()))
            ->resolve($request);

        $data['is_register'] = $isRegister;

        return response()->json([
            'success' => true,
            'message' => $message,
            'access_token' => $user->createToken(
                $user->email
                ?? $user->mobile
                ?? $user->firebase_uid
                ?? 'api-token'
            )->plainTextToken,
            'token_type' => 'Bearer',
            'data' => $data,
            'assigned_permissions' => [],
        ]);
    }

    private function storeFcmToken(
        Request $request,
        User $user
    ): void {
        $token = $request->input('fcm_token');

        if (! is_string($token) || $token === '') {
            return;
        }

        $this->deviceTokenService->sync(
            $user,
            $token,
            $request->input('device_type'),
            'customer'
        );
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

    private function buildMobileCandidates(
        string $value,
        ?string $countryCode
    ): array {
        $candidates = $this->otpService
            ->mobileCandidates($value);

        if ($countryCode) {
            $code = preg_replace('/\D+/', '', $countryCode);
            $digits = preg_replace('/\D+/', '', $value);

            $candidates[] = $code.$digits;
            $candidates[] = '+'.$code.$digits;
            $candidates[] = '+'.$code.' '.$digits;
        }

        return array_values(array_unique($candidates));
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\AuthProviderSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class AuthProviderSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::query()->firstOrCreate(
            ['variable' => 'authentication'],
            ['value' => []]
        );

        $setting->value = array_merge([
            'customSms' => false,
            'customSmsUrl' => '',
            'customSmsMethod' => 'GET',
            'customSmsHeaderKey' => [],
            'customSmsHeaderValue' => [],
            'customSmsParamsKey' => [],
            'customSmsParamsValue' => [],
            'customSmsBodyKey' => [],
            'customSmsBodyValue' => [],
            'customSmsTextFormatData' =>
                'Your OTP is: {otp}. Valid for {minutes} minutes.',
            'firebase' => false,
            'googleLogin' => false,
            'appleLogin' => false,
            'smsGateway' => '',
            'fireBaseApiKey' => '',
            'fireBaseAuthDomain' => '',
            'fireBaseProjectId' => '',
            'fireBaseStorageBucket' => '',
            'fireBaseMessagingSenderId' => '',
            'fireBaseAppId' => '',
        ], $setting->value ?? []);

        $setting->save();
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\DatabaseSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            AuthProviderSeeder::class,
        ]);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\routes\api.php" -Content @'
<?php

use App\Http\Controllers\Api\DeliveryZoneApiController;
use App\Http\Controllers\Api\SettingApiController;
use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Controllers\Api\User\OtpApiController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthApiController::class, 'register'])
    ->name('register');

Route::post('login', [AuthApiController::class, 'login'])
    ->name('login');

Route::post(
    'forget-password',
    [AuthApiController::class, 'forgotPassword']
)->name('password');

Route::post(
    'verify-user',
    [AuthApiController::class, 'verifyUser']
);

Route::post(
    'auth/send-otp',
    [OtpApiController::class, 'sendOtp']
)->name('send-otp');

Route::post(
    'auth/verify-otp',
    [OtpApiController::class, 'verifyOtp']
)->name('verify-otp');

Route::post(
    'auth/google/callback',
    [AuthApiController::class, 'googleCallback']
)->name('google-callback');

Route::post(
    'auth/apple/callback',
    [AuthApiController::class, 'appleCallback']
)->name('apple-callback');

Route::post(
    'auth/phone/callback',
    [AuthApiController::class, 'phoneCallback']
)->name('phone-callback');

Route::prefix('settings')->name('api.')->group(function (): void {
    Route::get('/', [SettingApiController::class, 'index'])
        ->name('settings.index');

    Route::get(
        'firebase-config',
        [SettingApiController::class, 'firebaseConfig']
    )->name('settings.firebase-config');

    Route::get(
        'check-version',
        [SettingApiController::class, 'checkVersion']
    )->name('settings.check-version');

    Route::get(
        'variables',
        [SettingApiController::class, 'settingVariables']
    )->name('settings.variables');

    Route::get(
        '{setting}',
        [SettingApiController::class, 'show']
    )->name('settings.show');
});

Route::prefix('delivery-zone')
    ->name('delivery_zone.')
    ->group(function (): void {
        Route::get('/', [DeliveryZoneApiController::class, 'index']);
        Route::get(
            'check',
            [DeliveryZoneApiController::class, 'checkDelivery']
        );
        Route::get(
            'search',
            [DeliveryZoneApiController::class, 'search']
        )->name('search');
        Route::get(
            '{id}',
            [DeliveryZoneApiController::class, 'show']
        );
    });

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthApiController::class, 'logout']);

    Route::post(
        'devices/sync',
        [DeviceTokenController::class, 'sync']
    );

    Route::delete(
        'devices',
        [DeviceTokenController::class, 'forget']
    );

    Route::prefix('user')->name('user.')->group(function (): void {
        Route::get(
            'profile',
            [UserApiController::class, 'getProfile']
        );

        Route::post(
            'profile',
            [UserApiController::class, 'updateProfile']
        );

        Route::post(
            'change-password',
            [UserApiController::class, 'changePassword']
        )->name('change-password');

        Route::post(
            'update-email',
            [UserApiController::class, 'updateEmail']
        )->name('update-email');

        Route::post(
            'email/verification-notification',
            [UserApiController::class, 'resendEmailVerification']
        )->middleware('throttle:6,1')
            ->name('verification.send');

        Route::delete(
            'delete-account',
            [UserApiController::class, 'deleteAccount']
        )->name('delete-account');
    });
});
'@

Write-Utf8NoBom -Path "$BackendPath\tests\Feature\Api\OtpSocialAuthApiTest.php" -Content @'
<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FirebaseAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OtpSocialAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fastsheba.otp.test_mode' => true,
            'fastsheba.otp.test_code' => '123456',
            'fastsheba.otp.resend_cooldown_seconds' => 0,
        ]);

        Role::findOrCreate(
            DefaultSystemRolesEnum::CUSTOMER->value,
            GuardNameEnum::WEB->value
        );

        Setting::query()->create([
            'variable' => 'system',
            'value' => [
                'currencyCode' => 'BDT',
                'welcomeWalletBalanceAmount' => 0,
            ],
        ]);

        Setting::query()->create([
            'variable' => 'authentication',
            'value' => [
                'customSms' => false,
                'googleLogin' => true,
                'appleLogin' => true,
            ],
        ]);
    }

    public function test_otp_can_be_sent_in_local_test_mode(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000000',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mobile', '01710000000')
            ->assertJsonPath('data.debug_otp', '123456');

        $this->assertDatabaseHas('user_otps', [
            'mobile' => '01710000000',
            'attempts' => 0,
        ]);
    }

    public function test_invalid_otp_increments_attempts(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000001',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000001',
            'otp' => '654321',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.remaining_attempts', 2);

        $this->assertDatabaseHas('user_otps', [
            'mobile' => '01710000001',
            'attempts' => 1,
        ]);
    }

    public function test_verified_unknown_mobile_returns_new_user_hint(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000002',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000002',
            'otp' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.new_user', true)
            ->assertJsonPath('data.is_register', true);
    }

    public function test_otp_can_register_new_customer(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000003',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000003',
            'otp' => '123456',
            'name' => 'OTP Customer',
            'email' => 'otp@example.test',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_register', true)
            ->assertJsonPath('data.mobile', '01710000003')
            ->assertJsonPath('data.wallet_type', 'customer');

        $user = User::query()
            ->where('mobile', '01710000003')
            ->firstOrFail();

        $this->assertNotNull($user->mobile_verified_at);
        $this->assertTrue($user->hasRole('customer'));

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'type' => 'customer',
        ]);
    }

    public function test_otp_logs_in_existing_customer(): void
    {
        $user = User::query()->create([
            'name' => 'Existing OTP Customer',
            'email' => 'existing-otp@example.test',
            'mobile' => '01710000004',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'customer',
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000004',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000004',
            'otp' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_register', false)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_google_callback_registers_customer(): void
    {
        $this->mock(
            FirebaseAuthService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('verify')
                    ->once()
                    ->with('google-token')
                    ->andReturn([
                        'uid' => 'google-uid-1',
                        'email' => 'google@example.test',
                        'name' => 'Google Customer',
                        'phone_number' => null,
                        'email_verified' => true,
                        'claims' => [],
                    ]);
            }
        );

        $this->postJson('/api/auth/google/callback', [
            'idToken' => 'google-token',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'google@example.test')
            ->assertJsonPath('data.logged_in_type', 'google')
            ->assertJsonPath('data.is_register', true);
    }

    public function test_apple_callback_supports_user_without_email(): void
    {
        $this->mock(
            FirebaseAuthService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('verify')
                    ->once()
                    ->with('apple-token')
                    ->andReturn([
                        'uid' => 'apple-uid-1',
                        'email' => null,
                        'name' => null,
                        'phone_number' => null,
                        'email_verified' => false,
                        'claims' => [],
                    ]);
            }
        );

        $this->postJson('/api/auth/apple/callback', [
            'idToken' => 'apple-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.logged_in_type', 'apple')
            ->assertJsonPath('data.is_register', true);

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'apple-uid-1',
            'email' => null,
        ]);
    }

    public function test_phone_callback_logs_in_existing_customer(): void
    {
        $user = User::query()->create([
            'name' => 'Phone Customer',
            'email' => 'phone@example.test',
            'mobile' => '01710000005',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'customer',
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->mock(
            FirebaseAuthService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('verify')
                    ->once()
                    ->with('phone-token')
                    ->andReturn([
                        'uid' => 'phone-uid-1',
                        'email' => null,
                        'name' => null,
                        'phone_number' => '+8801710000005',
                        'email_verified' => false,
                        'claims' => [],
                    ]);
            }
        );

        $this->postJson('/api/auth/phone/callback', [
            'idToken' => 'phone-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_register', false);
    }
}
'@

Write-Host ""
Write-Host "Phase 2B OTP and Firebase authentication files written." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate"
Write-Host "  herd php artisan db:seed --class=AuthProviderSeeder"
Write-Host "  herd php artisan route:list --path=api/auth"
Write-Host "  herd php artisan test"
