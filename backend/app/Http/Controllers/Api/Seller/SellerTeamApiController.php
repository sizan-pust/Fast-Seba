<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SellerTeamApiController extends Controller
{
    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->seller($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller permissions fetched.',
            Permission::query()
                ->where('guard_name', GuardNameEnum::SELLER->value)
                ->orderBy('name')
                ->pluck('name')
                ->values()
        );
    }

    public function roles(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $prefix = 'seller_'.$seller->id.'_';

        $roles = Role::query()
            ->where('guard_name', GuardNameEnum::SELLER->value)
            ->where('name', 'like', $prefix.'%')
            ->with('permissions')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => Str::headline(
                    Str::after($role->name, $prefix)
                ),
                'permissions' =>
                    $role->permissions->pluck('name')->values(),
            ])->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller roles fetched.',
            $roles
        );
    }

    public function createRole(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [
                'string',
                'exists:permissions,name',
            ],
        ]);

        $roleName = 'seller_'.$seller->id.'_'
            .Str::slug($data['name'], '_');

        $role = Role::findOrCreate(
            $roleName,
            GuardNameEnum::SELLER->value
        );

        $permissions = Permission::query()
            ->where('guard_name', GuardNameEnum::SELLER->value)
            ->whereIn('name', $data['permissions'])
            ->get();

        $role->syncPermissions($permissions);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller role saved.',
            [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' =>
                    $role->permissions->pluck('name')->values(),
            ],
            201
        );
    }

    public function deleteRole(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->seller($request);
        $prefix = 'seller_'.$seller->id.'_';

        $role = Role::query()
            ->where('guard_name', GuardNameEnum::SELLER->value)
            ->where('name', 'like', $prefix.'%')
            ->findOrFail($id);

        if ($role->users()->exists()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Role assigned to team members cannot be deleted.',
                [],
                422
            );
        }

        $role->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller role deleted.',
            []
        );
    }

    public function members(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $members = User::query()
            ->whereHas(
                'sellers',
                fn ($query) =>
                    $query->where('sellers.id', $seller->id)
            )
            ->with('roles')
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'mobile' => $member->mobile,
                'status' => $member->status,
                'position' => $member->sellers
                    ->firstWhere('id', $seller->id)?->pivot?->position,
                'membership_status' => $member->sellers
                    ->firstWhere('id', $seller->id)?->pivot?->status,
                'roles' => $member->roles->pluck('name')->values(),
            ])->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller team members fetched.',
            [
                'owner' => [
                    'id' => $seller->owner?->id,
                    'name' => $seller->owner?->name,
                    'email' => $seller->owner?->email,
                ],
                'members' => $members,
            ]
        );
    }

    public function createMember(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:32', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8'],
            'position' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = $this->ownedRole($seller, $data['role_id']);

        $user = DB::transaction(function () use (
            $seller,
            $data,
            $role
        ): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'access_panel' => GuardNameEnum::SELLER->value,
                'logged_in_type' => 'platform',
                'country' => 'Bangladesh',
                'iso_2' => 'BD',
                'country_code' => '+880',
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$role]);

            DB::table('seller_user')->insert([
                'seller_id' => $seller->id,
                'user_id' => $user->id,
                'position' => $data['position'] ?? null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller team member created.',
            $user->load('roles'),
            201
        );
    }

    public function updateMember(
        Request $request,
        int $userId
    ): JsonResponse {
        $seller = $this->seller($request);

        $membership = DB::table('seller_user')
            ->where('seller_id', $seller->id)
            ->where('user_id', $userId)
            ->first();

        abort_unless($membership, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                'unique:users,mobile,'.$userId,
            ],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
        ]);

        $user = User::query()->findOrFail($userId);

        DB::transaction(function () use (
            $seller,
            $user,
            $data
        ): void {
            $user->update(
                collect($data)
                    ->only(['name', 'mobile', 'status'])
                    ->all()
            );

            DB::table('seller_user')
                ->where('seller_id', $seller->id)
                ->where('user_id', $user->id)
                ->update([
                    'position' => $data['position']
                        ?? DB::raw('position'),
                    'status' => $data['status']
                        ?? DB::raw('status'),
                    'updated_at' => now(),
                ]);

            if (isset($data['role_id'])) {
                $user->syncRoles([
                    $this->ownedRole(
                        $seller,
                        $data['role_id']
                    ),
                ]);
            }
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller team member updated.',
            $user->fresh('roles')
        );
    }

    public function removeMember(
        Request $request,
        int $userId
    ): JsonResponse {
        $seller = $this->seller($request);

        abort_if($seller->user_id === $userId, 422);

        $deleted = DB::table('seller_user')
            ->where('seller_id', $seller->id)
            ->where('user_id', $userId)
            ->delete();

        abort_unless($deleted > 0, 404);

        User::query()
            ->whereKey($userId)
            ->update(['status' => 'inactive']);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller team member removed.',
            []
        );
    }

    private function ownedRole(
        Seller $seller,
        int $id
    ): Role {
        return Role::query()
            ->where('guard_name', GuardNameEnum::SELLER->value)
            ->where(
                'name',
                'like',
                'seller_'.$seller->id.'_%'
            )
            ->findOrFail($id);
    }
}
