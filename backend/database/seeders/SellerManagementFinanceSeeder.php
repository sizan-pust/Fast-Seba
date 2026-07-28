<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Models\DeliveryBoy;
use App\Models\PaymentGatewayConfig;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SellerManagementFinanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'seller.dashboard.view',
            'seller.stores.manage',
            'seller.products.manage',
            'seller.inventory.manage',
            'seller.finance.view',
            'seller.withdrawals.create',
        ] as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::SELLER->value
            );
        }

        $sellerRole = Role::findOrCreate(
            'seller',
            GuardNameEnum::SELLER->value
        );

        $sellerRole->givePermissionTo([
            'seller.dashboard.view',
            'seller.stores.manage',
            'seller.products.manage',
            'seller.inventory.manage',
            'seller.finance.view',
            'seller.withdrawals.create',
        ]);

        foreach ([
            'admin.dashboard.view',
            'admin.sellers.manage',
            'admin.stores.manage',
            'admin.products.manage',
            'admin.finance.manage',
            'admin.payment-gateways.manage',
        ] as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::ADMIN->value
            );
        }

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', GuardNameEnum::ADMIN->value)
            ->first();

        $superAdmin?->givePermissionTo([
            'admin.dashboard.view',
            'admin.sellers.manage',
            'admin.stores.manage',
            'admin.products.manage',
            'admin.finance.manage',
            'admin.payment-gateways.manage',
        ]);

        Seller::query()
            ->with('owner')
            ->get()
            ->each(function (Seller $seller): void {
                if (! $seller->owner) {
                    return;
                }

                Wallet::query()->firstOrCreate(
                    [
                        'user_id' => $seller->owner->id,
                        'type' => 'seller',
                    ],
                    [
                        'balance' => 0,
                        'blocked_balance' => 0,
                        'currency_code' => 'BDT',
                    ]
                );
            });

        DeliveryBoy::query()
            ->with('user')
            ->get()
            ->each(function (DeliveryBoy $rider): void {
                if (! $rider->user) {
                    return;
                }

                Wallet::query()->firstOrCreate(
                    [
                        'user_id' => $rider->user->id,
                        'type' => 'delivery_boy',
                    ],
                    [
                        'balance' => 0,
                        'blocked_balance' => 0,
                        'currency_code' => 'BDT',
                    ]
                );
            });

        $gateways = [
            [
                'code' => 'cod',
                'display_name' => 'Cash on Delivery',
                'enabled' => true,
                'test_mode' => false,
                'sort_order' => 10,
                'public_config' => [],
                'supported_currencies' => ['BDT'],
            ],
            [
                'code' => 'wallet',
                'display_name' => 'FastSheba Wallet',
                'enabled' => true,
                'test_mode' => false,
                'sort_order' => 20,
                'public_config' => [],
                'supported_currencies' => ['BDT'],
            ],
            [
                'code' => 'sslcommerz',
                'display_name' => 'SSLCommerz',
                'enabled' => false,
                'test_mode' => true,
                'sort_order' => 30,
                'public_config' => [
                    'checkout_mode' => 'hosted',
                ],
                'supported_currencies' => ['BDT'],
            ],
            [
                'code' => 'stripe',
                'display_name' => 'Stripe',
                'enabled' => false,
                'test_mode' => true,
                'sort_order' => 40,
                'public_config' => [],
                'supported_currencies' => ['USD', 'BDT'],
            ],
            [
                'code' => 'razorpay',
                'display_name' => 'Razorpay',
                'enabled' => false,
                'test_mode' => true,
                'sort_order' => 50,
                'public_config' => [],
                'supported_currencies' => ['INR'],
            ],
            [
                'code' => 'paystack',
                'display_name' => 'Paystack',
                'enabled' => false,
                'test_mode' => true,
                'sort_order' => 60,
                'public_config' => [],
                'supported_currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
            ],
            [
                'code' => 'flutterwave',
                'display_name' => 'Flutterwave',
                'enabled' => false,
                'test_mode' => true,
                'sort_order' => 70,
                'public_config' => [],
                'supported_currencies' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGatewayConfig::query()->updateOrCreate(
                ['code' => $gateway['code']],
                $gateway
            );
        }

        Setting::query()->updateOrCreate(
            ['variable' => 'seller_finance'],
            [
                'value' => [
                    'minimumWithdrawalAmount' => 100,
                    'withdrawalProcessingDays' => 3,
                    'automaticStatementSync' => false,
                    'defaultCurrency' => 'BDT',
                ],
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
