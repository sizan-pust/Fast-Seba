<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Banner;
use App\Models\GiftCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNullableDateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nullable_admin_dates_render_without_carbon_errors(): void
    {
        $admin = $this->admin();

        $banner = Banner::query()->create([
            'type' => 'custom',
            'title' => 'Nullable Date Banner',
            'position' => 'home_top',
            'visibility_status' => 'published',
            'display_order' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $giftCard = GiftCard::query()->create([
            'code' => 'NULLDATE100',
            'title' => 'Nullable Date Gift Card',
            'amount' => 100,
            'currency_code' => 'BDT',
            'max_redemptions' => 10,
            'redemption_count' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.manage.banners.index'))
            ->assertOk()
            ->assertSee('Nullable Date Banner');

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.manage.gift-cards-referrals.index',
                ['view' => 'gift-cards']
            ))
            ->assertOk()
            ->assertSee('Nullable Date Gift Card');

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.manage.banners.show',
                $banner->id
            ))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.manage.gift-cards-referrals.show',
                [
                    'id' => $giftCard->id,
                    'view' => 'gift-cards',
                ]
            ))
            ->assertOk();
    }

    private function admin(): User
    {
        $role = Role::findOrCreate(
            DefaultSystemRolesEnum::SUPER_ADMIN->value,
            GuardNameEnum::ADMIN->value
        );

        $admin = User::factory()->create([
            'name' => 'Nullable Date Admin',
            'email' => 'nullable-date-admin@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
