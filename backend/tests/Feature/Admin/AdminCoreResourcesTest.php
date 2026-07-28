<?php

namespace Tests\Feature\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Category;
use App\Models\User;
use App\Services\Admin\AdminCoreResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCoreResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_every_live_resource_index(): void
    {
        $admin = $this->admin();

        foreach (AdminCoreResourceService::LIVE_MODULES as $module) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.core.'.$module.'.index'))
                ->assertOk();
        }
    }

    public function test_category_list_detail_and_state_update_work(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create([
            'title' => 'Medicine',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.core.categories.index'))
            ->assertOk()
            ->assertSee('Medicine');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.core.categories.show', $category->id))
            ->assertOk()
            ->assertSee('Medicine');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.core.categories.state', $category->id), [
                'field' => 'status',
                'value' => 'inactive',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'inactive',
        ]);
    }

    public function test_invalid_state_value_is_rejected(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create([
            'title' => 'Grocery',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.core.categories.state', $category->id), [
                'field' => 'status',
                'value' => 'deleted',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_customer_cannot_access_live_admin_resources(): void
    {
        $customer = User::factory()->create([
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        $this->actingAs($customer, 'admin')
            ->get(route('admin.core.orders.index'))
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
            'email' => 'admin-b@fastsheba.test',
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($role);

        return $admin;
    }
}
