<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Faq;
use App\Models\PaymentGatewayConfig;
use App\Models\User;
use App\Services\Admin\AdminEngagementFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEngagementFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_every_admin_c_index(): void
    {
        $admin = $this->admin();

        foreach (AdminEngagementFinanceService::LIVE_MODULES as $module) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.manage.'.$module.'.index'))
                ->assertOk();
        }
    }

    public function test_admin_can_open_all_tabbed_admin_c_queues(): void
    {
        $admin = $this->admin();

        $tabs = [
            'subscriptions' => ['plans', 'subscribers'],
            'gift-cards-referrals' => [
                'gift-cards',
                'referrals',
                'earnings',
            ],
            'rider-cash' => ['cash', 'withdrawals'],
            'payment-intents' => ['intents', 'webhooks'],
            'reviews' => ['product', 'seller', 'delivery'],
            'faqs' => ['general', 'product'],
        ];

        foreach ($tabs as $module => $views) {
            foreach ($views as $view) {
                $this->actingAs($admin, 'admin')
                    ->get(route(
                        'admin.manage.'.$module.'.index',
                        ['view' => $view]
                    ))
                    ->assertOk();
            }
        }
    }

    public function test_admin_can_create_update_and_delete_general_faq(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin, 'admin')
            ->post(route(
                'admin.manage.faqs.store',
                ['view' => 'general']
            ), [
                'view' => 'general',
                'category' => 'Orders',
                'question' => 'How do I track my order?',
                'answer' => 'Open your order details.',
                'sort_order' => 1,
                'status' => 'active',
            ]);

        $faq = Faq::query()->firstOrFail();

        $created->assertRedirect(route(
            'admin.manage.faqs.show',
            [
                'id' => $faq->id,
                'view' => 'general',
            ]
        ));

        $this->actingAs($admin, 'admin')
            ->put(route(
                'admin.manage.faqs.update',
                [
                    'id' => $faq->id,
                    'view' => 'general',
                ]
            ), [
                'view' => 'general',
                'category' => 'Delivery',
                'question' => 'How do I track my order?',
                'answer' => 'Use the live tracking screen.',
                'sort_order' => 2,
                'status' => 'active',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('faqs', [
            'id' => $faq->id,
            'category' => 'Delivery',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route(
                'admin.manage.faqs.destroy',
                [
                    'id' => $faq->id,
                    'view' => 'general',
                ]
            ))
            ->assertRedirect(route(
                'admin.manage.faqs.index',
                ['view' => 'general']
            ));

        $this->assertDatabaseMissing('faqs', [
            'id' => $faq->id,
        ]);
    }

    public function test_gateway_update_keeps_existing_secret_when_blank(): void
    {
        $admin = $this->admin();

        $gateway = PaymentGatewayConfig::query()->create([
            'code' => 'sslcommerz',
            'display_name' => 'SSLCommerz',
            'enabled' => false,
            'test_mode' => true,
            'sort_order' => 1,
            'public_config' => [],
            'secret_config' => [
                'store_password' => 'secret',
            ],
            'supported_currencies' => ['BDT'],
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route(
                'admin.manage.payment-gateways.update',
                $gateway->id
            ), [
                'display_name' => 'SSLCommerz Bangladesh',
                'enabled' => '1',
                'test_mode' => '1',
                'sort_order' => 2,
                'public_config_json' => '{}',
                'secret_config_json' => '',
                'supported_currencies' => 'BDT',
            ])
            ->assertSessionHas('success');

        $fresh = $gateway->fresh();

        $this->assertSame(
            'SSLCommerz Bangladesh',
            $fresh->display_name
        );
        $this->assertTrue($fresh->enabled);
        $this->assertSame(
            'secret',
            $fresh->secret_config['store_password']
        );
    }

    public function test_non_admin_cannot_access_admin_c_pages(): void
    {
        $customer = User::factory()->create([
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->actingAs($customer, 'admin')
            ->get(route('admin.manage.seller-statements.index'))
            ->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    }

    private function admin(): User
    {
        $role = Role::findOrCreate(
            DefaultSystemRolesEnum::SUPER_ADMIN->value,
            GuardNameEnum::ADMIN->value
        );

        $admin = User::factory()->create([
            'name' => 'FastSheba Admin C',
            'email' => 'admin-c@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
