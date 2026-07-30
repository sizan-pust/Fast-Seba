<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class CustomerWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $assetBase = rtrim((string) config('app.url'), '/').'/assets/brand';

        $this->mergeSetting('system', [
            'appName' => 'FastSheba',
            'logo' => $assetBase.'/fastsheba-logo.png',
            'favicon' => $assetBase.'/fastsheba-logo.png',
            'copyrightDetails' => '© '.now()->year.' FastSheba. All rights reserved.',
            'systemTimezone' => 'Asia/Dhaka',
            'timezone' => 'Asia/Dhaka',
            'sellerSupportNumber' => '+8801339814081',
            'sellerSupportEmail' => 'fastsheba.com.bd@gmail.com',
            'systemVendorType' => 'multiple',
            'checkoutType' => 'multi_store',
            'minimumCartAmount' => 0,
            'maximumItemsAllowedInCart' => 50,
            'lowStockLimit' => 5,
            'maximumDistanceToNearestStore' => '25',
            'enableWallet' => true,
            'welcomeWalletBalanceAmount' => 0,
            'currency' => 'BDT',
            'currencyCode' => 'BDT',
            'currencySymbol' => '৳',
            'enableThirdPartyStoreSync' => false,
            'Shopify' => false,
            'Woocommerce' => false,
            'etsy' => false,
            'sellerAppMaintenanceMode' => false,
            'sellerAppMaintenanceMessage' => '',
            'webMaintenanceMode' => false,
            'webMaintenanceMessage' => '',
            'demoMode' => false,
            'adminDemoModeMessage' => '',
            'sellerDemoModeMessage' => '',
            'customerDemoModeMessage' => '',
            'customerLocationDemoModeMessage' => '',
            'deliveryBoyDemoModeMessage' => '',
            'referEarnStatus' => true,
            'referEarnMethodUser' => 'fixed',
            'referEarnBonusUser' => '50',
            'referEarnMaximumBonusAmountUser' => '50',
            'referEarnMethodReferral' => 'fixed',
            'referEarnBonusReferral' => '25',
            'referEarnMaximumBonusAmountReferral' => '25',
            'referEarnMinimumOrderAmount' => '0',
            'referEarnNumberOfTimesBonus' => '1',
        ]);

        $this->mergeSetting('web', [
            'siteName' => 'FastSheba',
            'siteCopyright' => '© '.now()->year.' FastSheba. All rights reserved.',
            'supportNumber' => '+8801339814081',
            'supportEmail' => 'fastsheba.com.bd@gmail.com',
            'address' => 'Bangladesh',
            'shortDescription' => 'Food, grocery, pharmacy and marketplace services from nearby trusted sellers.',
            'siteHeaderLogo' => $assetBase.'/fastsheba-logo.png',
            'siteHeaderDarkLogo' => $assetBase.'/fastsheba-logo.png',
            'siteFooterLogo' => $assetBase.'/fastsheba-logo.png',
            'siteFavicon' => $assetBase.'/fastsheba-logo.png',
            'headerScript' => '',
            'footerScript' => '',
            'googleMapKey' => (string) env('GOOGLE_MAPS_API_KEY', ''),
            'mapIframe' => '',
            'appDownloadSection' => true,
            'appSectionTitle' => 'FastSheba in your pocket',
            'appSectionTagline' => 'সব সেবা, এক অ্যাপে',
            'appSectionPlaystoreLink' => '',
            'appSectionAppstoreLink' => '',
            'appSectionShortDescription' => 'Order from nearby stores, upload prescriptions and track deliveries.',
            'facebookLink' => 'https://www.facebook.com/fastsheba.com.bd',
            'instagramLink' => '',
            'xLink' => '',
            'youtubeLink' => '',
            'shippingFeatureSection' => 'true',
            'shippingFeatureSectionTitle' => 'Fast local delivery',
            'shippingFeatureSectionDescription' => 'Location-aware delivery from nearby verified stores.',
            'returnFeatureSection' => 'true',
            'returnFeatureSectionTitle' => 'Easy returns',
            'returnFeatureSectionDescription' => 'Transparent return and refund workflows.',
            'safetySecurityFeatureSection' => 'true',
            'safetySecurityFeatureSectionTitle' => 'Secure shopping',
            'safetySecurityFeatureSectionDescription' => 'Protected checkout and trusted sellers.',
            'supportFeatureSection' => 'true',
            'supportFeatureSectionTitle' => 'Customer support',
            'supportFeatureSectionDescription' => 'Support for orders, pharmacy and delivery issues.',
            'metaKeywords' => 'FastSheba, pharmacy delivery Bangladesh, grocery delivery, food delivery, marketplace, upload prescription',
            'metaDescription' => 'FastSheba is a Bangladesh multi-vendor marketplace for pharmacy, food, grocery and daily essentials.',
            'defaultLatitude' => '23.8103',
            'defaultLongitude' => '90.4125',
            'enableCountryValidation' => true,
            'allowedCountries' => ['BD'],
            'returnRefundPolicy' => '<h2>Return and refund policy</h2><p>Eligible items can be returned according to product and seller rules. Prescription medicines and restricted health products may have additional conditions.</p>',
            'shippingPolicy' => '<h2>Shipping policy</h2><p>Delivery availability, fees and estimated time are calculated from the selected delivery location and nearby active stores.</p>',
            'privacyPolicy' => '<h2>Privacy policy</h2><p>FastSheba uses customer information only to provide, secure and improve the marketplace, delivery and pharmacy services.</p>',
            'termsCondition' => '<h2>Terms and conditions</h2><p>By using FastSheba, customers agree to provide accurate account, delivery and prescription information and to follow applicable marketplace policies.</p>',
            'aboutUs' => '<h2>FastSheba</h2><p>FastSheba brings food, grocery, pharmacy and marketplace services together in one location-aware platform for Bangladesh.</p>',
        ]);

        $this->mergeSetting('home_general_settings', [
            'title' => 'Everything nearby, delivered',
            'searchLabels' => ['medicine', 'grocery', 'food', 'daily essentials'],
            'backgroundType' => 'color',
            'backgroundColor' => '#EAF8EE',
            'backgroundImage' => '',
            'icon' => '',
            'activeIcon' => '',
            'fontColor' => '#064E24',
        ]);

        $this->mergeSetting('payment', [
            'cod' => true,
            'wallet' => true,
            'directBankTransfer' => false,
            'offline' => false,
            'stripePayment' => false,
            'stripePaymentMode' => 'test',
            'stripePublishableKey' => '',
            'stripeCurrencyCode' => 'BDT',
            'razorpayPayment' => false,
            'razorpayPaymentMode' => 'test',
            'razorpayKeyId' => '',
            'paystackPayment' => false,
            'paystackPaymentMode' => 'test',
            'paystackPublicKey' => '',
            'flutterwavePayment' => false,
            'flutterwavePaymentMode' => 'test',
            'flutterwavePublicKey' => '',
            'flutterwaveCurrencyCode' => 'BDT',
            'bankAccountName' => '',
            'bankAccountNumber' => '',
            'bankName' => '',
            'bankCode' => '',
            'bankExtraNote' => '',
            'currencyCode' => 'BDT',
            'currencySymbol' => '৳',
        ]);

        $this->mergeSetting('advertisement', [
            'featureEnabled' => false,
            'disableBehavior' => 'hide',
            'cpcRate' => 0,
            'walletMinTopup' => 0,
            'searchSlotCount' => 0,
            'relatedSlotCount' => 0,
            'impressionMultiplierMin' => 1,
            'impressionMultiplierMax' => 1,
            'adImpressionVisibilityPct' => 50,
            'adImpressionVisibilityMs' => 1000,
            'broadcastDriver' => 'log',
            'pusherAppId' => '',
            'pusherKey' => '',
            'pusherSecret' => '',
            'pusherCluster' => '',
            'reverbAppId' => '',
            'reverbKey' => '',
            'reverbSecret' => '',
            'reverbHost' => '',
            'reverbPort' => null,
            'reverbScheme' => 'https',
        ]);

        $this->seedBanners();
    }

    private function mergeSetting(string $variable, array $defaults): void
    {
        $setting = Setting::query()->firstOrNew(['variable' => $variable]);
        $current = is_array($setting->value) ? $setting->value : [];

        // Existing administrator values always win over defaults.
        $setting->value = array_replace_recursive($defaults, $current);
        $setting->save();
    }

    private function seedBanners(): void
    {
        $definitions = [
            [
                'slug' => 'fastsheba-pharmacy-prescription',
                'title' => 'Medicine delivered to your doorstep',
                'position' => 'home_top',
                'display_order' => 10,
                'custom_url' => '/prescriptions',
                'file' => 'pharmacy-prescription.jpg',
                'metadata' => ['subtitle' => 'Upload a prescription or shop verified pharmacy products.'],
            ],
            [
                'slug' => 'fastsheba-grocery-essentials',
                'title' => 'Fresh groceries and daily essentials',
                'position' => 'home_top',
                'display_order' => 20,
                'custom_url' => '/categories?category=grocery',
                'file' => 'grocery-essentials.jpg',
                'metadata' => ['subtitle' => 'Shop from nearby sellers.'],
            ],
            [
                'slug' => 'fastsheba-marketplace',
                'title' => 'Everything you need in one marketplace',
                'position' => 'home_carousel',
                'display_order' => 30,
                'custom_url' => '/categories',
                'file' => 'marketplace.jpg',
                'metadata' => ['subtitle' => 'Browse categories, products and stores.'],
            ],
            [
                'slug' => 'fastsheba-fast-delivery',
                'title' => 'Fast local delivery',
                'position' => 'home_carousel',
                'display_order' => 40,
                'custom_url' => '/delivery-zones',
                'file' => 'fast-delivery.jpg',
                'metadata' => ['subtitle' => 'Choose your delivery location.'],
            ],
        ];

        $zoneId = \App\Models\DeliveryZone::query()
            ->where('status', 'active')
            ->value('id');

        foreach ($definitions as $definition) {
            $banner = Banner::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'type' => 'custom',
                    'scope_type' => 'global',
                    'title' => $definition['title'],
                    'custom_url' => $definition['custom_url'],
                    'position' => $definition['position'],
                    'visibility_status' => 'published',
                    'display_order' => $definition['display_order'],
                    'metadata' => $definition['metadata'],
                ]
            );

            if ($zoneId) {
                $banner->zones()->syncWithoutDetaching([$zoneId]);
            }

            $file = public_path('assets/brand/banners/'.$definition['file']);

            if (
                is_file($file)
                && ! $banner->getFirstMedia('banner_image')
            ) {
                $banner->addMedia($file)
                    ->preservingOriginal()
                    ->toMediaCollection('banner_image');
            }
        }
    }
}
