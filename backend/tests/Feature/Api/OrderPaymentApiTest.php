<?php
namespace Tests\Feature\Api;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\OrderPaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class OrderPaymentApiTest extends TestCase
{
    use RefreshDatabase;
    protected User $user; protected Store $store; protected ProductVariant $variant; protected Address $address;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);$this->seed(CatalogueInventorySeeder::class);$this->seed(CommerceSeeder::class);$this->seed(OrderPaymentSeeder::class);
        $this->user=User::query()->create(['name'=>'Order Customer','email'=>'order@example.test','mobile'=>'01717770000','password'=>'Test@123456','status'=>'active','access_panel'=>GuardNameEnum::WEB->value,'logged_in_type'=>'platform','country'=>'Bangladesh','iso_2'=>'BD','email_verified_at'=>now(),'mobile_verified_at'=>now()]);
        $this->user->syncRoles([DefaultSystemRolesEnum::CUSTOMER->value]);
        Wallet::query()->create(['user_id'=>$this->user->id,'type'=>'customer','balance'=>1000,'blocked_balance'=>0,'currency_code'=>'BDT']);
        $this->store=Store::query()->firstOrFail();$this->variant=ProductVariant::query()->firstOrFail();
        $this->address=Address::query()->create(['user_id'=>$this->user->id,'address_line1'=>'Dhaka','city'=>'Dhaka','mobile'=>'01717770000','address_type'=>'home','country'=>'Bangladesh','country_code'=>'+880','latitude'=>23.8103,'longitude'=>90.4125,'is_default'=>true]);
        Sanctum::actingAs($this->user);
    }
    private function addCart(int $quantity=2): void
    {
        $this->postJson('/api/user/cart/add',['product_variant_id'=>$this->variant->id,'store_id'=>$this->store->id,'quantity'=>$quantity])->assertCreated();
    }
    public function test_verified_customer_can_create_cod_order(): void
    {
        $before=StoreProductVariant::query()->first()->stock;$this->addCart();
        $response=$this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery','rush_delivery'=>false,'use_wallet'=>false])->assertOk()->assertJsonPath('success',true)->assertJsonPath('data.payment_status','pending');
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('seller_orders',1);$this->assertDatabaseCount('order_items',1);$this->assertDatabaseCount('cart_items',0);
        $this->assertSame($before-2,StoreProductVariant::query()->first()->stock);
        $this->getJson('/api/user/orders/'.$response->json('data.slug'))->assertOk()->assertJsonPath('data.items.0.quantity',2);
    }
    public function test_unverified_customer_cannot_checkout(): void
    {
        $this->user->update(['email_verified_at'=>null]);$this->addCart(1);
        $this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery'])->assertUnprocessable()->assertJsonValidationErrors(['verification']);
        $this->assertDatabaseCount('orders',0);
    }
    public function test_wallet_order_deducts_balance_and_creates_transactions(): void
    {
        $this->addCart(1);
        $this->postJson('/api/user/orders',['payment_type'=>'wallet','address_id'=>$this->address->id,'delivery_type'=>'delivery','use_wallet'=>true])->assertOk()->assertJsonPath('data.payment_status','completed')->assertJsonPath('data.total_payable','0.00');
        $this->assertDatabaseCount('wallet_transactions',1);$this->assertDatabaseCount('order_payment_transactions',1);
        $this->assertLessThan(1000,(float)Wallet::query()->where('user_id',$this->user->id)->first()->balance);
    }
    public function test_pickup_rejects_cod(): void
    {
        $this->addCart(1);
        $this->postJson('/api/user/orders',['payment_type'=>'cod','delivery_type'=>'pickup'])->assertUnprocessable()->assertJsonValidationErrors(['payment_type']);
    }
    public function test_customer_can_cancel_item_and_stock_is_restored(): void
    {
        $before=StoreProductVariant::query()->first()->stock;$this->addCart(1);
        $created=$this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery'])->assertOk();
        $itemId=$created->json('data.items.0.id');
        $this->postJson('/api/user/orders/items/'.$itemId.'/cancel')->assertOk()->assertJsonPath('data.items.0.orderItem.status','cancelled');
        $this->assertSame($before,StoreProductVariant::query()->first()->stock);
    }
    public function test_customer_can_reorder_previous_order(): void
    {
        $this->addCart(1);$created=$this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery'])->assertOk();
        $this->postJson('/api/user/orders/'.$created->json('data.id').'/reorder')->assertOk()->assertJsonPath('data.added',1);
        $this->assertDatabaseCount('cart_items',1);
    }
    public function test_order_list_and_transactions_are_paginated(): void
    {
        $this->addCart(1);$this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery'])->assertOk();
        $this->getJson('/api/user/orders')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/user/order-transactions')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/user/wallet')->assertOk()->assertJsonPath('data.currency_code','BDT');
    }
    public function test_demo_seller_can_login_and_manage_order_item_status(): void
    {
        $this->addCart(1);$created=$this->postJson('/api/user/orders',['payment_type'=>'cod','address_id'=>$this->address->id,'delivery_type'=>'delivery'])->assertOk();
        $itemId=$created->json('data.items.0.id');
        $login=$this->postJson('/api/seller/login',['email'=>'seller@fastsheba.test','password'=>'Test@123456'])->assertOk();
        $token=$login->json('access_token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/seller/orders')->assertOk()->assertJsonPath('data.total',1);
        $this->withToken($token)->postJson('/api/seller/order-items/'.$itemId.'/status',['status'=>'accepted_by_seller'])->assertOk()->assertJsonPath('data.orderItem.status','accepted_by_seller');
        $this->withToken($token)->postJson('/api/seller/order-items/'.$itemId.'/status',['status'=>'preparing'])->assertOk()->assertJsonPath('data.orderItem.status','preparing');
    }
    public function test_payment_variables_expose_only_enabled_local_methods(): void
    {
        $this->getJson('/api/payment/variables')->assertOk()->assertJsonPath('data.cod',true)->assertJsonPath('data.wallet',true)->assertJsonPath('data.stripePayment',false);
    }
}
