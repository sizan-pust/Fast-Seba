<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\FeaturedSection;
use App\Models\GiftCard;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SupportTicketType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GrowthSupportPharmacySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'engagement.view',
            'reviews.manage',
            'support.manage',
            'prescriptions.manage',
            'referrals.manage',
            'gift-cards.manage',
            'notifications.manage',
            'audit-logs.view',
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
            'engagement.view',
            'reviews.manage',
            'support.manage',
            'prescriptions.manage',
            'referrals.manage',
            'gift-cards.manage',
            'notifications.manage',
            'audit-logs.view',
        ]);

        foreach ([
            'engagement.view',
            'reviews.reply',
            'product-faqs.manage',
            'prescriptions.fulfill',
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
            'engagement.view',
            'reviews.reply',
            'product-faqs.manage',
            'prescriptions.fulfill',
        ]);

        $supportTypes = [
            ['title' => 'Order Issue', 'sort_order' => 10],
            ['title' => 'Payment Issue', 'sort_order' => 20],
            ['title' => 'Medicine or Prescription', 'sort_order' => 30],
            ['title' => 'Delivery Issue', 'sort_order' => 40],
            ['title' => 'Account and Other', 'sort_order' => 50],
        ];

        foreach ($supportTypes as $type) {
            SupportTicketType::query()->updateOrCreate(
                ['slug' => Str::slug($type['title'])],
                [
                    'title' => $type['title'],
                    'status' => 'active',
                    'sort_order' => $type['sort_order'],
                ]
            );
        }

        $faqs = [
            [
                'category' => 'orders',
                'question' => 'How can I track my FastSheba order?',
                'answer' => 'Open My Orders, select the order, and check its status timeline.',
                'sort_order' => 10,
            ],
            [
                'category' => 'prescriptions',
                'question' => 'How do I upload a prescription?',
                'answer' => 'Open Prescriptions, add patient details, and upload a clear image or PDF.',
                'sort_order' => 20,
            ],
            [
                'category' => 'wallet',
                'question' => 'Where are referral and gift-card rewards added?',
                'answer' => 'Approved rewards are credited to your FastSheba customer wallet.',
                'sort_order' => 30,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->updateOrCreate(
                ['question' => $faq['question']],
                array_merge($faq, ['status' => 'active'])
            );
        }

        $zoneId = \App\Models\DeliveryZone::query()->value('id');
        $productIds = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->limit(12)
            ->pluck('id')
            ->all();

        $banner = Banner::query()->updateOrCreate(
            ['slug' => 'fastsheba-pharmacy-welcome'],
            [
                'type' => 'custom',
                'scope_type' => 'global',
                'title' => 'FastSheba Pharmacy',
                'custom_url' => null,
                'position' => 'home_top',
                'visibility_status' => 'published',
                'display_order' => 10,
                'metadata' => [
                    'subtitle' => 'Medicines and pharmacy essentials near you',
                ],
            ]
        );

        if ($zoneId) {
            $banner->zones()->syncWithoutDetaching([$zoneId]);
        }

        $section = FeaturedSection::query()->updateOrCreate(
            ['slug' => 'popular-near-you'],
            [
                'scope_type' => 'global',
                'title' => 'Popular near you',
                'short_description' => 'Available products from nearby verified stores',
                'style' => 'horizontal',
                'section_type' => 'manual',
                'background_type' => 'color',
                'background_color' => '#F6F8FA',
                'text_color' => '#111111',
                'sort_order' => 10,
                'product_limit' => 12,
                'status' => 'active',
            ]
        );

        $sync = [];

        foreach ($productIds as $index => $productId) {
            $sync[$productId] = ['sort_order' => $index];
        }

        $section->products()->sync($sync);

        if ($zoneId) {
            $section->zones()->syncWithoutDetaching([$zoneId]);
        }

        GiftCard::query()->updateOrCreate(
            ['code' => 'FASTSHEBA100'],
            [
                'title' => 'FastSheba Demo Gift Card',
                'amount' => 100,
                'currency_code' => 'BDT',
                'max_redemptions' => 1000,
                'status' => 'active',
                'metadata' => ['demo' => true],
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'referral'],
            [
                'value' => [
                    'enabled' => true,
                    'referrer_bonus' => 50,
                    'referee_bonus' => 25,
                    'reward_after_first_delivered_order' => true,
                ],
            ]
        );

        User::query()
            ->whereNull('referral_code')
            ->orderBy('id')
            ->each(function (User $user): void {
                do {
                    $code = 'FS'.Str::upper(Str::random(8));
                } while (
                    User::query()
                        ->where('referral_code', $code)
                        ->exists()
                );

                $user->forceFill(['referral_code' => $code])->save();
            });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
