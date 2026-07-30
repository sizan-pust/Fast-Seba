<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Brand;
use App\Models\Category;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FastShebaMedicineCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/fastsheba_medicines_100.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                'Medicine catalogue JSON was not found: '.$path
            );
        }

        $payload = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $sellerEmail = (string) env(
            'MEDICINE_SELLER_EMAIL',
            'seller@fastsheba.test'
        );

        $seller = Seller::query()
            ->whereHas(
                'owner',
                fn ($query) => $query->where('email', $sellerEmail)
            )
            ->first();

        if (! $seller) {
            throw new RuntimeException(
                "Seller was not found for email: {$sellerEmail}. "
                .'Create/login the seller first or set '
                .'MEDICINE_SELLER_EMAIL in .env.'
            );
        }

        $preferredStoreSlug = (string) env(
            'MEDICINE_STORE_SLUG',
            'fastsheba-demo-pharmacy'
        );

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->where('slug', $preferredStoreSlug)
            ->first()
            ?? Store::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'online')
                ->first()
            ?? Store::query()
                ->where('seller_id', $seller->id)
                ->first();

        if (! $store) {
            throw new RuntimeException(
                "No store was found for seller {$sellerEmail}. "
                .'Create a store first or set MEDICINE_STORE_SLUG in .env.'
            );
        }

        DB::transaction(function () use (
            $payload,
            $seller,
            $store
        ): void {
            $root = Category::query()->updateOrCreate(
                ['slug' => 'pharmacy'],
                [
                    'title' => 'Pharmacy',
                    'description' =>
                        'Medicines and healthcare products.',
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
                    'metadata' => [
                        'catalogue' => 'fastsheba',
                    ],
                ]
            );

            $categories = [];

            foreach ($payload['categories'] as $index => $row) {
                $categories[$row['slug']] =
                    Category::query()->updateOrCreate(
                        ['slug' => $row['slug']],
                        [
                            'parent_id' => $root->id,
                            'title' => $row['title'],
                            'description' =>
                                $row['title'].' medicine catalogue.',
                            'status' => 'active',
                            'requires_approval' => true,
                            'commission' => 10,
                            'sort_order' => $index + 1,
                            'is_home_category' => false,
                            'background_type' => 'color',
                            'background_color' => '#F1F8F4',
                            'font_color' => '#146C43',
                            'search_labels' => [
                                strtolower($row['title']),
                                'medicine',
                                'pharmacy',
                            ],
                            'metadata' => [
                                'catalogue' => 'fastsheba-100-medicines',
                            ],
                        ]
                    );
            }

            $brand = Brand::query()->updateOrCreate(
                ['slug' => 'verified-generic-catalogue'],
                [
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'title' => 'Verified Generic Catalogue',
                    'description' =>
                        'Generic catalogue scaffold. Select a verified '
                        .'manufacturer/brand before production.',
                    'status' => 'active',
                    'metadata' => [
                        'demo' => true,
                        'manufacturer_pending' => true,
                    ],
                ]
            );

            $condition = ProductCondition::query()->updateOrCreate(
                ['slug' => 'new'],
                [
                    'category_id' => $root->id,
                    'title' => 'New',
                    'alignment' => 'strip',
                ]
            );

            $popularBadge = Badge::query()->updateOrCreate(
                ['slug' => 'popular'],
                [
                    'label' => 'Popular',
                    'bg_color' => '#198754',
                    'text_color' => '#ffffff',
                    'border_color' => '#198754',
                    'status' => 'active',
                ]
            );

            $rxBadge = Badge::query()->updateOrCreate(
                ['slug' => 'prescription-required'],
                [
                    'label' => 'Prescription Required',
                    'bg_color' => '#B42318',
                    'text_color' => '#ffffff',
                    'border_color' => '#B42318',
                    'status' => 'active',
                ]
            );

            $otcBadge = Badge::query()->updateOrCreate(
                ['slug' => 'otc'],
                [
                    'label' => 'OTC',
                    'bg_color' => '#0F766E',
                    'text_color' => '#ffffff',
                    'border_color' => '#0F766E',
                    'status' => 'active',
                ]
            );

            $packAttribute = GlobalProductAttribute::query()
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

            foreach ($payload['products'] as $row) {
                $category = $categories[$row['category']] ?? null;

                if (! $category) {
                    throw new RuntimeException(
                        'Unknown category: '.$row['category']
                    );
                }

                $rx = (bool) $row['prescription_required'];
                $badge = $rx
                    ? $rxBadge
                    : ((int) $row['id'] <= 20
                        ? $popularBadge
                        : $otcBadge);

                $product = Product::withTrashed()
                    ->where('slug', $row['slug'])
                    ->first()
                    ?? new Product();

                if ($product->trashed()) {
                    $product->restore();
                }

                $product->fill([
                    'seller_id' => $seller->id,
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'product_condition_id' => $condition->id,
                    'badge_id' => $badge->id,
                    'slug' => $row['slug'],
                    'title' => $row['title'],
                    'type' => 'variant',
                    'short_description' =>
                        $row['generic_name'].' '
                        .$row['strength'].' '
                        .$row['dosage_form'].'.',
                    'description' => $rx
                        ? 'Prescription catalogue item. The order must be '
                            .'reviewed by the pharmacy team before dispensing.'
                        : 'General pharmacy catalogue item. Verify the final '
                            .'brand, pack, label and retail price before sale.',
                    'minimum_order_quantity' => 1,
                    'quantity_step_size' => 1,
                    'total_allowed_quantity' => $rx ? 5 : 20,
                    'is_inclusive_tax' => true,
                    'is_returnable' => false,
                    'returnable_days' => null,
                    'is_cancelable' => true,
                    'cancelable_till' => 'processing',
                    'is_attachment_required' => $rx,
                    'attachment_mode' => $rx
                        ? 'required'
                        : 'optional',
                    'requires_otp' => false,
                    'base_prep_time' => $rx ? 15 : 5,
                    'status' => 'active',
                    'verification_status' => 'approved',
                    'featured' => (int) $row['id'] <= 20,
                    'tags' => array_values(array_unique([
                        strtolower($row['generic_name']),
                        strtolower($row['dosage_form']),
                        str_replace('-', ' ', $row['category']),
                        $rx ? 'prescription' : 'otc',
                    ])),
                    'custom_fields' => [
                        'generic_name' => $row['generic_name'],
                        'strength' => $row['strength'],
                        'dosage_form' => $row['dosage_form'],
                        'pack_size' => $row['pack_size'],
                        'prescription_required' => $rx,
                        'manufacturer' => $row['manufacturer'],
                        'price_status' => $row['price_status'],
                    ],
                    'made_in' => 'Bangladesh',
                    'metadata' => [
                        'catalogue' => 'fastsheba-100-medicines',
                        'catalogue_item_id' => (int) $row['id'],
                        'demo_data' => true,
                        'image_file' => $row['image_file'],
                    ],
                    'image_fit' => 'contain',
                ]);

                $product->save();
                $product->categories()->sync([$category->id]);

                $packValue =
                    GlobalProductAttributeValue::query()
                        ->updateOrCreate(
                            [
                                'global_attribute_id' =>
                                    $packAttribute->id,
                                'title' => $row['pack_size'],
                            ],
                            [
                                'swatche_value' => $row['pack_size'],
                            ]
                        );

                $variantSlug =
                    $row['slug'].'-'.str($row['pack_size'])->slug();

                $variant = ProductVariant::withTrashed()
                    ->where('slug', $variantSlug)
                    ->first()
                    ?? new ProductVariant();

                if ($variant->trashed()) {
                    $variant->restore();
                }

                $variant->fill([
                    'product_id' => $product->id,
                    'title' => $row['pack_size'],
                    'slug' => $variantSlug,
                    'weight' => (float) $row['weight_kg'],
                    'availability' => true,
                    'provider' => 'self',
                    'provider_product_id' => null,
                    'provider_json' => [
                        'catalogue_item_id' => (int) $row['id'],
                    ],
                    'barcode' => $row['barcode'],
                    'visibility' => 'published',
                    'is_default' => true,
                ]);

                $variant->save();

                ProductVariantAttribute::query()->updateOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'global_attribute_id' => $packAttribute->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'global_attribute_value_id' => $packValue->id,
                    ]
                );

                $inventory = StoreProductVariant::withTrashed()
                    ->where('store_id', $store->id)
                    ->where('product_variant_id', $variant->id)
                    ->first()
                    ?? new StoreProductVariant();

                if ($inventory->trashed()) {
                    $inventory->restore();
                }

                $inventory->fill([
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'sku' => $row['sku'],
                    'price' => (float) $row['price_bdt'],
                    'special_price' =>
                        (float) $row['special_price_bdt'],
                    'cost' => (float) $row['cost_bdt'],
                    'stock' => (int) $row['stock'],
                    'low_stock_threshold' =>
                        (int) $row['low_stock_threshold'],
                    'status' => 'active',
                ]);

                $inventory->save();

                StoreInventoryLog::query()->firstOrCreate(
                    [
                        'store_product_variant_id' => $inventory->id,
                        'reason' =>
                            'FastSheba 100 medicine catalogue seed',
                    ],
                    [
                        'store_id' => $store->id,
                        'product_variant_id' => $variant->id,
                        'change_type' => 'add',
                        'quantity' => (int) $row['stock'],
                        'previous_stock' => 0,
                        'new_stock' => (int) $row['stock'],
                        'created_by' => $seller->user_id,
                        'created_at' => now(),
                    ]
                );
            }
        });

        $this->command?->info(
            'FastSheba 100 medicine catalogue seeded successfully.'
        );

        $this->command?->warn(
            'Prices, manufacturer, registration and medicine images are '
            .'development placeholders until verified.'
        );
    }
}
