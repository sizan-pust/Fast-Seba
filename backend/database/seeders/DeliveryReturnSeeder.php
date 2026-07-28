<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Enums\WalletTypeEnum;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DeliveryReturnSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::findOrCreate(
            'delivery_boy',
            GuardNameEnum::WEB->value
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'rider@fastsheba.test'],
            [
                'name' => 'FastSheba Demo Rider',
                'mobile' => '01700000002',
                'password' => 'Test@123456',
                'status' => 'active',
                'access_panel' =>
                    GuardNameEnum::WEB->value,
                'logged_in_type' => 'platform',
                'country' => 'Bangladesh',
                'iso_2' => 'BD',
                'country_code' => '+880',
                'email_verified_at' => now(),
                'mobile_verified_at' => now(),
            ]
        );

        $user->syncRoles([$role]);

        $zone = DeliveryZone::query()
            ->where(
                'slug',
                'dhaka-test-zone'
            )
            ->first();

        DeliveryBoy::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'delivery_zone_id' => $zone?->id,
                'status' => 'available',
                'verification_status' => 'approved',
                'is_blocked' => false,
                'vehicle_type' => 'motorcycle',
                'vehicle_number' =>
                    'DHAKA-METRO-HA-00-0000',
                'license_number' => 'DEMO-LICENSE',
                'metadata' => [],
            ]
        );

        Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' =>
                    WalletTypeEnum::DELIVERY_BOY->value,
            ],
            [
                'balance' => 0,
                'blocked_balance' => 0,
                'currency_code' => 'BDT',
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'delivery'],
            [
                'value' => [
                    'riderSelfAssignment' => true,
                    'liveTracking' => true,
                    'returnPickupEnabled' => true,
                    'refundMethod' => 'wallet',
                ],
            ]
        );

        foreach ([
            'delivery.view',
            'delivery.assign',
            'returns.view',
            'returns.manage',
            'refunds.manage',
        ] as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::ADMIN->value
            );
        }

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where(
                'guard_name',
                GuardNameEnum::ADMIN->value
            )
            ->first();

        $superAdmin?->givePermissionTo([
            'delivery.view',
            'delivery.assign',
            'returns.view',
            'returns.manage',
            'refunds.manage',
        ]);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
