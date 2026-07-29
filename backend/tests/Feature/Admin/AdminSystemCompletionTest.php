<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminSystemCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSystemCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_every_admin_d_module(): void
    {
        $admin = $this->admin();

        foreach (AdminSystemCompletionService::LIVE_MODULES as $module) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.system.'.$module.'.index'))
                ->assertOk();
        }
    }

    public function test_setting_group_crud_works(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.system.settings.store'), [
                'variable' => 'admin_d_test',
                'value_json' => json_encode(['enabled' => true]),
            ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['variable' => 'admin_d_test']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.system.settings.update', 'admin_d_test'), [
                'variable' => 'admin_d_test',
                'value_json' => json_encode(['enabled' => false]),
            ])->assertSessionHas('success');

        $this->assertFalse(
            Setting::query()->where('variable', 'admin_d_test')
                ->firstOrFail()->value['enabled']
        );

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.system.settings.destroy', 'admin_d_test'))
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['variable' => 'admin_d_test']);
    }

    public function test_role_is_created_with_admin_guard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.system.roles-users.store', ['view' => 'roles']), [
                'view' => 'roles',
                'name' => 'Operations Auditor',
                'permission_ids' => [],
            ])->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'Operations Auditor',
            'guard_name' => GuardNameEnum::ADMIN->value,
        ]);
    }

    public function test_non_admin_is_blocked(): void
    {
        $customer = User::factory()->create([
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->actingAs($customer, 'admin')
            ->get(route('admin.system.system-operations.index'))
            ->assertRedirect('/admin/login');
    }

    private function admin(): User
    {
        $role = Role::findOrCreate(
            DefaultSystemRolesEnum::SUPER_ADMIN->value,
            GuardNameEnum::ADMIN->value
        );

        $admin = User::factory()->create([
            'name' => 'FastSheba Admin D',
            'email' => 'admin-d@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
