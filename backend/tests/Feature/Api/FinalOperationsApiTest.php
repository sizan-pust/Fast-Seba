<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\AdCampaign;
use App\Models\BulkUploadJob;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyCashTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BulkUploadService;
use App\Services\DeliveryCashFeedbackService;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\DeliveryReturnSeeder;
use Database\Seeders\FinalOperationsSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\GrowthSupportPharmacySeeder;
use Database\Seeders\OrderPaymentSeeder;
use Database\Seeders\SellerManagementFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinalOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $sellerUser;
    protected User $admin;
    protected User $riderUser;
    protected Seller $seller;
    protected Store $store;
    protected Product $product;
    protected ProductVariant $variant;
    protected StoreProductVariant $inventory;
    protected DeliveryBoy $rider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);
        $this->seed(OrderPaymentSeeder::class);
        $this->seed(DeliveryReturnSeeder::class);
        $this->seed(SellerManagementFinanceSeeder::class);
        $this->seed(GrowthSupportPharmacySeeder::class);
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

        $this->inventory = StoreProductVariant::query()
            ->where('store_id', $this->store->id)
            ->with('productVariant.product')
            ->firstOrFail();

        $this->variant = $this->inventory->productVariant;
        $this->product = $this->variant->product;

        $this->riderUser = User::query()
            ->where('email', 'rider@fastsheba.test')
            ->firstOrFail();

        $this->rider = DeliveryBoy::query()
            ->where('user_id', $this->riderUser->id)
            ->firstOrFail();

        $this->customer = User::query()->create([
            'name' => 'Final Operations Customer',
            'email' => 'phase9-customer@example.test',
            'mobile' => '01718880001',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
            'country_code' => '+880',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);

        $this->customer->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);

        Wallet::query()->create([
            'user_id' => $this->customer->id,
            'type' => 'customer',
            'balance' => 1000,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Final Operations Admin',
            'email' => 'phase9-admin@example.test',
            'mobile' => '01718880002',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);
    }

    public function test_public_tax_collections_addons_health_and_openapi_are_available(): void
    {
        $this->getJson('/api/tax-classes')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'medicine-zero-rated',
            ]);

        $this->getJson('/api/collections')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'pharmacy-essentials',
            ]);

        $this->getJson(
            '/api/stores/'
            .$this->store->id
            .'/variants/'
            .$this->variant->id
            .'/addons'
        )
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Pharmacy Extras',
            ]);

        $this->getJson('/api/system/live')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/docs/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0');
    }

    public function test_customer_can_follow_and_unfollow_verified_seller(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson(
            '/api/user/followed-sellers/'.$this->seller->id
        )
            ->assertCreated()
            ->assertJsonPath('data.following', true);

        $this->getJson('/api/user/followed-sellers')
            ->assertOk()
            ->assertJsonPath(
                'data.0.seller_id',
                $this->seller->id
            );

        $this->deleteJson(
            '/api/user/followed-sellers/'.$this->seller->id
        )
            ->assertOk()
            ->assertJsonPath('data.following', false);

        $this->assertDatabaseMissing('following_sellers', [
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
        ]);
    }

    public function test_customer_can_submit_seller_and_delivery_feedback_once(): void
    {
        $order = $this->deliveredOrderWithRider();

        Sanctum::actingAs($this->customer);

        $this->postJson('/api/user/feedback/sellers', [
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'rating' => 5,
            'comment' => 'Reliable pharmacy.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->postJson('/api/user/feedback/delivery', [
            'order_id' => $order->id,
            'delivery_boy_id' => $this->rider->id,
            'rating' => 4,
            'comment' => 'Delivered safely.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);

        $this->postJson('/api/user/feedback/sellers', [
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'rating' => 4,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id']);

        $this->getJson('/api/sellers/'.$this->seller->id.'/rating')
            ->assertOk()
            ->assertJsonPath('data.review_count', 1);

        $this->getJson('/api/delivery-boys/'.$this->rider->id.'/rating')
            ->assertOk()
            ->assertJsonPath('data.review_count', 1);
    }

    public function test_seller_can_manage_addon_group_and_variant_matrix(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $group = $this->postJson('/api/seller/addons', [
            'title' => 'Packaging Choice',
            'selection_type' => 'single',
            'minimum_selection' => 0,
            'maximum_selection' => 1,
            'items' => [
                [
                    'title' => 'Premium Box',
                    'default_price' => 15,
                    'default_cost' => 8,
                ],
            ],
        ])->assertCreated();

        $groupId = $group->json('data.id');
        $itemId = $group->json('data.items.0.id');

        $this->postJson('/api/seller/addons/matrix/attach', [
            'store_id' => $this->store->id,
            'product_variant_id' => $this->variant->id,
            'addon_group_id' => $groupId,
            'items' => [
                [
                    'addon_item_id' => $itemId,
                    'price' => 18,
                    'cost' => 8,
                    'stock' => 20,
                    'is_available' => true,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Premium Box',
                'price' => 18,
            ]);
    }

    public function test_starter_subscription_and_feature_eligibility_are_available(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $this->getJson('/api/seller/subscriptions/current')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'starter');

        $this->postJson('/api/seller/subscriptions/eligibility', [
            'feature_key' => 'pos_access',
            'additional' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.eligible', true);

        $this->getJson('/api/seller/subscriptions/plans')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'growth']);
    }

    public function test_ad_campaign_approval_reserves_wallet_and_event_is_deduplicated(): void
    {
        $wallet = Wallet::query()->updateOrCreate(
            [
                'user_id' => $this->sellerUser->id,
                'type' => 'seller_ad',
            ],
            [
                'balance' => 500,
                'blocked_balance' => 0,
                'currency_code' => 'BDT',
            ]
        );

        Sanctum::actingAs($this->sellerUser);

        $response = $this->postJson('/api/seller/advertisements', [
            'title' => 'Phase 9 CPC Campaign',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'ad_type' => 'cpc',
            'placement' => 'search',
            'budget' => 100,
            'bid_amount' => 2,
            'starts_at' => now()->subMinute()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_approval');

        $campaignId = $response->json('data.id');
        $campaignUuid = $response->json('data.uuid');

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/advertisements/'.$campaignId.'/approve'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $wallet->refresh();
        $this->assertSame(100.0, (float) $wallet->blocked_balance);

        $eventUuid = (string) Str::uuid();

        $this->postJson(
            '/api/advertisements/'.$campaignUuid.'/events',
            [
                'event_type' => 'click',
                'event_uuid' => $eventUuid,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.recorded', true)
            ->assertJsonPath('data.cost', 2);

        $this->postJson(
            '/api/advertisements/'.$campaignUuid.'/events',
            [
                'event_type' => 'click',
                'event_uuid' => $eventUuid,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $wallet->refresh();

        $this->assertSame(498.0, (float) $wallet->balance);
        $this->assertSame(98.0, (float) $wallet->blocked_balance);
    }

    public function test_seller_can_transfer_earnings_to_ad_wallet(): void
    {
        $sellerWallet = Wallet::query()
            ->where('user_id', $this->sellerUser->id)
            ->where('type', 'seller')
            ->firstOrFail();

        $sellerWallet->update([
            'balance' => 300,
            'blocked_balance' => 0,
        ]);

        Sanctum::actingAs($this->sellerUser);

        $this->postJson(
            '/api/seller/advertisements/wallet/topup-from-earnings',
            ['amount' => 120]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.ad_wallet.available_balance',
                120
            );

        $sellerWallet->refresh();
        $this->assertSame(180.0, (float) $sellerWallet->balance);
    }

    public function test_pos_order_is_atomic_and_idempotent(): void
    {
        $beforeStock = (int) $this->inventory->stock;
        $payload = $this->posPayload(1);
        $key = (string) Str::uuid();

        Sanctum::actingAs($this->sellerUser);

        $first = $this->withHeader(
            'Idempotency-Key',
            $key
        )->postJson('/api/seller/pos/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'completed');

        $orderId = $first->json('data.order_id');

        $second = $this->withHeader(
            'Idempotency-Key',
            $key
        )->postJson('/api/seller/pos/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.order_id', $orderId);

        $this->assertDatabaseCount('orders', 1);

        $this->inventory->refresh();
        $this->assertSame(
            $beforeStock - 1,
            (int) $this->inventory->stock
        );

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'source' => 'pos',
        ]);
    }

    public function test_pos_idempotency_key_rejects_different_payload(): void
    {
        $key = (string) Str::uuid();

        Sanctum::actingAs($this->sellerUser);

        $this->withHeader(
            'Idempotency-Key',
            $key
        )->postJson('/api/seller/pos/orders', $this->posPayload(1))
            ->assertCreated();

        $this->withHeader(
            'Idempotency-Key',
            $key
        )->postJson('/api/seller/pos/orders', $this->posPayload(2))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['Idempotency-Key']);
    }

    public function test_pos_parked_sale_crud_works(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $created = $this->postJson('/api/seller/pos/parked-sales', [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'reference' => 'PARK-001',
            'cart' => [
                [
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 1,
                ],
            ],
            'totals' => ['total' => 18],
        ])->assertCreated();

        $saleId = $created->json('data.id');

        $this->postJson(
            '/api/seller/pos/parked-sales/'.$saleId,
            ['note' => 'Customer will return soon.']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.note',
                'Customer will return soon.'
            );

        $this->getJson('/api/seller/pos/parked-sales')
            ->assertOk()
            ->assertJsonPath('data.0.id', $saleId);

        $this->deleteJson(
            '/api/seller/pos/parked-sales/'.$saleId
        )->assertOk();
    }

    public function test_pos_refund_restores_inventory(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $before = (int) $this->inventory->stock;

        $order = $this->withHeader(
            'Idempotency-Key',
            (string) Str::uuid()
        )->postJson(
            '/api/seller/pos/orders',
            $this->posPayload(1)
        )->assertCreated();

        $orderId = $order->json('data.order_id');
        $itemId = $order->json('data.items.0.id');

        $this->postJson(
            '/api/seller/pos/orders/'.$orderId.'/refunds',
            [
                'method' => 'cash',
                'reason' => 'Customer returned the item.',
                'items' => [
                    [
                        'order_item_id' => $itemId,
                        'quantity' => 1,
                    ],
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $this->inventory->refresh();
        $this->assertSame($before, (int) $this->inventory->stock);
    }

    public function test_seller_bulk_export_job_can_be_processed(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $response = $this->postJson(
            '/api/seller/bulk-uploads/export',
            ['type' => 'products']
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $uuid = $response->json('data.uuid');
        $job = BulkUploadJob::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        app(BulkUploadService::class)->process($job);

        $job->refresh();

        $this->assertSame('completed', $job->status);
        Storage::disk('local')->assertExists($job->stored_path);

        $this->getJson('/api/seller/bulk-uploads/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_delivery_cash_sync_remittance_and_admin_approval_work(): void
    {
        $order = $this->deliveredOrderWithRider();

        DeliveryBoyAssignment::query()
            ->where('order_id', $order->id)
            ->update(['cash_collected' => 100]);

        app(DeliveryCashFeedbackService::class)
            ->syncCashCollections();

        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/delivery-boy/cash-statistics')
            ->assertOk()
            ->assertJsonPath('data.cash_in_hand', 100);

        $remittance = $this->postJson(
            '/api/delivery-boy/cash-remittances',
            [
                'amount' => 50,
                'reference' => 'CASH-001',
            ]
        )->assertCreated();

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/delivery-cash/'
            .$remittance->json('data.id')
            .'/process',
            ['status' => 'approved']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(
            50.0,
            app(DeliveryCashFeedbackService::class)
                ->riderBalance($this->rider)['cash_in_hand']
        );
    }

    public function test_signed_payment_webhook_completes_order_and_is_idempotent(): void
    {
        $gateway = PaymentGatewayConfig::query()
            ->where('code', 'sslcommerz')
            ->firstOrFail();

        $gateway->update([
            'enabled' => true,
            'test_mode' => true,
            'supported_currencies' => ['BDT'],
            'secret_config' => [
                'webhook_secret' => 'phase9-test-secret',
            ],
        ]);

        $order = $this->pendingOrder();

        Sanctum::actingAs($this->customer);

        $intent = $this->postJson('/api/payments/intents', [
            'provider' => 'sslcommerz',
            'purpose' => 'order',
            'order_id' => $order->id,
            'currency' => 'BDT',
        ])->assertCreated();

        $payload = [
            'event_id' => 'evt-phase9-order-001',
            'event_type' => 'payment.completed',
            'payment_intent_uuid' => $intent->json('data.uuid'),
            'transaction_id' => 'SSL-TXN-001',
            'status' => 'completed',
        ];

        $raw = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
        );

        $signature = hash_hmac(
            'sha256',
            $raw,
            'phase9-test-secret'
        );

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FASTSHEBA_SIGNATURE' => $signature,
            'HTTP_X_EVENT_ID' => 'evt-phase9-order-001',
        ];

        $this->call(
            'POST',
            '/api/payments/webhooks/sslcommerz',
            [],
            [],
            [],
            $server,
            $raw
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'processed');

        $this->call(
            'POST',
            '/api/payments/webhooks/sslcommerz',
            [],
            [],
            [],
            $server,
            $raw
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'processed');

        $order->refresh();

        $this->assertSame('completed', $order->payment_status);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseHas('order_payment_transactions', [
            'order_id' => $order->id,
            'transaction_id' => 'SSL-TXN-001',
        ]);
    }

    public function test_seller_can_create_scoped_role_and_team_member(): void
    {
        Sanctum::actingAs($this->sellerUser);

        $role = $this->postJson('/api/seller/team/roles', [
            'name' => 'POS Cashier',
            'permissions' => [
                'seller.pos.use',
            ],
        ])->assertCreated();

        $member = $this->postJson('/api/seller/team/members', [
            'name' => 'Demo Cashier',
            'email' => 'phase9-cashier@example.test',
            'mobile' => '01718880003',
            'password' => 'Test@123456',
            'position' => 'Cashier',
            'role_id' => $role->json('data.id'),
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'email' => 'phase9-cashier@example.test',
            ]);

        $this->getJson('/api/seller/team/members')
            ->assertOk()
            ->assertJsonFragment([
                'position' => 'Cashier',
            ]);

        $this->assertDatabaseHas('seller_user', [
            'seller_id' => $this->seller->id,
            'user_id' => $member->json('data.id'),
        ]);
    }

    public function test_admin_can_manage_tax_collection_and_release_records(): void
    {
        Sanctum::actingAs($this->admin);

        $rate = $this->postJson('/api/admin/tax-rates', [
            'name' => 'Test VAT',
            'rate' => 7.5,
            'country_code' => 'BD',
            'priority' => 2,
            'compound' => false,
        ])->assertCreated();

        $this->postJson('/api/admin/tax-classes', [
            'name' => 'Test Tax Class',
            'rate_ids' => [$rate->json('data.id')],
        ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Test VAT']);

        $this->postJson('/api/admin/collections', [
            'title' => 'Phase 9 Collection',
            'product_ids' => [$this->product->id],
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'slug' => 'phase-9-collection',
            ]);

        $this->postJson('/api/admin/system-releases', [
            'version' => '1.0.1-test',
            'status' => 'testing',
            'release_notes' => 'Automated final operations test.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'testing');

        $this->assertDatabaseHas('system_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'collection.saved',
        ]);
    }

    public function test_non_admin_is_blocked_from_final_admin_routes(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/operations/dashboard')
            ->assertForbidden();

        $this->getJson('/api/admin/command-runs')
            ->assertForbidden();

        $this->postJson('/api/admin/system-releases', [
            'version' => 'forbidden',
            'status' => 'planned',
        ])->assertForbidden();
    }

    private function posPayload(int $quantity): array
    {
        $unitPrice = (float) $this->inventory->effectivePrice();
        $total = round($unitPrice * $quantity, 2);

        return [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'reference' => 'POS-TEST-'.$quantity,
            'items' => [
                [
                    'inventory_id' => $this->inventory->id,
                    'quantity' => $quantity,
                ],
            ],
            'tenders' => [
                [
                    'method' => 'cash',
                    'amount' => $total,
                    'received_amount' => $total,
                ],
            ],
        ];
    }

    private function deliveredOrderWithRider(): Order
    {
        $order = Order::query()->create([
            'slug' => 'FS-FINAL-'.Str::upper(Str::random(8)),
            'user_id' => $this->customer->id,
            'delivery_zone_id' =>
                \App\Models\DeliveryZone::query()->value('id'),
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'completed',
            'delivery_type' => 'delivery',
            'subtotal' => 100,
            'total_payable' => 100,
            'final_total' => 100,
            'billing_name' => $this->customer->name,
            'shipping_name' => $this->customer->name,
            'paid_at' => now(),
        ]);

        $order->delivery_boy_id = $this->rider->id;
        $order->delivered_at = now();
        $order->save();

        $sellerOrder = SellerOrder::query()->create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'status' => 'delivered',
            'delivery_type' => 'delivery',
            'subtotal' => 100,
            'commission_amount' => 10,
            'seller_earnings' => 90,
            'accepted_at' => now()->subHour(),
            'ready_for_pickup_at' => now()->subMinutes(30),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'product_title' => $this->product->title,
            'variant_title' => $this->variant->title,
            'sku' => $this->inventory->sku,
            'price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
            'status' => 'delivered',
            'is_returnable' => false,
        ]);

        DeliveryBoyAssignment::query()->create([
            'order_id' => $order->id,
            'delivery_boy_id' => $this->rider->id,
            'status' => 'delivered',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
            'picked_up_at' => now()->subMinutes(30),
            'out_for_delivery_at' => now()->subMinutes(20),
            'delivered_at' => now(),
            'total_earnings' => 50,
            'cash_collected' => 0,
            'payment_status' => 'completed',
        ]);

        return $order->fresh();
    }

    private function pendingOrder(): Order
    {
        return Order::query()->create([
            'slug' => 'FS-PAY-'.Str::upper(Str::random(8)),
            'user_id' => $this->customer->id,
            'delivery_zone_id' =>
                \App\Models\DeliveryZone::query()->value('id'),
            'status' => 'awaiting_store_response',
            'payment_method' => 'sslcommerz',
            'payment_status' => 'pending',
            'delivery_type' => 'delivery',
            'subtotal' => 100,
            'total_payable' => 100,
            'final_total' => 100,
            'billing_name' => $this->customer->name,
            'shipping_name' => $this->customer->name,
        ]);
    }
}
