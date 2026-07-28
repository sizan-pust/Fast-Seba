<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Address;
use App\Models\DeliveryBoy;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\DeliveryReturnSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\OrderPaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryReturnApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $riderUser;
    protected User $sellerUser;
    protected User $admin;
    protected Address $address;
    protected Store $store;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);
        $this->seed(OrderPaymentSeeder::class);
        $this->seed(DeliveryReturnSeeder::class);

        Product::query()->firstOrFail()->update([
            'is_returnable' => true,
            'returnable_days' => 7,
        ]);

        $this->customer = User::query()->create([
            'name' => 'Delivery Customer',
            'email' =>
                'delivery-customer@example.test',
            'mobile' => '01718880000',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' =>
                GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
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

        $this->store = Store::query()
            ->firstOrFail();

        $this->variant = ProductVariant::query()
            ->firstOrFail();

        $this->address = Address::query()->create([
            'user_id' => $this->customer->id,
            'address_line1' =>
                'Dhaka Test Address',
            'city' => 'Dhaka',
            'mobile' => '01718880000',
            'address_type' => 'home',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'is_default' => true,
        ]);

        $this->riderUser = User::query()
            ->where(
                'email',
                'rider@fastsheba.test'
            )
            ->firstOrFail();

        $this->sellerUser = User::query()
            ->where(
                'email',
                'seller@fastsheba.test'
            )
            ->firstOrFail();

        $this->admin = User::query()->create([
            'name' => 'API Admin',
            'email' => 'api-admin@example.test',
            'mobile' => '01718880001',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' =>
                GuardNameEnum::ADMIN->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);
    }

    private function createOrder(
        bool $ready = false
    ): Order {
        Sanctum::actingAs($this->customer);

        $this->postJson(
            '/api/user/cart/add',
            [
                'product_variant_id' =>
                    $this->variant->id,
                'store_id' => $this->store->id,
                'quantity' => 1,
            ]
        )->assertCreated();

        $response = $this->postJson(
            '/api/user/orders',
            [
                'payment_type' => 'cod',
                'address_id' => $this->address->id,
                'delivery_type' => 'delivery',
            ]
        )->assertOk();

        $order = Order::query()->findOrFail(
            $response->json('data.id')
        );

        if ($ready) {
            $order->items()->update([
                'status' => 'ready_for_pickup',
            ]);

            $order->sellerOrders()->update([
                'status' => 'ready_for_pickup',
            ]);

            $order->update([
                'status' => 'ready_for_pickup',
            ]);
        }

        return $order->fresh(['items']);
    }

    private function deliveredOrder(): Order
    {
        $order = $this->createOrder(true);

        $order->items()->update([
            'status' => 'delivered',
            'is_returnable' => true,
            'returnable_until' => now()->addDays(7),
        ]);

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return $order->fresh(['items']);
    }

    public function test_demo_rider_can_login_and_fetch_profile(): void
    {
        $login = $this->postJson(
            '/api/delivery-boy/login',
            [
                'email' => 'rider@fastsheba.test',
                'password' => 'Test@123456',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'available'
            );

        $this->withToken(
            $login->json('access_token')
        )
            ->getJson('/api/delivery-boy/profile')
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'approved'
            );
    }

    public function test_rider_can_update_and_read_location(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->postJson(
            '/api/delivery-boy/update-current-location',
            [
                'latitude' => 23.811,
                'longitude' => 90.413,
                'heading' => 180,
            ]
        )->assertOk();

        $this->getJson(
            '/api/delivery-boy/get-last-location'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.latitude',
                23.811
            );
    }

    public function test_rider_can_accept_and_deliver_ready_order(): void
    {
        $order = $this->createOrder(true);

        Sanctum::actingAs($this->riderUser);

        $this->getJson(
            '/api/delivery-boy/orders/available'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->postJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/accept'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'assigned'
            );

        $this->putJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/status',
            ['status' => 'picked_up']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'picked_up'
            );

        $this->putJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/status',
            ['status' => 'out_for_delivery']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'out_for_delivery'
            );

        $this->putJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/status',
            ['status' => 'delivered']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'delivered'
            )
            ->assertJsonPath(
                'data.payment_status',
                'completed'
            );

        Sanctum::actingAs($this->customer);

        $this->getJson(
            '/api/user/orders/'
                .$order->slug
                .'/delivery-boy-location'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned',
                true
            );
    }

    public function test_invalid_delivery_transition_is_rejected(): void
    {
        $order = $this->createOrder(true);

        Sanctum::actingAs($this->riderUser);

        $this->postJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/accept'
        )->assertOk();

        $this->putJson(
            '/api/delivery-boy/orders/'
                .$order->id
                .'/status',
            ['status' => 'delivered']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_customer_can_request_and_cancel_return(): void
    {
        $order = $this->deliveredOrder();
        $item = $order->items->firstOrFail();

        Sanctum::actingAs($this->customer);

        $this->postJson(
            '/api/user/orders/items/'
                .$item->id
                .'/return',
            [
                'reason' =>
                    'Package was damaged.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'requested'
            );

        $this->postJson(
            '/api/user/orders/items/'
                .$item->id
                .'/return-cancel'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'cancelled'
            );
    }

    public function test_seller_approves_and_rider_completes_return_pickup(): void
    {
        $order = $this->deliveredOrder();
        $item = $order->items->firstOrFail();

        Sanctum::actingAs($this->customer);

        $returnId = $this->postJson(
            '/api/user/orders/items/'
                .$item->id
                .'/return',
            [
                'reason' =>
                    'Wrong medicine supplied.',
            ]
        )
            ->assertOk()
            ->json('data.id');

        Sanctum::actingAs($this->sellerUser);

        $this->postJson(
            '/api/seller/returns/'
                .$returnId
                .'/decision',
            ['decision' => 'approve']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'seller_approved'
            );

        Sanctum::actingAs($this->riderUser);

        $this->getJson(
            '/api/delivery-boy/return-pickups/available'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->postJson(
            '/api/delivery-boy/return-pickups/'
                .$returnId
                .'/accept'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'pickup_assigned'
            );

        $this->putJson(
            '/api/delivery-boy/return-pickups/'
                .$returnId
                .'/status',
            ['status' => 'picked_up']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'picked_up'
            );

        $this->putJson(
            '/api/delivery-boy/return-pickups/'
                .$returnId
                .'/status',
            ['status' => 'received_by_seller']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'received_by_seller'
            );
    }

    public function test_admin_can_refund_received_return_to_wallet(): void
    {
        $order = $this->deliveredOrder();
        $item = $order->items->firstOrFail();
        $seller = Seller::query()->firstOrFail();

        $return = OrderItemReturn::query()->create([
            'order_item_id' => $item->id,
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'store_id' => $item->store_id,
            'quantity' => 1,
            'reason' => 'Refund test',
            'refund_amount' => $item->subtotal,
            'refund_method' => 'wallet',
            'pickup_status' =>
                'delivered_to_seller',
            'return_status' =>
                'received_by_seller',
            'requested_at' => now(),
            'received_at' => now(),
        ]);

        $before = (float) Wallet::query()
            ->where(
                'user_id',
                $this->customer->id
            )
            ->where('type', 'customer')
            ->value('balance');

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/returns/'
                .$return->id
                .'/refund',
            ['comment' => 'Approved refund']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'completed'
            );

        $after = (float) Wallet::query()
            ->where(
                'user_id',
                $this->customer->id
            )
            ->where('type', 'customer')
            ->value('balance');

        $this->assertGreaterThan(
            $before,
            $after
        );

        $this->assertDatabaseHas(
            'refund_transactions',
            [
                'order_item_return_id' =>
                    $return->id,
                'status' => 'completed',
            ]
        );
    }

    public function test_admin_can_assign_ready_order_to_rider(): void
    {
        $order = $this->createOrder(true);

        $rider = DeliveryBoy::query()
            ->firstOrFail();

        Sanctum::actingAs($this->admin);

        $this->postJson(
            '/api/admin/orders/'
                .$order->id
                .'/assign-rider',
            [
                'delivery_boy_id' =>
                    $rider->id,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'assigned'
            )
            ->assertJsonPath(
                'data.delivery_boy.id',
                $rider->id
            );
    }

    public function test_seller_can_reject_return(): void
    {
        $order = $this->deliveredOrder();
        $item = $order->items->firstOrFail();

        Sanctum::actingAs($this->customer);

        $returnId = $this->postJson(
            '/api/user/orders/items/'
                .$item->id
                .'/return',
            [
                'reason' =>
                    'No longer needed.',
            ]
        )
            ->assertOk()
            ->json('data.id');

        Sanctum::actingAs($this->sellerUser);

        $this->postJson(
            '/api/seller/returns/'
                .$returnId
                .'/decision',
            [
                'decision' => 'reject',
                'comment' =>
                    'Opened medicine cannot be returned.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.return_status',
                'seller_rejected'
            );
    }
}
