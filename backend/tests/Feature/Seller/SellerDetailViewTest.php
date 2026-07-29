<?php

namespace Tests\Feature\Seller;

use App\Enums\GuardNameEnum;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\SellerPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerDetailViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_store_detail_view_renders_without_blade_parse_errors(): void
    {
        $this->seed(SellerPanelSeeder::class);

        $user = User::factory()->create([
            'name' => 'FastSheba Seller Detail Test',
            'email' => 'seller-detail@fastsheba.test',
            'mobile' => '01700000098',
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
            'business_name' => 'FastSheba Seller Detail Business',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
            'status' => 'active',
        ]);

        $store = Store::query()->create([
            'seller_id' => $seller->id,
            'name' => 'FastSheba Detail Outlet',
            'city' => 'Dhaka',
            'status' => 'online',
            'verification_status' => 'approved',
            'visibility_status' => 'visible',
            'pos_enabled' => true,
            'allows_pickup' => true,
        ]);

        $this->actingAs($user, 'seller')
            ->withSession(['seller_id' => $seller->id])
            ->get(route('seller.resource.stores.show', ['id' => $store->id]))
            ->assertOk()
            ->assertSee('FastSheba Detail Outlet');
    }
}
