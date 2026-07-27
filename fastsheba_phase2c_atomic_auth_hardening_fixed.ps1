param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
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

$authControllerPath = Join-Path $BackendPath "app\Http\Controllers\Api\User\AuthApiController.php"
$otpControllerPath = Join-Path $BackendPath "app\Http\Controllers\Api\User\OtpApiController.php"
$otpServicePath = Join-Path $BackendPath "app\Services\OtpService.php"
$socialServicePath = Join-Path $BackendPath "app\Services\SocialAuthService.php"
$authTestPath = Join-Path $BackendPath "tests\Feature\Api\AuthPublicApiTest.php"
$otpTestPath = Join-Path $BackendPath "tests\Feature\Api\OtpSocialAuthApiTest.php"

$required = @(
    $authControllerPath,
    $otpControllerPath,
    $otpServicePath,
    $socialServicePath,
    $authTestPath,
    $otpTestPath
)

foreach ($path in $required) {
    if (-not (Test-Path $path)) {
        throw "Required file not found: $path"
    }
}

$authController = [System.IO.File]::ReadAllText($authControllerPath)
$otpController = [System.IO.File]::ReadAllText($otpControllerPath)
$otpService = [System.IO.File]::ReadAllText($otpServicePath)
$socialService = [System.IO.File]::ReadAllText($socialServicePath)
$authTest = [System.IO.File]::ReadAllText($authTestPath)
$otpTest = [System.IO.File]::ReadAllText($otpTestPath)

$hasAtomicRegister = $authController.Contains(
    '$user = DB::transaction('
)

$hasOtpConsumeMethod = $otpService.Contains(
    'public function consumeOtp(string $mobile): void'
)

$hasAtomicSocialAuth = $socialService.Contains(
    '$user = DB::transaction('
)

if (
    $hasAtomicRegister -and
    $hasOtpConsumeMethod -and
    $hasAtomicSocialAuth
) {
    Write-Host ""
    Write-Host "Phase 2C atomic auth hardening is already applied." -ForegroundColor Yellow
    exit 0
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase2c_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

foreach ($path in $required) {
    Backup-File $path $backupRoot
}

# ------------------------------------------------------------------
# AuthApiController: wrap standard registration core writes in a DB
# transaction so user/role/wallet/device-token succeed or fail together.
# ------------------------------------------------------------------

$oldAuthImport = @'
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
'@

$newAuthImport = @'
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
'@

if (-not $authController.Contains($oldAuthImport)) {
    throw "AuthApiController import marker was not found."
}

$authController = $authController.Replace(
    $oldAuthImport,
    $newAuthImport
)

$oldRegisterBlock = @'
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
'@

$newRegisterBlock = @'
        $user = DB::transaction(
            function () use ($validated, $mobile, $request): User {
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

                return $user;
            }
        );
'@

if (-not $authController.Contains($oldRegisterBlock)) {
    throw "AuthApiController registration block was not found."
}

$authController = $authController.Replace(
    $oldRegisterBlock,
    $newRegisterBlock
)

# ------------------------------------------------------------------
# OtpService: transaction for new OTP customer creation.
# Add explicit OTP consumption method.
# ------------------------------------------------------------------

$oldOtpImport = @'
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
'@

$newOtpImport = @'
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
'@

if (-not $otpService.Contains($oldOtpImport)) {
    throw "OtpService import marker was not found."
}

$otpService = $otpService.Replace(
    $oldOtpImport,
    $newOtpImport
)

$verifyReturnMarker = @'
        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'mobile' => $mobile,
        ];
    }

    public function resolveMobileUser(
'@

$verifyReturnReplacement = @'
        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'mobile' => $mobile,
        ];
    }

    public function consumeOtp(string $mobile): void
    {
        $record = UserOtp::query()
            ->forMobile($this->sanitizeMobile($mobile))
            ->active()
            ->latest('created_at')
            ->first();

        $record?->markAsVerified();
    }

    public function resolveMobileUser(
'@

if (-not $otpService.Contains($verifyReturnMarker)) {
    throw "OtpService consumeOtp insertion marker was not found."
}

$otpService = $otpService.Replace(
    $verifyReturnMarker,
    $verifyReturnReplacement
)

$oldOtpCreateBlock = @'
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
'@

$newOtpCreateBlock = @'
        return DB::transaction(
            function () use ($mobile, $validated): array {
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
        );
'@

if (-not $otpService.Contains($oldOtpCreateBlock)) {
    throw "OtpService customer creation block was not found."
}

$otpService = $otpService.Replace(
    $oldOtpCreateBlock,
    $newOtpCreateBlock
)

# ------------------------------------------------------------------
# OtpApiController: validate first, consume only after successful
# account update/login/registration.
# ------------------------------------------------------------------

$oldConsumeDecision = @'
        $hasRegistrationDetails = ! empty($validated['name'])
            && ! empty($validated['password']);

        $consumeOtp = $authenticatedUser !== null
            || $existingUser
            || $hasRegistrationDetails;

        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp'],
            $consumeOtp
        );
'@

$newConsumeDecision = @'
        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp'],
            false
        );
'@

if (-not $otpController.Contains($oldConsumeDecision)) {
    throw "OtpApiController OTP-consumption decision block was not found."
}

