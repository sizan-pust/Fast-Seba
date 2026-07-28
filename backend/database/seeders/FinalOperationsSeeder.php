<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Models\AdCampaign;
use App\Models\AddonGroup;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SellerSubscriptionUsage;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanLimit;
use App\Models\SystemRelease;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\Wallet;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FinalOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedTax();
        $this->seedCollections();
        $this->seedAddons();
        $this->seedSubscriptions();
        $this->seedAdvertising();
        $this->seedOperationsSettings();

        Store::query()->update([
            'pos_enabled' => true,
            'pos_payment_config' => json_encode([
                'cash' => true,
                'card' => true,
                'wallet' => true,
                'bank' => true,
                'split_tender' => true,
            ], JSON_THROW_ON_ERROR),
        ]);

        SystemRelease::query()->updateOrCreate(
            ['version' => '1.0.0'],
            [
                'status' => 'ready',
                'checksum' => null,
                'release_notes' =>
                    'FastSheba backend MVP complete through Phase 9.',
                'metadata' => [
                    'modules' => [
                        'auth',
                        'catalogue',
                        'commerce',
                        'orders',
                        'delivery',
                        'returns',
                        'seller_management',
                        'finance',
                        'growth',
                        'support',
                        'pharmacy',
                        'pos',
                        'subscriptions',
                        'advertising',
                        'bulk_operations',
                        'payments',
                        'operations',
                    ],
                ],
            ]
        );

        app(SubscriptionService::class)->syncUsage();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $sellerPermissions = [
            'seller.addons.manage',
            'seller.subscriptions.manage',
            'seller.advertisements.manage',
            'seller.pos.use',
            'seller.pos.refund',
            'seller.bulk-uploads.manage',
            'seller.feedback.manage',
            'seller.team.manage',
        ];

        foreach ($sellerPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::SELLER->value
            );
        }

        $sellerRole = Role::findOrCreate(
            'seller',
            GuardNameEnum::SELLER->value
        );

        $sellerRole->givePermissionTo($sellerPermissions);

        $adminPermissions = [
            'admin.tax.manage',
            'admin.collections.manage',
            'admin.subscriptions.manage',
            'admin.advertisements.manage',
            'admin.pos.view',
            'admin.bulk-uploads.manage',
            'admin.delivery-cash.manage',
            'admin.feedback.manage',
            'admin.payment-operations.view',
            'admin.commands.run',
            'admin.releases.manage',
        ];

        foreach ($adminPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::ADMIN->value
            );
        }

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', GuardNameEnum::ADMIN->value)
            ->first();

        $superAdmin?->givePermissionTo($adminPermissions);
    }

    private function seedTax(): void
    {
        $zero = TaxRate::query()->updateOrCreate(
            ['name' => 'Zero Rated'],
            [
                'rate' => 0,
                'country_code' => 'BD',
                'priority' => 1,
                'compound' => false,
                'status' => 'active',
            ]
        );

        $standard = TaxRate::query()->updateOrCreate(
            ['name' => 'Standard VAT'],
            [
                'rate' => 5,
                'country_code' => 'BD',
                'priority' => 1,
                'compound' => false,
                'status' => 'active',
            ]
        );

        $medicineClass = TaxClass::query()->updateOrCreate(
            ['slug' => 'medicine-zero-rated'],
            [
                'name' => 'Medicine Zero Rated',
                'description' =>
                    'Default FastSheba medicine tax class.',
                'is_default' => true,
                'status' => 'active',
            ]
        );

        $standardClass = TaxClass::query()->updateOrCreate(
            ['slug' => 'standard-vat'],
            [
                'name' => 'Standard VAT',
                'description' =>
                    'Configurable standard VAT class.',
                'is_default' => false,
                'status' => 'active',
            ]
        );

        $medicineClass->rates()->sync([$zero->id]);
        $standardClass->rates()->sync([$standard->id]);
    }

    private function seedCollections(): void
    {
        $collection = ProductCollection::query()->updateOrCreate(
            ['slug' => 'pharmacy-essentials'],
            [
                'title' => 'Pharmacy Essentials',
                'description' =>
                    'Frequently needed pharmacy and wellness products.',
                'status' => 'active',
                'sort_order' => 10,
                'metadata' => [
                    'layout' => 'horizontal',
                    'show_on_home' => true,
                ],
            ]
        );

        $productIds = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->limit(12)
            ->pluck('id')
            ->all();

        $sync = [];

        foreach ($productIds as $index => $productId) {
            $sync[$productId] = ['sort_order' => $index + 1];
        }

        $collection->products()->sync($sync);
    }

    private function seedAddons(): void
    {
        $seller = Seller::query()->first();

        if (! $seller) {
            return;
        }

        $group = AddonGroup::query()->updateOrCreate(
            [
                'seller_id' => $seller->id,
                'slug' => 'pharmacy-extras',
            ],
            [
                'title' => 'Pharmacy Extras',
                'selection_type' => 'multiple',
                'minimum_selection' => 0,
                'maximum_selection' => 3,
                'is_required' => false,
                'status' => 'active',
                'sort_order' => 10,
            ]
        );

        $bag = $group->items()->updateOrCreate(
            ['slug' => 'paper-bag'],
            [
                'title' => 'Paper Bag',
                'default_price' => 5,
                'default_cost' => 2,
                'status' => 'active',
                'sort_order' => 10,
            ]
        );

        $coldPack = $group->items()->updateOrCreate(
            ['slug' => 'cold-pack'],
            [
                'title' => 'Cold Pack',
                'default_price' => 20,
                'default_cost' => 12,
                'status' => 'active',
                'sort_order' => 20,
            ]
        );

        $inventory = StoreProductVariant::query()
            ->whereHas(
                'store',
                fn ($query) =>
                    $query->where('seller_id', $seller->id)
            )
            ->first();

        if (! $inventory) {
            return;
        }

        foreach (
            [
                [$bag, 5, 100],
                [$coldPack, 20, 25],
            ] as [$item, $price, $stock]
        ) {
            DB::table(
                'store_product_variant_addons'
            )->updateOrInsert(
                [
                    'store_id' => $inventory->store_id,
                    'product_variant_id' =>
                        $inventory->product_variant_id,
                    'addon_item_id' => $item->id,
                ],
                [
                    'addon_group_id' => $group->id,
                    'is_default' => false,
                    'sort_order' => $item->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('store_addon_items')->updateOrInsert(
                [
                    'store_id' => $inventory->store_id,
                    'addon_item_id' => $item->id,
                ],
                [
                    'price' => $price,
                    'cost' => $item->default_cost,
                    'stock' => $stock,
                    'low_stock_threshold' => 5,
                    'is_available' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedSubscriptions(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'title' => 'Starter',
                'price' => 0,
                'duration_days' => 3650,
                'trial_days' => 0,
                'is_featured' => false,
                'sort_order' => 10,
                'limits' => [
                    'products' => 100,
                    'stores' => 2,
                    'ad_campaigns' => 2,
                    'bulk_uploads' => 10,
                    'pos_access' => 1,
                ],
            ],
            [
                'slug' => 'growth',
                'title' => 'Growth',
                'price' => 1499,
                'duration_days' => 30,
                'trial_days' => 7,
                'is_featured' => true,
                'sort_order' => 20,
                'limits' => [
                    'products' => 1000,
                    'stores' => 10,
                    'ad_campaigns' => 25,
                    'bulk_uploads' => 200,
                    'pos_access' => 1,
                ],
            ],
            [
                'slug' => 'enterprise',
                'title' => 'Enterprise',
                'price' => 4999,
                'duration_days' => 30,
                'trial_days' => 0,
                'is_featured' => false,
                'sort_order' => 30,
                'limits' => [
                    'products' => null,
                    'stores' => null,
                    'ad_campaigns' => null,
                    'bulk_uploads' => null,
                    'pos_access' => null,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $limits = $planData['limits'];
            unset($planData['limits']);

            $plan = SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                array_merge($planData, [
                    'description' =>
                        $planData['title']
                        .' seller subscription plan.',
                    'status' => 'active',
                ])
            );

            foreach ($limits as $key => $limit) {
                SubscriptionPlanLimit::query()->updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'feature_key' => $key,
                    ],
                    [
                        'limit_value' => $limit,
                        'is_unlimited' => $limit === null,
                    ]
                );
            }
        }

        $starter = SubscriptionPlan::query()
            ->where('slug', 'starter')
            ->with('limits')
            ->first();

        if (! $starter) {
            return;
        }

        Seller::query()
            ->each(function (Seller $seller) use ($starter): void {
                $subscription = SellerSubscription::query()
                    ->firstOrCreate(
                        [
                            'seller_id' => $seller->id,
                            'subscription_plan_id' => $starter->id,
                            'status' => 'active',
                        ],
                        [
                            'starts_at' => now(),
                            'ends_at' => now()->addDays(
                                $starter->duration_days
                            ),
                            'auto_renew' => false,
                            'payment_method' => 'free',
                            'snapshot' => [
                                'title' => $starter->title,
                                'price' => $starter->price,
                            ],
                        ]
                    );

                foreach ($starter->limits as $limit) {
                    SellerSubscriptionUsage::query()
                        ->firstOrCreate(
                            [
                                'seller_subscription_id' =>
                                    $subscription->id,
                                'feature_key' =>
                                    $limit->feature_key,
                            ],
                            [
                                'seller_id' => $seller->id,
                                'used_count' => 0,
                                'period_starts_at' =>
                                    $subscription->starts_at,
                                'period_ends_at' =>
                                    $subscription->ends_at,
                            ]
                        );
                }
            });
    }

    private function seedAdvertising(): void
    {
        $seller = Seller::query()
            ->with('owner')
            ->first();

        if (! $seller || ! $seller->owner) {
            return;
        }

        Wallet::query()->firstOrCreate(
            [
                'user_id' => $seller->owner->id,
                'type' => 'seller_ad',
            ],
            [
                'balance' => 0,
                'blocked_balance' => 0,
                'currency_code' => 'BDT',
            ]
        );

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->first();

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->where('verification_status', 'approved')
            ->first();

        AdCampaign::query()->updateOrCreate(
            [
                'seller_id' => $seller->id,
                'title' => 'FastSheba Pharmacy Launch',
            ],
            [
                'store_id' => $store?->id,
                'product_id' => $product?->id,
                'ad_type' => 'cpc',
                'placement' => 'home_feed',
                'status' => 'approved',
                'budget' => 1000,
                'spent_amount' => 0,
                'bid_amount' => 2,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
                'approved_at' => now(),
                'metadata' => [
                    'headline' =>
                        'Medicines and essentials near you',
                ],
            ]
        );

        PaymentGatewayConfig::query()
            ->whereIn('code', [
                'sslcommerz',
                'stripe',
                'razorpay',
                'paystack',
                'flutterwave',
            ])
            ->update([
                'metadata' => json_encode([
                    'phase9_payment_intents' => true,
                    'requires_external_credentials' => true,
                ], JSON_THROW_ON_ERROR),
            ]);
    }

    private function seedOperationsSettings(): void
    {
        $configuration = [
            'version' => '1.0.0',
            'currency' => 'BDT',
            'posEnabled' => true,
            'subscriptionsEnabled' => true,
            'advertisingEnabled' => true,
            'bulkUploadsEnabled' => true,
            'paymentIntentsEnabled' => true,
            'commandLoggingEnabled' => true,
            'readinessEndpointEnabled' => true,
            'openApiEndpointEnabled' => true,
        ];

        $values = [
            'value' => json_encode(
                $configuration,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('settings', 'is_public')) {
            $values['is_public'] = false;
        }

        if (Schema::hasColumn('settings', 'description')) {
            $values['description'] =
                'FastSheba final backend operations configuration.';
        }

        $exists = DB::table('settings')
            ->where('variable', 'final_backend_operations')
            ->exists();

        if ($exists) {
            DB::table('settings')
                ->where('variable', 'final_backend_operations')
                ->update($values);

            return;
        }

        DB::table('settings')->insert(array_merge(
            [
                'variable' => 'final_backend_operations',
                'created_at' => now(),
            ],
            $values
        ));
    }
}
