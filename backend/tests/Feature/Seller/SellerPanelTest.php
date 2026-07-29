<?php

namespace Tests\Feature\Seller;

use App\Enums\GuardNameEnum;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\Seller\SellerPanelResourceService;
use Database\Seeders\SellerPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_login_and_open_dashboard(): void
    {
        [$user] = $this->sellerAccount();

        $this->post(route('seller.login.attempt'), [
            'email' => $user->email,
            'password' => 'Test@123456',
        ])->assertRedirect(route('seller.dashboard'));

        $this->assertAuthenticatedAs($user, 'seller');

        $this->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Seller Operations');
    }

    public function test_owner_can_open_every_live_seller_module(): void
    {
        [$user] = $this->sellerAccount();

        foreach (SellerPanelResourceService::LIVE_MODULES as $module) {
            $this->actingAs($user, 'seller')
                ->withSession(['seller_id' => $user->ownedSeller->id])
                ->get(route('seller.resource.'.$module.'.index'))
                ->assertOk();
        }
    }

    public function test_seller_can_create_and_toggle_owned_store(): void
    {
        [$user, $seller] = $this->sellerAccount();

        $response = $this->actingAs($user, 'seller')
            ->withSession(['seller_id' => $seller->id])
            ->post(route('seller.resource.stores.store'), [
                'name' => 'FastSheba Test Outlet',
                'city' => 'Dhaka',
                'allows_pickup' => 1,
                'pos_enabled' => 1,
            ]);

        $response->assertSessionHasNoErrors();

        $store = Store::query()->where('seller_id', $seller->id)->firstOrFail();
        $response->assertRedirect(route('seller.resource.stores.show', $store->id));

        $this->actingAs($user, 'seller')
            ->withSession(['seller_id' => $seller->id])
            ->post(route('seller.resource.stores.action', $store->id), [
                'status' => 'online',
            ])->assertSessionHas('success');

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'status' => 'online',
            'pos_enabled' => true,
        ]);
    }

    public function test_customer_cannot_access_seller_panel(): void
    {
        $customer = User::factory()->create([
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->actingAs($customer, 'seller')
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.login'));
    }

    private function sellerAccount(): array
    {
        $this->seed(SellerPanelSeeder::class);

        $user = User::factory()->create([
            'name' => 'FastSheba Seller Test',
            'email' => 'seller-panel@fastsheba.test',
            'mobile' => '01700000099',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::SELLER->value,
            'logged_in_type' => 'platform',
            'email_verified_at' => now(),
        ]);

        $role = Role::findOrCreate('seller', GuardNameEnum::SELLER->value);
        $user->syncRoles([$role]);

        $seller = Seller::query()->create([
            'user_id' => $user->id,
            'business_name' => 'FastSheba Seller Test Business',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
            'status' => 'active',
        ]);

        $user->setRelation('ownedSeller', $seller);

        return [$user, $seller];
    }
}