$otpController = $otpController.Replace(
    $oldConsumeDecision,
    $newConsumeDecision
)

$oldAuthedSave = @'
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
'@

$newAuthedSave = @'
            $authedUser->forceFill([
                'mobile' => $mobile,
                'country_code' => '+880',
                'mobile_verified_at' => now(),
                'name' => $validated['name']
                    ?? $authedUser->name,
                'friends_code' => $validated['friends_code']
                    ?? $authedUser->friends_code,
            ])->save();

            $this->otpService->consumeOtp($mobile);

            return ApiResponseType::sendJsonResponse(
'@

if (-not $otpController.Contains($oldAuthedSave)) {
    throw "OtpApiController authenticated-user save block was not found."
}

$otpController = $otpController.Replace(
    $oldAuthedSave,
    $newAuthedSave
)

$oldResolvedUser = @'
        $user = $resolved['user'];

        if (! empty($validated['fcm_token'])) {
'@

$newResolvedUser = @'
        $user = $resolved['user'];

        $this->otpService->consumeOtp($mobile);

        if (! empty($validated['fcm_token'])) {
'@

if (-not $otpController.Contains($oldResolvedUser)) {
    throw "OtpApiController resolved-user marker was not found."
}

$otpController = $otpController.Replace(
    $oldResolvedUser,
    $newResolvedUser
)

# ------------------------------------------------------------------
# SocialAuthService: transaction for new Google/Apple account core.
# ------------------------------------------------------------------

$oldSocialImport = @'
use App\Models\Wallet;
use Illuminate\Support\Str;
'@

$newSocialImport = @'
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
'@

if (-not $socialService.Contains($oldSocialImport)) {
    throw "SocialAuthService import marker was not found."
}

$socialService = $socialService.Replace(
    $oldSocialImport,
    $newSocialImport
)

$oldSocialCreateBlock = @'
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
'@

$newSocialCreateBlock = @'
        $user = DB::transaction(
            function () use (
                $profile,
                $email,
                $uid,
                $type,
                $friendsCode,
                $extra
            ): User {
                $user = User::query()->create([
                    'name' => $profile['name']
                        ?: ($email ?: (
                            $type === UserLoginTypeEnum::APPLE
                                ? 'Apple User'
                                : 'User'
                        )),
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

                return $user;
            }
        );

        return [
            'user' => $user,
            'is_new' => true,
        ];
'@

if (-not $socialService.Contains($oldSocialCreateBlock)) {
    throw "SocialAuthService customer creation block was not found."
}

$socialService = $socialService.Replace(
    $oldSocialCreateBlock,
    $newSocialCreateBlock
)

# ------------------------------------------------------------------
# Regression tests: no half-created user and no consumed OTP when a
# downstream role assignment fails.
# ------------------------------------------------------------------

$authTestInsert = @'
    public function test_customer_can_login_and_receive_expected_resource(): void
'@

$authTransactionTest = @'
    public function test_registration_rolls_back_when_role_assignment_fails(): void
    {
        Role::query()
            ->where('name', DefaultSystemRolesEnum::CUSTOMER->value)
            ->where('guard_name', GuardNameEnum::WEB->value)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->postJson('/api/register', [
            'name' => 'Rollback Customer',
            'email' => 'rollback@example.test',
            'mobile' => '01719999991',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('users', [
            'email' => 'rollback@example.test',
        ]);

        $this->assertDatabaseMissing('wallets', [
            'currency_code' => 'BDT',
        ]);
    }

    public function test_customer_can_login_and_receive_expected_resource(): void
'@

if (-not $authTest.Contains($authTestInsert)) {
    throw "AuthPublicApiTest insertion marker was not found."
}

$authTest = $authTest.Replace(
    $authTestInsert,
    $authTransactionTest
)

$otpTestInsert = @'
    public function test_otp_can_register_new_customer(): void
'@

$otpTransactionTest = @'
    public function test_failed_otp_registration_rolls_back_and_keeps_otp_active(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000007',
        ])->assertOk();

        Role::query()
            ->where('name', DefaultSystemRolesEnum::CUSTOMER->value)
            ->where('guard_name', GuardNameEnum::WEB->value)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000007',
            'otp' => '123456',
            'name' => 'OTP Rollback Customer',
            'email' => 'otp-rollback@example.test',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('users', [
            'email' => 'otp-rollback@example.test',
        ]);

        $this->assertDatabaseHas('user_otps', [
            'mobile' => '01710000007',
            'verified_at' => null,
        ]);
    }

    public function test_otp_can_register_new_customer(): void
'@

if (-not $otpTest.Contains($otpTestInsert)) {
    throw "OtpSocialAuthApiTest insertion marker was not found."
}

$otpTest = $otpTest.Replace(
    $otpTestInsert,
    $otpTransactionTest
)

Write-Utf8NoBom -Path $authControllerPath -Content $authController
Write-Utf8NoBom -Path $otpControllerPath -Content $otpController
Write-Utf8NoBom -Path $otpServicePath -Content $otpService
Write-Utf8NoBom -Path $socialServicePath -Content $socialService
Write-Utf8NoBom -Path $authTestPath -Content $authTest
Write-Utf8NoBom -Path $otpTestPath -Content $otpTest

Write-Host ""
Write-Host "Phase 2C atomic authentication hardening applied." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan test"
