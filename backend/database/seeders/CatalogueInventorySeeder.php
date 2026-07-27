<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\Badge;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductCondition;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CatalogueInventorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedPermissions();

            $sellerRole = Role::findOrCreate(
                'seller',
                GuardNameEnum::SELLER->value
            );

            $sellerUser = User::query()->updateOrCreate(
                ['email' => 'seller@fastsheba.test'],
                [
                    'name' => 'FastSheba Demo Seller',
                    'mobile' => '01700000001',
                    'country_code' => '+880',
                    'country' => 'Bangladesh',
                    'iso_2' => 'BD',
                    'password' => env(
                        'CATALOGUE_SELLER_PASSWORD',
                        'Test@123456'
                    ),
                    'status' => 'active',
                    'access_panel' => GuardNameEnum::SELLER->value,
                    'logged_in_type' =>
                        UserLoginTypeEnum::PLATFORM->value,
                    'email_verified_at' => now(),
                    'mobile_verified_at' => now(),
                ]
            );

            $sellerUser->syncRoles([$sellerRole]);

            $seller = Seller::query()->updateOrCreate(
                ['user_id' => $sellerUser->id],
                [
                    'business_name' => 'FastSheba Demo Pharmacy',
                    'legal_name' => 'FastSheba Demo Pharmacy',
                    'address' => 'Dhaka, Bangladesh',
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'country_code' => '+880',
                    'commission_rate' => 10,
                    'verification_status' => 'approved',
                    'visibility_status' => 'visible',
                    'status' => 'active',
                    'verified_at' => now(),
                ]
            );

            $store = Store::query()->firstOrNew([
                'slug' => 'fastsheba-demo-pharmacy',
            ]);

            $store->fill([
                'seller_id' => $seller->id,
                'name' => 'FastSheba Demo Pharmacy',
                'slug' => 'fastsheba-demo-pharmacy',
                'address' => 'Dhaka, Bangladesh',
                'city' => 'Dhaka',
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'contact_email' => 'seller@fastsheba.test',
                'contact_number' => '01700000001',
                'description' => 'Demo pharmacy for local development.',
                'currency_code' => 'BDT',
                'status' => 'online',
                'verification_status' => 'approved',
                'visibility_status' => 'visible',
                'is_recommended' => true,
                'fulfillment_type' => 'hyperlocal',
                'allows_pickup' => true,
                'pickup_instructions' =>
                    'Bring the order confirmation.',
            ]);

            $store->save();

            $zone = DeliveryZone::query()
                ->where('slug', 'dhaka-test-zone')
                ->first();

            if ($zone) {
                $store->zones()->syncWithoutDetaching([$zone->id]);
            }

            $root = Category::query()->updateOrCreate(
                ['slug' => 'pharmacy'],
                [
                    'title' => 'Pharmacy',
                    'description' => 'Medicine and healthcare products.',
                    'status' => 'active',
                    'requires_approval' => true,
                    'commission' => 10,
                    'sort_order' => 1,
                    'is_home_category' => true,
                    'background_type' => 'color',
                    'background_color' => '#E8F5E9',
                    'font_color' => '#1B5E20',
                    'search_labels' => [
                        'medicine',
                        'healthcare',
                        'pharmacy',
                    ],
                    'metadata' => [],
                ]
            );

            $category = Category::query()->updateOrCreate(
                ['slug' => 'otc-medicines'],
                [
                    'parent_id' => $root->id,
                    'title' => 'OTC Medicines',
                    'description' => 'Over-the-counter medicines.',
                    'status' => 'active',
                    'requires_approval' => true,
                    'commission' => 10,
                    'sort_order' => 1,
                    'is_home_category' => false,
                    'search_labels' => [
                        'tablet',
                        'pain relief',
                    ],
                    'metadata' => [],
                ]
            );

            $brand = Brand::query()->updateOrCreate(
                ['slug' => 'fastsheba-generic'],
                [
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'title' => 'FastSheba Generic',
                    'description' =>
                        'Generic demo brand for development.',
                    'status' => 'active',
                    'metadata' => [],
                ]
            );

            $condition = ProductCondition::query()->updateOrCreate(
                ['slug' => 'new'],
                [
                    'category_id' => $category->id,
                    'title' => 'New',
                    'alignment' => 'strip',
                ]
            );

            $badge = Badge::query()->updateOrCreate(
                ['slug' => 'popular'],
                [
                    'label' => 'Popular',
                    'bg_color' => '#198754',
                    'text_color' => '#ffffff',
                    'border_color' => '#198754',
                    'status' => 'active',
                ]
            );

            $attribute = GlobalProductAttribute::query()
                ->updateOrCreate(
                    [
                        'seller_id' => $seller->id,
                        'slug' => 'pack-size',
                    ],
                    [
                        'title' => 'Pack Size',
                        'label' => 'Pack Size',
                        'swatche_type' => 'text',
                    ]
                );

            $attributeValue = GlobalProductAttributeValue::query()
                ->updateOrCreate(
                    [
                        'global_attribute_id' => $attribute->id,
                        'title' => '10 Tablets',
                    ],
                    ['swatche_value' => '10 Tablets']
                );

            $product = Product::query()->firstOrNew([
                'slug' => 'paracetamol-500-mg-tablet',
            ]);

            $product->fill([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'product_condition_id' => $condition->id,
                'badge_id' => $badge->id,
                'slug' => 'paracetamol-500-mg-tablet',
                'title' => 'Paracetamol 500 mg Tablet',
                'type' => 'variant',
                'short_description' =>
                    'Common pain and fever relief medicine.',
                'description' =>
                    'Demo catalogue item for local development only.',
                'minimum_order_quantity' => 1,
                'quantity_step_size' => 1,
                'total_allowed_quantity' => 20,
                'is_inclusive_tax' => true,
                'is_returnable' => false,
                'is_cancelable' => true,
                'is_attachment_required' => false,
                'attachment_mode' => 'optional',
                'requires_otp' => false,
                'base_prep_time' => 5,
                'status' => 'active',
                'verification_status' => 'approved',
                'featured' => true,
                'tags' => [
                    'paracetamol',
                    'pain relief',
                    'fever',
                ],
                'custom_fields' => [
                    'generic_name' => 'Paracetamol',
                    'strength' => '500 mg',
                    'dosage_form' => 'Tablet',
                ],
                'made_in' => 'Bangladesh',
                'metadata' => [],
                'image_fit' => 'contain',
            ]);

            $product->save();
            $product->categories()->syncWithoutDetaching([
                $category->id,
            ]);

            $variant = ProductVariant::query()->firstOrNew([
                'slug' => 'paracetamol-500-mg-tablet-10-tablets',
            ]);

            $variant->fill([
                'product_id' => $product->id,
                'title' => '10 Tablets',
                'slug' =>
                    'paracetamol-500-mg-tablet-10-tablets',
                'weight' => 0.020,
                'availability' => true,
                'provider' => 'self',
                'barcode' => 'FS-PARA-500-10',
                'visibility' => 'published',
                'is_default' => true,
            ]);

            $variant->save();

            ProductVariantAttribute::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'global_attribute_id' => $attribute->id,
                ],
                [
                    'product_id' => $product->id,
                    'global_attribute_value_id' =>
                        $attributeValue->id,
                ]
            );

            $inventory = StoreProductVariant::query()
                ->updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'sku' => 'FS-PARA-500-10',
                        'price' => 20,
                        'special_price' => 18,
                        'cost' => 15,
                        'stock' => 100,
                        'low_stock_threshold' => 10,
                        'status' => 'active',
                    ]
                );

            StoreInventoryLog::query()->firstOrCreate(
                [
                    'store_product_variant_id' => $inventory->id,
                    'reason' => 'Initial catalogue seed',
                ],
                [
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'change_type' => 'add',
                    'quantity' => 100,
                    'previous_stock' => 0,
                    'new_stock' => 100,
                    'created_by' => $sellerUser->id,
                    'created_at' => now(),
                ]
            );
        });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'catalogue.view',
            'catalogue.manage',
            'inventory.view',
            'inventory.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::ADMIN->value
            );
        }

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', GuardNameEnum::ADMIN->value)
            ->first();

        $superAdmin?->givePermissionTo($permissions);
    }
}