<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\StoreProductVariant;
use App\Services\InventoryService;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CatalogueInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
    }

    public function test_catalogue_tables_and_seed_data_exist(): void
    {
        $this->assertDatabaseHas('categories', [
            'slug' => 'pharmacy',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('products', [
            'slug' => 'paracetamol-500-mg-tablet',
            'verification_status' => 'approved',
        ]);

        $this->assertDatabaseHas('store_product_variants', [
            'sku' => 'FS-PARA-500-10',
            'stock' => 100,
        ]);
    }

    public function test_home_categories_are_flutter_compatible(): void
    {
        $this->getJson(
            '/api/categories'
            .'?home=true'
            .'&latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'pharmacy')
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'data' => [[
                        'id',
                        'title',
                        'slug',
                        'image',
                        'search_labels',
                        'subcategory_count',
                        'product_count',
                    ]],
                ],
            ]);
    }

    public function test_brands_are_filtered_by_available_products(): void
    {
        $this->getJson(
            '/api/brands'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.data.0.slug',
                'fastsheba-generic'
            );
    }

    public function test_location_product_listing_and_detail_work(): void
    {
        $list = $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        );

        $list
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.slug',
                'paracetamol-500-mg-tablet'
            )
            ->assertJsonPath(
                'data.data.0.variants.0.stock',
                100
            )
            ->assertJsonPath(
                'data.data.0.variants.0.special_price',
                '18.00'
            );

        $this->getJson(
            '/api/products/paracetamol-500-mg-tablet'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.custom_fields.generic_name',
                'Paracetamol'
            )
            ->assertJsonPath(
                'data.attributes.0.slug',
                'pack-size'
            );
    }

    public function test_product_filters_and_store_location_work(): void
    {
        $this->getJson(
            '/api/products/sidebar-filters'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.attributes.0.slug',
                'pack-size'
            );


        // Browsing is allowed before a valid delivery location is selected.
        $this->getJson('/api/products/sidebar-filters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.attributes.0.slug',
                'pack-size'
            );

        $this->getJson(
            '/api/delivery-zone/stores'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.data.0.slug',
                'fastsheba-demo-pharmacy'
            );
    }

    public function test_outside_zone_returns_empty_products(): void
    {
        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_inventory_service_is_atomic_and_logs_changes(): void
    {
        $inventory = StoreProductVariant::query()->firstOrFail();
        $service = app(InventoryService::class);

        $inventory = $service->changeStock(
            $inventory,
            'remove',
            5,
            'Test order allocation'
        );

        $this->assertSame(95, $inventory->stock);

        $this->assertDatabaseHas('store_inventory_logs', [
            'store_product_variant_id' => $inventory->id,
            'change_type' => 'remove',
            'quantity' => -5,
            'previous_stock' => 100,
            'new_stock' => 95,
        ]);

        try {
            $service->changeStock(
                $inventory,
                'remove',
                500,
                'Should fail'
            );

            $this->fail('Expected insufficient stock exception.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('store_product_variants', [
                'id' => $inventory->id,
                'stock' => 95,
            ]);
        }
    }

    public function test_price_sorting_and_category_filter_work(): void
    {
        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&categories=pharmacy'
            .'&include_child_categories=1'
            .'&sort=price_asc'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.slug',
                'paracetamol-500-mg-tablet'
            );
    }
}