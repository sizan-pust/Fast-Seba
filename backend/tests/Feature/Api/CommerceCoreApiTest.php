<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Wishlist;
use App\Models\User;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceCoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected ProductVariant $variant;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);

        $this->user = User::query()->create([
            'name' => 'Commerce Customer',
            'email' => 'commerce@example.test',
            'mobile' => '01718888888',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ]);

        $this->user->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);

        \App\Models\Wallet::query()->create([
            'user_id' => $this->user->id,
            'type' => 'customer',
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->product = Product::query()->firstOrFail();
        $this->variant = ProductVariant::query()->firstOrFail();
        $this->store = Store::query()->firstOrFail();

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_list_update_and_delete_address(): void
    {
        $created = $this->postJson('/api/user/addresses', [
            'address_line1' => 'Dhanmondi',
            'address_line2' => 'Road 1',
            'city' => 'Dhaka',
            'landmark' => 'Lake',
            'state' => 'Dhaka',
            'zipcode' => '1205',
            'mobile' => '01710000000',
            'address_type' => 'home',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.city', 'Dhaka');

        $id = $created->json('data.id');

        $this->getJson('/api/user/addresses')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->putJson('/api/user/addresses/'.$id, [
            'address_type' => 'work',
        ])
            ->assertOk()
            ->assertJsonPath('data.address_type', 'work');

        $this->deleteJson('/api/user/addresses/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $id]);
    }

    public function test_outside_zone_address_is_rejected(): void
    {
        $this->postJson('/api/user/addresses', [
            'address_line1' => 'Outside',
            'city' => 'Sylhet',
            'mobile' => '01710000001',
            'address_type' => 'home',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'latitude' => 24.9000,
            'longitude' => 91.9000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_wishlist_crud_and_item_move_work(): void
    {
        $first = $this->postJson('/api/user/wishlists/create', [
            'title' => 'Medicine',
        ])->assertCreated();

        $second = $this->postJson('/api/user/wishlists/create', [
            'title' => 'Monthly',
        ])->assertCreated();

        $firstId = $first->json('data.id');
        $secondId = $second->json('data.id');

        $item = $this->postJson('/api/user/wishlists', [
            'wishlist_title' => 'Medicine',
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $itemId = $item->json('data.id');

        $this->putJson(
            '/api/user/wishlists/items/'.$itemId.'/move',
            ['target_wishlist_id' => $secondId]
        )
            ->assertOk()
            ->assertJsonPath('data.wishlist_id', $secondId);

        $this->deleteJson('/api/user/wishlists/'.$firstId)
            ->assertOk();

        $this->getJson('/api/user/wishlists')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_cart_add_update_save_and_remove_work(): void
    {
        $added = $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ])->assertCreated();

        $cartItemId = $added->json('data.cart_item_id');

        $this->getJson(
            '/api/user/cart?latitude=23.8103&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath(
                'data.payment_summary.items_total',
                '36.00'
            );

        $this->postJson('/api/user/cart/item/'.$cartItemId, [
            'quantity' => 3,
        ])->assertOk();

        $this->postJson(
            '/api/user/cart/item/save-for-later/'.$cartItemId
        )
            ->assertOk()
            ->assertJsonPath('data.save_for_later', true);

        $this->getJson('/api/user/cart/item/save-for-later')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/user/cart/item/'.$cartItemId)
            ->assertOk();

        $this->assertDatabaseMissing(
            'cart_items',
            ['id' => $cartItemId]
        );
    }

    public function test_cart_quantity_cannot_exceed_product_limit(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 21,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_cart_sync_handles_multiple_items(): void
    {
        $this->postJson('/api/user/cart/sync', [
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'store_id' => $this->store->id,
                    'quantity' => 1,
                ],
                [
                    'product_variant_id' => $this->variant->id,
                    'store_id' => $this->store->id,
                    'quantity' => 2,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.synced', 2);

        $this->assertDatabaseHas('cart_items', [
            'quantity' => 3,
        ]);
    }

    public function test_promo_is_available_and_applied_to_cart(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/user/promos/available')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'FAST10');

        $this->getJson(
            '/api/user/promos/validate'
            .'?promo_code=FAST10'
        )
            ->assertOk()
            ->assertJsonPath('data.discount', '1.80');

        $this->getJson(
            '/api/user/cart'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&promo_code=FAST10'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.payment_summary.promo_discount',
                '1.80'
            );
    }

    public function test_authenticated_product_payload_contains_cart_and_wishlist_state(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/user/wishlists', [
            'wishlist_title' => 'Favorite',
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
        ])->assertOk();

        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.data.0.item_count_in_cart',
                2
            )
            ->assertJsonPath(
                'data.data.0.variants.0.cart_item.exists',
                true
            );

        $this->assertNotEmpty(
            $this->getJson(
                '/api/delivery-zone/products'
                .'?latitude=23.8103'
                .'&longitude=90.4125'
            )->json('data.data.0.favorite')
        );
    }

    public function test_product_filter_response_returns_selected_ids(): void
    {
        $response = $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&categories=pharmacy'
            .'&brands=fastsheba-generic'
            .'&include_child_categories=1'
        )->assertOk();

        $this->assertNotEmpty(
            $response->json('data.category_ids')
        );
        $this->assertNotEmpty(
            $response->json('data.brand_ids')
        );
    }
}