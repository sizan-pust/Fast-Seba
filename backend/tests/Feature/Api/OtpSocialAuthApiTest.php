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

    public function test_unknown_mobile_can_verify_then_register_with_same_otp(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000006',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000006',
            'otp' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.new_user', true)
            ->assertJsonPath('data.is_register', true);

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000006',
            'otp' => '123456',
            'name' => 'Two Step OTP Customer',
            'email' => 'two-step-otp@example.test',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_register', true)
            ->assertJsonPath('data.mobile', '01710000006');

        $this->assertDatabaseHas('users', [
            'email' => 'two-step-otp@example.test',
            'mobile' => '01710000006',
        ]);
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