<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSystemViewContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_d_shared_views_use_system_routes(): void
    {
        $admin = $this->admin();

        $setting = Setting::query()->create([
            'variable' => 'admin_d_view_contract',
            'value' => [
                'enabled' => true,
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.system.settings.index'))
            ->assertOk()
            ->assertSee('admin_d_view_contract');

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.system.settings.show',
                $setting->variable
            ))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.system.settings.edit',
                $setting->variable
            ))
            ->assertOk();

        $role = Role::findOrCreate(
            'Operations Viewer',
            GuardNameEnum::ADMIN->value
        );

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.system.roles-users.show',
                [
                    'id' => $role->id,
                    'view' => 'roles',
                ]
            ))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route(
                'admin.system.roles-users.edit',
                [
                    'id' => $role->id,
                    'view' => 'roles',
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
            'name' => 'Admin D Contract Tester',
            'email' => 'admin-d-contract@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
