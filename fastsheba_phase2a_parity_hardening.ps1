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
$backupRoot = Join-Path $BackendPath "_phase2a_parity_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$authController = "$BackendPath\app\Http\Controllers\Api\User\AuthApiController.php"
$userResource = "$BackendPath\app\Http\Resources\UserResource.php"
$authTest = "$BackendPath\tests\Feature\Api\AuthPublicApiTest.php"
$publicTest = "$BackendPath\tests\Feature\Api\SettingsDeliveryZoneApiTest.php"

Backup-File $authController $backupRoot
Backup-File $userResource $backupRoot
Backup-File $authTest $backupRoot
Backup-File $publicTest $backupRoot

Write-Utf8NoBom -Path $userResource -Content @'
<?php

namespace App\Http\Resources;

use App\Enums\WalletTypeEnum;
use App\Models\Wallet;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected ?WalletTypeEnum $walletType = null;

    public function withWalletType(
        WalletTypeEnum|string $type
    ): self {
        $this->walletType = $type instanceof WalletTypeEnum
            ? $type
            : (
                WalletTypeEnum::tryFrom($type)
                ?? WalletTypeEnum::CUSTOMER
            );

        return $this;
    }

    public function toArray(Request $request): array
    {
        $wallet = $this->resolveWallet($request);

        $balance = (float) ($wallet?->balance ?? 0);
        $blockedBalance = (float) ($wallet?->blocked_balance ?? 0);

        $loggedInType = $this->logged_in_type;
        if ($loggedInType instanceof BackedEnum) {
            $loggedInType = $loggedInType->value;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country_code' => $this->country_code,
            'country' => $this->country,
            'iso_2' => $this->iso_2,
            'wallet_type' => $wallet?->type instanceof BackedEnum
                ? $wallet->type->value
                : (
                    $wallet?->type
                    ?? $this->currentWalletType($request)->value
                ),
            'wallet_balance' => number_format(
                $balance,
                2,
                '.',
                ''
            ),
            'blocked_balance' => number_format(
                $blockedBalance,
                2,
                '.',
                ''
            ),
            'available_balance' => number_format(
                $balance - $blockedBalance,
                2,
                '.',
                ''
            ),
            'referral_code' => $this->referral_code,
            'friends_code' => $this->friends_code,
            'reward_points' => $this->reward_points ?? '0.00',
            'profile_image' => $this->profile_image,
            'email_verified_at' => $this->email_verified_at
                ?->format('Y-m-d H:i:s'),
            'mobile_verified_at' => $this->mobile_verified_at
                ?->format('Y-m-d H:i:s'),
            'logged_in_type' => $loggedInType,
            'created_at' => $this->created_at
                ?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at
                ?->format('Y-m-d H:i:s'),
        ];
    }

    protected function resolveWallet(Request $request): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $this->id)
            ->where(
                'type',
                $this->currentWalletType($request)->value
            )
            ->first();
    }

    protected function currentWalletType(
        Request $request
    ): WalletTypeEnum {
        if ($this->walletType instanceof WalletTypeEnum) {
            return $this->walletType;
        }

        $path = ltrim($request->path(), '/');

        return match (true) {
            str_starts_with(
                $path,
                'api/delivery-boy'
            ) => WalletTypeEnum::DELIVERY_BOY,

            str_starts_with(
                $path,
                'api/seller'
            ) => WalletTypeEnum::SELLER,

            default => WalletTypeEnum::CUSTOMER,
        };
    }
}
'@

$authContent = [System.IO.File]::ReadAllText($authController)

$oldResponse = @'
        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Verification email sent.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'is_register' => true,
            ],
        ], 201);
'@

$newResponse = @'
        $user->refresh();

        $data = (new UserResource($user))->resolve($request);
        $data['is_register'] = true;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Verification email sent.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $data,
        ]);
'@

if (-not $authContent.Contains($oldResponse)) {
    throw "Expected registration response block was not found. The controller may have been edited manually."
}

