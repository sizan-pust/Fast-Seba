<?php

namespace Database\Seeders;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Models\Country;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::SUPER_ADMIN->value,
            'guard_name' => GuardNameEnum::ADMIN->value,
        ]);

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::SELLER->value,
            'guard_name' => GuardNameEnum::SELLER->value,
        ]);

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::CUSTOMER->value,
            'guard_name' => GuardNameEnum::WEB->value,
        ]);

        Country::query()->updateOrCreate(
            ['iso2' => 'BD'],
            [
                'name' => 'Bangladesh',
                'iso3' => 'BGD',
                'numeric_code' => '050',
                'phonecode' => '+880',
                'capital' => 'Dhaka',
                'currency' => 'BDT',
                'currency_name' => 'Bangladeshi Taka',
                'currency_symbol' => "\u{09F3}",
                'tld' => '.bd',
                'native' => "\u{09AC}\u{09BE}\u{0982}\u{09B2}\u{09BE}\u{09A6}\u{09C7}\u{09B6}",
                'region' => 'Asia',
                'subregion' => 'Southern Asia',
                'timezones' => [
                    [
                        'zoneName' => 'Asia/Dhaka',
                        'gmtOffset' => 21600,
                        'gmtOffsetName' => 'UTC+06:00',
                        'abbreviation' => 'BST',
                        'tzName' => 'Bangladesh Standard Time',
                    ],
                ],
                'translations' => [
                    'bn' => "\u{09AC}\u{09BE}\u{0982}\u{09B2}\u{09BE}\u{09A6}\u{09C7}\u{09B6}",
                    'en' => 'Bangladesh',
                ],
                'latitude' => 23.6850,
                'longitude' => 90.3563,
                'emoji' => 'ðŸ‡§ðŸ‡©',
                'emojiU' => 'U+1F1E7 U+1F1E9',
                'flag' => true,
                'wikiDataId' => 'Q902',
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'system'],
            [
                'value' => [
                    'appName' => 'FastSheba',
                    'systemVendorType' => 'multiple',
                    'country' => 'Bangladesh',
                    'currencyCode' => 'BDT',
                    'currencySymbol' => "\u{09F3}",
                    'timezone' => 'Asia/Dhaka',
                    'defaultLanguage' => 'en',
                    'demoMode' => false,
                    'welcomeWalletBalanceAmount' => 0,
                ],
            ]
        );

        $authenticationSetting = Setting::query()->firstOrCreate(
            ['variable' => 'authentication'],
            ['value' => []]
        );
        $authenticationSetting->value = array_merge([
            'customSms' => false,
            'firebase' => false,
            'googleLogin' => false,
            'appleLogin' => false,
            'smsGateway' => '',
            'fireBaseApiKey' => '',
            'fireBaseAuthDomain' => '',
            'fireBaseDatabaseURL' => '',
            'fireBaseProjectId' => '',
            'fireBaseStorageBucket' => '',
            'fireBaseMessagingSenderId' => '',
            'fireBaseAppId' => '',
            'fireBaseMeasurementId' => '',
        ], is_array($authenticationSetting->value)
            ? $authenticationSetting->value
            : []);
        $authenticationSetting->save();

        $notificationSetting = Setting::query()->firstOrCreate(
            ['variable' => 'notification'],
            ['value' => []]
        );
        $notificationSetting->value = array_merge([
            'vapIdKey' => '',
            'firebaseProjectId' => '',
        ], is_array($notificationSetting->value)
            ? $notificationSetting->value
            : []);
        $notificationSetting->save();

        Setting::query()->updateOrCreate(
            ['variable' => 'app'],
            [
                'value' => [
                    'customerPlaystoreLink' => '',
                    'customerAppstoreLink' => '',
                    'sellerPlaystoreLink' => '',
                    'sellerAppstoreLink' => '',
                    'riderPlaystoreLink' => '',
                    'riderAppstoreLink' => '',
                ],
            ]
        );

        DeliveryZone::query()->updateOrCreate(
            ['slug' => 'dhaka-test-zone'],
            [
                'name' => 'Dhaka Test Zone',
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
            ]
        );

        $email = env('FOUNDATION_ADMIN_EMAIL');
        $password = env('FOUNDATION_ADMIN_PASSWORD');

        if ($email && $password) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env(
                        'FOUNDATION_ADMIN_NAME',
                        'FastSheba Super Admin'
                    ),
                    'password' => $password,
                    'status' => 'active',
                    'access_panel' => GuardNameEnum::ADMIN->value,
                    'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
                    'email_verified_at' => now(),
                    'country' => 'Bangladesh',
                    'iso_2' => 'BD',
                    'country_code' => '+880',
                ]
            );

            $user->syncRoles([
                DefaultSystemRolesEnum::SUPER_ADMIN->value,
            ]);

            Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => WalletTypeEnum::CUSTOMER->value,
                ],
                [
                    'balance' => 0,
                    'blocked_balance' => 0,
                    'currency_code' => 'BDT',
                ]
            );
        } elseif ($this->command) {
            $this->command->warn(
                'Super admin was not seeded. Set FOUNDATION_ADMIN_EMAIL and FOUNDATION_ADMIN_PASSWORD in .env.'
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}