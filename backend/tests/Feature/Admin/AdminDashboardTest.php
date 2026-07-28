<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_parity_dashboard_and_navigation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Welcome Back')
            ->assertSee('Revenue vs Orders')
            ->assertSee('Enhanced Commissions')
            ->assertSee('Catalog')
            ->assertSee('Communication')
            ->assertSee('System');
    }

    public function test_unfinished_module_placeholder_is_available(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/module/support')
            ->assertOk()
            ->assertSee('Support Tickets')
            ->assertSee('The visual shell is ready');
    }

    public function test_non_admin_session_is_rejected(): void
    {
        $customer = User::factory()->create([
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->actingAs($customer, 'admin')
            ->get('/admin/dashboard')
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
            'name' => 'FastSheba Admin',
            'email' => 'dashboard-b@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
