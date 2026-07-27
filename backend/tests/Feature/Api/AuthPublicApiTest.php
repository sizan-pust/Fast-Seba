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