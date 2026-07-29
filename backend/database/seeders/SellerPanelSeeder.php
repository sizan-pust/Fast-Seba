<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Models\Seller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanLimit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SellerPanelSeeder extends Seeder
{
    public const PERMISSIONS = [
        'seller.dashboard.view',
        'seller.stores.manage',
        'seller.products.manage',
        'seller.attributes.manage',
        'seller.addons.manage',
        'seller.inventory.manage',
        'seller.orders.view',
        'seller.returns.manage',
        'seller.pos.manage',
        'seller.finance.view',
        'seller.withdrawals.manage',
        'seller.ads.manage',
        'seller.subscriptions.manage',
        'seller.team.manage',
        'seller.reviews.manage',
        'seller.feedback.manage',
        'seller.prescriptions.manage',
        'seller.faqs.manage',
        'seller.notifications.view',
        'seller.bulk_uploads.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ensureStarterPlan();

        $permissions = collect(self::PERMISSIONS)->map(
            fn (string $name) => Permission::findOrCreate(
                $name,
                GuardNameEnum::SELLER->value
            )
        );

        $sellerRole = Role::findOrCreate(
            'seller',
            GuardNameEnum::SELLER->value
        );
        $sellerRole->syncPermissions($permissions);

        Seller::query()->with(['owner', 'staff'])->each(
            function (Seller $seller) use ($sellerRole): void {
                if ($seller->owner) {
                    $seller->owner->forceFill([
                        'access_panel' => GuardNameEnum::SELLER->value,
                    ])->save();
                    $seller->owner->syncRoles([$sellerRole]);
                }

                foreach ($seller->staff as $member) {
                    $member->forceFill([
                        'access_panel' => GuardNameEnum::SELLER->value,
                    ])->save();
                }
            }
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureStarterPlan(): void
    {
        $plan = SubscriptionPlan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'title' => 'Starter',
                'description' => 'Starter seller subscription plan.',
                'price' => 0,
                'duration_days' => 3650,
                'trial_days' => 0,
                'is_featured' => false,
                'status' => 'active',
                'sort_order' => 10,
            ]
        );

        $limits = [
            'products' => 100,
            'stores' => 2,
            'ad_campaigns' => 2,
            'bulk_uploads' => 10,
            'pos_access' => 1,
        ];

        foreach ($limits as $featureKey => $limitValue) {
            SubscriptionPlanLimit::query()->updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'feature_key' => $featureKey,
                ],
                [
                    'limit_value' => $limitValue,
                    'is_unlimited' => false,
                ]
            );
        }
    }

}
