<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Address;
use App\Models\GiftCardRedemption;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\ProductVariant;
use App\Models\Referral;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ReferralService;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\DeliveryReturnSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\GrowthSupportPharmacySeeder;
use Database\Seeders\OrderPaymentSeeder;
use Database\Seeders\SellerManagementFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthSupportPharmacyApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $sellerUser;
    protected User $admin;
    protected Seller $seller;
    protected Store $store;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media-library.disk_name' => 'public']);
        Storage::fake('public');

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);
        $this->seed(OrderPaymentSeeder::class);
        $this->seed(DeliveryReturnSeeder::class);
        $this->seed(SellerManagementFinanceSeeder::class);
        $this->seed(GrowthSupportPharmacySeeder::class);

        $this->sellerUser = User::query()
            ->where('email', 'seller@fastsheba.test')
            ->firstOrFail();

        $this->seller = Seller::query()
            ->where('user_id', $this->sellerUser->id)
            ->firstOrFail();

        $this->store = Store::query()
            ->where('seller_id', $this->seller->id)
            ->firstOrFail();

        $this->product = Product::query()->firstOrFail();
        $this->variant = ProductVariant::query()->firstOrFail();

        $this->customer = User::query()->create([
            'name' => 'Engagement Customer',
            'email' => 'engagement-customer@example.test',
            'mobile' => '01715550001',
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
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Engagement Admin',
            'email' => 'engagement-admin@example.test',
            'mobile' => '01715550002',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);
    }

    public function test_public_marketing_content_and_faqs_are_available(): void
    {
        $this->getJson(
            '/api/banners?latitude=23.8103&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['slug' => 'fastsheba-pharmacy']);

        $this->getJson(
            '/api/featured-sections?latitude=23.8103&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonFragment(['slug' => 'popular-near-you']);

        $this->getJson('/api/faqs')
            ->assertOk()
            ->assertJsonFragment([
                'category' => 'prescriptions',
            ]);
    }

    public function test_customer_can_review_delivered_item_only_once(): void
    {
        $item = $this->deliveredOrderItem($this->customer);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/user/reviews', [
            'order_item_id' => $item->id,
            'rating' => 5,
            'title' => 'Good service',
            'comment' => 'Medicine arrived in good condition.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.status', 'published');

        $this->postJson('/api/user/reviews', [
            'order_item_id' => $item->id,
            'rating' => 4,
            'comment' => 'Duplicate review.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_item_id']);

        $this->assertDatabaseHas('reviews', [
            'id' => $response->json('data.id'),
            'order_item_id' => $item->id,
        ]);
    }

    public function test_seller_can_reply_to_owned_review_and_customer_is_notified(): void
    {
        $item = $this->deliveredOrderItem($this->customer);

        $review = Review::query()->create([
            'user_id' => $this->customer->id,
            'product_id' => $item->product_id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'store_id' => $item->store_id,
            'rating' => 4,
            'comment' => 'Helpful product.',
            'status' => 'published',
        ]);

        Sanctum::actingAs($this->sellerUser);

        $this->postJson('/api/seller/reviews/'.$review->id.'/reply', [
            'reply' => 'Thank you for your feedback.',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.seller_reply',
                'Thank you for your feedback.'
            );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'review_reply',
        ]);
    }

    public function test_customer_question_can_be_answered_by_product_owner(): void
    {
        Sanctum::actingAs($this->customer);

        $faqId = $this->postJson(
            '/api/user/products/'.$this->product->slug.'/questions',
            ['question' => 'Should this be taken after food?']
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        Sanctum::actingAs($this->sellerUser);

        $this->postJson('/api/seller/product-faqs/'.$faqId.'/answer', [
            'answer' => 'Follow the physician or package instructions.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->getJson('/api/products/'.$this->product->slug.'/faqs')
            ->assertOk()
            ->assertJsonFragment([
                'question' => 'Should this be taken after food?',
            ]);
    }

    public function test_admin_broadcast_reaches_customer_inbox_and_can_be_read(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/notification-campaigns/broadcast', [
            'audience_type' => 'customer',
            'title' => 'FastSheba update',
            'message' => 'A new pharmacy offer is available.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.recipient_count', 1);

        Sanctum::actingAs($this->customer);

        $notificationId = $this->getJson('/api/user/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->json('data.data.0.id');

        $this->postJson('/api/user/notifications/'.$notificationId.'/state', [
            'is_read' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->getJson('/api/user/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_support_ticket_customer_and_admin_conversation_works(): void
    {
        Sanctum::actingAs($this->customer);

        $typeId = \App\Models\SupportTicketType::query()->value('id');

        $ticket = $this->postJson('/api/user/support-tickets', [
            'ticket_type_id' => $typeId,
            'subject' => 'Order delivery question',
            'description' => 'Please check my delivery status.',
            'priority' => 'normal',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $ticketId = $ticket->json('data.id');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/support-tickets/'.$ticketId.'/reply', [
            'message' => 'Our support team is checking this.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->putJson('/api/admin/support-tickets/'.$ticketId.'/status', [
            'status' => 'resolved',
            'assigned_to' => $this->admin->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'support_ticket',
        ]);
    }

    public function test_prescription_can_be_uploaded_approved_and_fulfilled(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->post('/api/user/prescriptions', [
            'patient_name' => 'Demo Patient',
            'patient_age' => 28,
            'doctor_name' => 'Demo Doctor',
            'items' => [
                [
                    'medicine_name' => 'Paracetamol',
                    'strength' => '500 mg',
                    'quantity' => 10,
                ],
            ],
            'files' => [
                UploadedFile::fake()->image('prescription.jpg'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $prescriptionId = $response->json('data.id');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/prescriptions/'.$prescriptionId.'/review', [
            'status' => 'approved',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'review_notes' => 'Prescription is readable.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        Sanctum::actingAs($this->sellerUser);

        $this->getJson('/api/seller/prescriptions')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->postJson(
            '/api/seller/prescriptions/'.$prescriptionId.'/fulfill',
            ['note' => 'Prepared by pharmacy.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');
    }

    public function test_referral_rewards_are_settled_after_first_delivered_order(): void
    {
        $referrer = User::query()->create([
            'name' => 'Referral Owner',
            'email' => 'referrer@example.test',
            'mobile' => '01715550003',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
        ]);

        Wallet::query()->create([
            'user_id' => $referrer->id,
            'type' => 'customer',
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $code = app(ReferralService::class)->ensureCode($referrer);

        Sanctum::actingAs($this->customer);

        $this->postJson('/api/user/referral/submit', [
            'referral_code' => $code,
        ])->assertOk();

        $this->deliveredOrderItem($this->customer);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/referrals/sync')
            ->assertOk()
            ->assertJsonPath('data.settled', 2);

        $this->assertSame(
            50.0,
            (float) Wallet::query()
                ->where('user_id', $referrer->id)
                ->where('type', 'customer')
                ->value('balance')
        );

        $this->assertSame(
            25.0,
            (float) Wallet::query()
                ->where('user_id', $this->customer->id)
                ->where('type', 'customer')
                ->value('balance')
        );
    }

    public function test_gift_card_credits_wallet_once(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/user/gift-cards/redeem', [
            'code' => 'FASTSHEBA100',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '100.00');

        $this->postJson('/api/user/gift-cards/redeem', [
            'code' => 'FASTSHEBA100',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseCount('gift_card_redemptions', 1);

        $this->assertSame(
            100.0,
            (float) Wallet::query()
                ->where('user_id', $this->customer->id)
                ->where('type', 'customer')
                ->value('balance')
        );
    }

    public function test_admin_review_moderation_is_audited(): void
    {
        $item = $this->deliveredOrderItem($this->customer);

        $review = Review::query()->create([
            'user_id' => $this->customer->id,
            'product_id' => $item->product_id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'store_id' => $item->store_id,
            'rating' => 1,
            'comment' => 'Moderation test.',
            'status' => 'published',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', [
            'status' => 'hidden',
            'note' => 'Temporarily hidden for review.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden');

        $this->assertDatabaseHas('system_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'review.moderated',
            'entity_id' => $review->id,
        ]);
    }

    public function test_seller_cannot_fulfill_another_sellers_prescription(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other Seller',
            'email' => 'other-engagement-seller@example.test',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::SELLER->value,
            'logged_in_type' => 'platform',
        ]);

        $otherSeller = Seller::query()->create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Pharmacy',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
            'status' => 'active',
        ]);

        $prescription = \App\Models\Prescription::query()->create([
            'user_id' => $this->customer->id,
            'seller_id' => $otherSeller->id,
            'patient_name' => 'Ownership Test',
            'status' => 'approved',
            'approved_at' => now(),
            'expires_at' => now()->addDays(2),
        ]);

        Sanctum::actingAs($this->sellerUser);

        $this->postJson(
            '/api/seller/prescriptions/'.$prescription->id.'/fulfill',
            []
        )->assertNotFound();
    }

    public function test_non_admin_cannot_access_admin_engagement_routes(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/audit-logs')
            ->assertForbidden();

        $this->getJson('/api/admin/support-tickets')
            ->assertForbidden();
    }

    private function deliveredOrderItem(User $customer): OrderItem
    {
        $order = Order::query()->create([
            'slug' => 'FS-ENG-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'user_id' => $customer->id,
            'delivery_zone_id' => \App\Models\DeliveryZone::query()->value('id'),
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'completed',
            'delivery_type' => 'delivery',
            'subtotal' => 100,
            'total_payable' => 100,
            'final_total' => 100,
            'billing_name' => $customer->name,
            'shipping_name' => $customer->name,
            'delivered_at' => now(),
            'paid_at' => now(),
        ]);

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

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'product_title' => $this->product->title,
            'variant_title' => $this->variant->title,
            'sku' => 'ENGAGEMENT-SKU',
            'price' => 100,
            'special_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
            'status' => 'delivered',
            'is_returnable' => false,
        ]);
    }
}
