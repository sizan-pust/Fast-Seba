<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Category;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\DeliveryReturnSeeder;
use Database\Seeders\FinalOperationsSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\OrderPaymentSeeder;
use Database\Seeders\SellerManagementFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerManagementFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected User $admin;
    protected User $customer;
    protected User $riderUser;
    protected DeliveryBoy $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);
        $this->seed(OrderPaymentSeeder::class);
        $this->seed(DeliveryReturnSeeder::class);
        $this->seed(SellerManagementFinanceSeeder::class);
        $this->seed(FinalOperationsSeeder::class);

        $this->sellerUser = User::query()
            ->where('email', 'seller@fastsheba.test')
            ->firstOrFail();

        $this->seller = Seller::query()
            ->where('user_id', $this->sellerUser->id)
            ->firstOrFail();

        $this->store = Store::query()
            ->where('seller_id', $this->seller->id)
            ->firstOrFail();

        $this->riderUser = User::query()
            ->where('email', 'rider@fastsheba.test')
            ->firstOrFail();

        $this->rider = DeliveryBoy::query()
            ->where('user_id', $this->riderUser->id)
            ->firstOrFail();

        $this->admin = User::query()->create([
            'name' => 'Phase Seven Admin',
            'email' => 'phase7-admin@example.test',
            'mobile' => '01719990001',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);

        $this->customer = User::query()->create([
            'name' => 'Finance Customer',
            'email' => 'finance-customer@example.test',
            'mobile' => '01719990002',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);

        $this->customer->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);
    }

    public function test_seller_dashboard_and_existing_store_are_available(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $this->getJson('/api/seller/dashboard')
            ->assertOk()
            ->assertJsonPath('data.seller_id', $this->seller->id);

        $this->getJson('/api/seller/stores')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_seller_can_create_and_update_owned_store(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $zoneId = \App\Models\DeliveryZone::query()->value('id');

        $created = $this->postJson('/api/seller/stores', [
            'name' => 'Phase Seven Pharmacy',
            'address' => 'Dhanmondi, Dhaka',
            'city' => 'Dhaka',
            'latitude' => 23.7465,
            'longitude' => 90.3760,
            'contact_number' => '01719990003',
            'zone_ids' => [$zoneId],
            'allows_pickup' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        $storeId = $created->json('data.id');

        $this->postJson('/api/seller/stores/'.$storeId, [
            'description' => 'Updated store description',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated store description');

        $this->postJson('/api/seller/stores/'.$storeId.'/status', [
            'status' => 'offline',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'offline');
    }

    public function test_seller_can_create_product_variant_and_inventory(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $category = Category::query()->firstOrFail();

        $response = $this->postJson('/api/seller/products', [
            'title' => 'Cetirizine 10 mg Tablet',
            'category_id' => $category->id,
            'type' => 'simple',
            'short_description' => 'Antihistamine medicine',
            'is_returnable' => false,
            'variants' => [
                [
                    'title' => '10 Tablets',
                    'is_default' => true,
                    'stores' => [
                        [
                            'store_id' => $this->store->id,
                            'sku' => 'FS-CET-10-'.now()->format('His'),
                            'price' => 30,
                            'special_price' => 27,
                            'cost' => 20,
                            'stock' => 50,
                            'low_stock_threshold' => 5,
                        ],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cetirizine 10 mg Tablet')
            ->assertJsonPath('data.variants.0.stores.0.stock', 50);

        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'seller_id' => $this->seller->id,
        ]);

        $this->assertDatabaseHas('store_product_variants', [
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);
    }

    public function test_seller_cannot_attach_product_to_another_sellers_store(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other Seller',
            'email' => 'other-seller@example.test',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::SELLER->value,
            'logged_in_type' => 'platform',
        ]);

        $otherSeller = Seller::query()->create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Business',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
            'status' => 'active',
        ]);

        $otherStore = Store::query()->create([
            'seller_id' => $otherSeller->id,
            'name' => 'Other Store',
            'status' => 'online',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
        ]);

        Sanctum::actingAs($this->sellerUser);

        $this->postJson('/api/seller/products', [
            'title' => 'Ownership Test Medicine',
            'category_id' => Category::query()->value('id'),
            'variants' => [
                [
                    'title' => 'Default',
                    'stores' => [
                        [
                            'store_id' => $otherStore->id,
                            'sku' => 'FOREIGN-SKU',
                            'price' => 50,
                            'stock' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertNotFound();
    }

    public function test_seller_can_adjust_inventory_and_view_log(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $inventory = StoreProductVariant::query()
            ->where('store_id', $this->store->id)
            ->firstOrFail();

        $previous = $inventory->stock;

        $this->postJson('/api/seller/inventory/'.$inventory->id.'/adjust', [
            'change_type' => 'add',
            'quantity' => 7,
            'reason' => 'New stock received',
        ])
            ->assertOk()
            ->assertJsonPath('data.stock', $previous + 7);

        $this->getJson('/api/seller/inventory/'.$inventory->id.'/logs')
            ->assertOk()
            ->assertJsonPath('data.data.0.reason', 'New stock received');
    }

    public function test_admin_can_verify_seller_store_and_product(): void
    {
        $category = Category::query()->firstOrFail();
        $category->update(['requires_approval' => true]);

        Sanctum::actingAs($this->sellerUser);

        $product = $this->postJson('/api/seller/products', [
            'title' => 'Approval Required Medicine',
            'category_id' => $category->id,
            'variants' => [
                [
                    'title' => 'Default',
                    'stores' => [
                        [
                            'store_id' => $this->store->id,
                            'sku' => 'APPROVAL-SKU',
                            'price' => 40,
                            'stock' => 10,
                        ],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/sellers/'.$this->seller->id.'/verify', [
            'verification_status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'approved');

        $this->postJson('/api/admin/stores/'.$this->store->id.'/verify', [
            'verification_status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'approved');

        $this->postJson(
            '/api/admin/products/'.$product->json('data.id').'/verification-status',
            ['verification_status' => 'approved']
        )
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'approved');
    }

    public function test_finance_sync_creates_unsettled_seller_statement(): void
    {
        $sellerOrder = $this->deliveredSellerOrder(125, 12.5);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/finance/sync')
            ->assertOk()
            ->assertJsonPath('data.sellerCredits', 1);

        $this->assertDatabaseHas('seller_statements', [
            'seller_order_id' => $sellerOrder->id,
            'direction' => 'credit',
            'settlement_status' => 'unsettled',
        ]);
    }

    public function test_admin_settlement_credits_seller_wallet(): void
    {
        $sellerOrder = $this->deliveredSellerOrder(100, 10);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/finance/sync')->assertOk();

        $statement = SellerStatement::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->firstOrFail();

        $before = (float) Wallet::query()
            ->where('user_id', $this->sellerUser->id)
            ->where('type', 'seller')
            ->value('balance');

        $this->postJson('/api/admin/finance/statements/'.$statement->id.'/settle')
            ->assertOk()
            ->assertJsonPath('data.settlement_status', 'settled');

        $after = (float) Wallet::query()
            ->where('user_id', $this->sellerUser->id)
            ->where('type', 'seller')
            ->value('balance');

        $this->assertSame(90.0, round($after - $before, 2));
    }

    public function test_seller_withdrawal_blocks_and_admin_approval_deducts_wallet(): void
    {
        $wallet = Wallet::query()
            ->where('user_id', $this->sellerUser->id)
            ->where('type', 'seller')
            ->firstOrFail();

        $wallet->update(['balance' => 500]);

        Sanctum::actingAs($this->sellerUser);

        $withdrawal = $this->postJson('/api/seller/withdrawals', [
            'amount' => 200,
            'note' => 'Bank payout',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'blocked_balance' => 200,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/finance/seller-withdrawals/'
                .$withdrawal->json('data.id')
                .'/process',
            [
                'status' => 'approved',
                'external_transaction_id' => 'BANK-TXN-001',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $wallet->refresh();

        $this->assertSame(300.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->blocked_balance);
    }

    public function test_rejected_seller_withdrawal_releases_blocked_balance(): void
    {
        $wallet = Wallet::query()
            ->where('user_id', $this->sellerUser->id)
            ->where('type', 'seller')
            ->firstOrFail();

        $wallet->update(['balance' => 300]);

        Sanctum::actingAs($this->sellerUser);

        $withdrawalId = $this->postJson('/api/seller/withdrawals', [
            'amount' => 100,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/finance/seller-withdrawals/'.$withdrawalId.'/process',
            [
                'status' => 'rejected',
                'remark' => 'Bank account needs verification.',
            ]
        )->assertOk()->assertJsonPath('data.status', 'rejected');

        $wallet->refresh();

        $this->assertSame(300.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->blocked_balance);
    }

    public function test_rider_earning_sync_and_withdrawal_flow_work(): void
    {
        $order = $this->baseOrder('FS-RIDER-ORDER');

        DeliveryBoyAssignment::query()->create([
            'order_id' => $order->id,
            'delivery_boy_id' => $this->rider->id,
            'status' => 'delivered',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
            'delivered_at' => now(),
            'total_earnings' => 75,
            'payment_status' => 'completed',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/finance/sync')
            ->assertOk()
            ->assertJsonPath('data.riderCredits', 1);

        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/delivery-boy/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance', '75.00');

        $withdrawal = $this->postJson('/api/delivery-boy/withdrawals', [
            'amount' => 25,
        ])->assertCreated();

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/finance/rider-withdrawals/'
                .$withdrawal->json('data.id')
                .'/process',
            ['status' => 'approved']
        )->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_payment_gateway_secrets_are_not_public(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/admin/payment-gateways/sslcommerz', [
            'enabled' => true,
            'test_mode' => true,
            'public_config' => [
                'checkout_mode' => 'hosted',
            ],
            'secret_config' => [
                'store_id' => 'demo-store',
                'store_password' => 'super-secret',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.has_secret_config', true);

        $this->getJson('/api/admin/payment-gateways')
            ->assertOk()
            ->assertJsonMissing(['store_password' => 'super-secret']);

        $this->getJson('/api/payment/gateways')
            ->assertOk()
            ->assertJsonMissing(['store_password' => 'super-secret'])
            ->assertJsonFragment(['code' => 'sslcommerz']);
    }


    public function test_public_seller_registration_creates_pending_business_and_wallet(): void
    {
        $response = $this->postJson('/api/seller/register', [
            'name' => 'New Pharmacy Owner',
            'email' => 'new-pharmacy@example.test',
            'mobile' => '01719990009',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'business_name' => 'New Pharmacy Business',
            'store_name' => 'New Pharmacy Store',
            'address' => 'Dhaka',
            'city' => 'Dhaka',
        ])
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        $userId = $response->json('data.user_id');

        $this->assertDatabaseHas('sellers', [
            'user_id' => $userId,
            'business_name' => 'New Pharmacy Business',
            'verification_status' => 'pending',
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $userId,
            'type' => 'seller',
        ]);
    }

    public function test_seller_can_manage_own_attributes_and_values(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $response = $this->postJson('/api/seller/attributes', [
            'title' => 'Tablet Pack',
            'label' => 'Pack',
            'swatche_type' => 'text',
            'values' => [
                ['title' => '10 Tablets'],
                ['title' => '20 Tablets'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Tablet Pack');

        $attributeId = $response->json('data.id');

        $this->postJson('/api/seller/attributes/'.$attributeId, [
            'label' => 'Pack Size',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Pack Size');

        $this->postJson('/api/seller/attributes/'.$attributeId.'/values', [
            'title' => '30 Tablets',
        ])->assertCreated();

        $this->getJson('/api/seller/attributes')
            ->assertOk()
            ->assertJsonFragment(['title' => '30 Tablets']);
    }

    private function deliveredSellerOrder(
        float $subtotal,
        float $commission
    ): SellerOrder {
        $order = $this->baseOrder(
            'FS-SELLER-'.now()->format('His'),
            $subtotal
        );

        return SellerOrder::query()->create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'status' => 'delivered',
            'delivery_type' => 'delivery',
            'subtotal' => $subtotal,
            'commission_amount' => $commission,
            'seller_earnings' => $subtotal - $commission,
            'accepted_at' => now()->subHour(),
            'ready_for_pickup_at' => now()->subMinutes(30),
        ]);
    }

    private function baseOrder(
        string $slug,
        float $total = 100
    ): Order {
        return Order::query()->create([
            'slug' => $slug,
            'user_id' => $this->customer->id,
            'delivery_zone_id' => \App\Models\DeliveryZone::query()->value('id'),
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'completed',
            'delivery_type' => 'delivery',
            'subtotal' => $total,
            'total_payable' => $total,
            'final_total' => $total,
            'billing_name' => $this->customer->name,
            'shipping_name' => $this->customer->name,
            'delivered_at' => now(),
            'paid_at' => now(),
        ]);
    }
}
