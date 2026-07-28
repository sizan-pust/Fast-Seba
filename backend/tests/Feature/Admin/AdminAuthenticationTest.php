<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Login to your account');
    }

    public function test_active_admin_can_login_and_logout(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Test@123456',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs(
            $admin,
            'admin'
        );

        $this->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    }

    public function test_customer_cannot_login_to_admin_panel(): void
    {
        $customer = User::factory()->create([
            'password' => Hash::make(
                'Test@123456'
            ),
            'status' => 'active',
            'access_panel' =>
                GuardNameEnum::WEB->value,
        ]);

        $this->post('/admin/login', [
            'email' => $customer->email,
            'password' => 'Test@123456',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_admin_dashboard_requires_admin_session(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_forgot_password_notifies_active_admin(): void
    {
        Notification::fake();

        $admin = $this->admin();

        $this->post('/admin/forgot-password', [
            'email' => $admin->email,
        ])->assertSessionHas('status');

        Notification::assertSentTo(
            $admin,
            AdminResetPasswordNotification::class
        );
    }

    private function admin(): User
    {
        $role = Role::findOrCreate(
            DefaultSystemRolesEnum::SUPER_ADMIN->value,
            GuardNameEnum::ADMIN->value
        );

        $admin = User::factory()->create([
            'name' => 'FastSheba Admin',
            'email' => 'admin@fastsheba.test',
            'password' => Hash::make(
                'Test@123456'
            ),
            'status' => 'active',
            'access_panel' =>
                GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