$authContent = $authContent.Replace($oldResponse, $newResponse)
Write-Utf8NoBom -Path $authController -Content $authContent

Write-Utf8NoBom -Path $authTest -Content @'
<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Wallet;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthPublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

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
    }

    public function test_customer_can_register_with_expected_app_shape(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'FastSheba Customer',
            'email' => 'customer@example.test',
            'mobile' => '01710000000',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
            'device_type' => 'android',
            'fcm_token' => 'register-device-token',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('data.email', 'customer@example.test')
            ->assertJsonPath('data.mobile', '01710000000')
            ->assertJsonPath('data.iso_2', 'BD')
            ->assertJsonPath('data.wallet_type', 'customer')
            ->assertJsonPath('data.wallet_balance', '0.00')
            ->assertJsonPath('data.blocked_balance', '0.00')
            ->assertJsonPath('data.available_balance', '0.00')
            ->assertJsonPath('data.reward_points', '0.00')
            ->assertJsonPath('data.is_register', true);

        $user = User::query()
            ->where('email', 'customer@example.test')
            ->firstOrFail();

        $this->assertTrue($user->hasRole(
            DefaultSystemRolesEnum::CUSTOMER->value
        ));

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'type' => 'customer',
            'currency_code' => 'BDT',
        ]);

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'register-device-token',
            'device_type' => 'android',
            'role_type' => 'customer',
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validation_rejects_duplicate_data(): void
    {
        User::query()->create([
            'name' => 'Existing',
            'email' => 'existing@example.test',
            'mobile' => '01711111111',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
        ]);

        $this->postJson('/api/register', [
            'name' => 'Duplicate',
            'email' => 'existing@example.test',
            'mobile' => '01711111111',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_login_and_receive_expected_resource(): void
    {
        $user = User::query()->create([
            'name' => 'Login Customer',
            'email' => 'login@example.test',
            'mobile' => '01712222222',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'customer',
            'balance' => 125.50,
            'blocked_balance' => 25.50,
            'currency_code' => 'BDT',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'Test@123456',
            'device_type' => 'android',
            'fcm_token' => 'login-device-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'login@example.test')
            ->assertJsonPath('data.wallet_balance', '125.50')
            ->assertJsonPath('data.blocked_balance', '25.50')
            ->assertJsonPath('data.available_balance', '100.00')
            ->assertJsonPath('assigned_permissions', []);
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::query()->create([
            'name' => 'Invalid Login',
            'email' => 'invalid-login@example.test',
            'mobile' => '01713333333',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->postJson('/api/login', [
            'email' => 'invalid-login@example.test',
            'password' => 'WrongPassword',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_verify_user_supports_email_and_mobile(): void
    {
        User::query()->create([
            'name' => 'Verify User',
            'email' => 'verify@example.test',
            'mobile' => '01714444444',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->postJson('/api/verify-user', [
            'type' => 'email',
            'value' => 'verify@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.exists', true);

        $this->postJson('/api/verify-user', [
            'type' => 'mobile',
            'value' => '01714444444',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.exists', true);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/user/profile')
            ->assertUnauthorized();
    }

    public function test_device_token_can_be_synced_replaced_and_forgotten(): void
    {
        $user = User::query()->create([
            'name' => 'Device Customer',
            'email' => 'device@example.test',
            'mobile' => '01715555555',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/devices/sync', [
            'fcm_token' => 'token-old',
            'device_type' => 'android',
            'role_type' => 'customer',
        ])->assertOk();

        $this->postJson('/api/devices/sync', [
            'fcm_token' => 'token-new',
            'previous_token' => 'token-old',
            'device_type' => 'android',
            'role_type' => 'customer',
        ])->assertOk();

        $this->assertDatabaseMissing('user_fcm_tokens', [
            'fcm_token' => 'token-old',
        ]);

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'token-new',
        ]);

        $this->deleteJson('/api/devices', [
            'fcm_token' => 'token-new',
        ])->assertOk();

        $this->assertDatabaseMissing('user_fcm_tokens', [
            'fcm_token' => 'token-new',
        ]);
    }

    public function test_logout_revokes_current_sanctum_token(): void
    {
        $user = User::query()->create([
            'name' => 'Logout Customer',
            'email' => 'logout@example.test',
            'mobile' => '01716666666',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $token = $user->createToken('logout-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
'@

Write-Utf8NoBom -Path $publicTest -Content @'
<?php

namespace Tests\Feature\Api;

use App\Enums\GuardNameEnum;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsDeliveryZoneApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'variable' => 'system',
            'value' => [
                'appName' => 'FastSheba',
                'currencyCode' => 'BDT',
                'currencySymbol' => '৳',
            ],
        ]);

        Setting::query()->create([
            'variable' => 'authentication',
            'value' => [
                'firebase' => false,
                'googleLogin' => false,
                'appleLogin' => false,
            ],
        ]);

        Setting::query()->create([
            'variable' => 'notification',
            'value' => [
                'vapIdKey' => '',
                'firebaseProjectId' => '',
            ],
        ]);

        Setting::query()->create([
            'variable' => 'app',
            'value' => [
                'customerPlaystoreLink' => '',
                'customerAppstoreLink' => '',
            ],
        ]);

        DeliveryZone::query()->create([
            'name' => 'Dhaka Test Zone',
            'slug' => 'dhaka-test-zone',
            'center_latitude' => 23.8103,
            'center_longitude' => 90.4125,
            'radius_km' => 25,
            'delivery_time_per_km' => 3,
            'regular_delivery_charges' => 60,
            'free_delivery_amount' => 1000,
            'distance_based_delivery_charges' => 10,
            'per_store_drop_off_fee' => 0,
            'handling_charges' => 0,
            'buffer_time' => 10,
            'rush_delivery_enabled' => false,
            'delivery_boy_base_fee' => 0,
            'delivery_boy_per_store_pickup_fee' => 0,
            'delivery_boy_distance_based_fee' => 0,
            'delivery_boy_per_order_incentive' => 0,
            'status' => 'active',
        ]);
    }

    public function test_settings_index_and_firebase_config_are_available(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.variable', 'system');

        $this->getJson('/api/settings/firebase-config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'apiKey',
                    'authDomain',
                    'projectId',
                    'storageBucket',
                    'messagingSenderId',
                    'appId',
                    'vapidKey',
                ],
            ]);
    }

    public function test_version_check_supports_no_update_and_force_update(): void
    {
        config([
            'fastsheba.apps.customer.latest_version' => '2.1.0',
            'fastsheba.apps.customer.min_supported_version' => '2.0.0',
        ]);

        $this->getJson(
            '/api/settings/check-version'
            .'?current_version=2.1.0'
            .'&platform=android'
            .'&app=customer'
        )
            ->assertOk()
            ->assertJsonPath('data.update_available', false);

        $this->getJson(
            '/api/settings/check-version'
            .'?current_version=1.9.0'
            .'&platform=android'
            .'&app=customer'
        )
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.update_type', 'force_update');
    }

    public function test_delivery_zone_list_and_location_check_work(): void
    {
        $this->getJson('/api/delivery-zone')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'dhaka-test-zone');

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', 1);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', false)
            ->assertJsonPath('data.zone_id', null);
    }

    public function test_authenticated_zone_check_saves_selected_zone(): void
    {
        $user = User::query()->create([
            'name' => 'Zone Customer',
            'email' => 'zone@example.test',
            'mobile' => '01717777777',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true);

        $this->assertDatabaseHas('user_zone', [
            'user_id' => $user->id,
            'zone_id' => 1,
        ]);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=100'
            .'&longitude=200'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }
}
'@

Write-Host ""
Write-Host "Phase 2A parity hardening and API tests written." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan test --testsuite=Feature"
