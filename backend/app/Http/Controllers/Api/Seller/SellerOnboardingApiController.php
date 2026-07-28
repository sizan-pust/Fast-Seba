<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\FinanceWalletService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SellerOnboardingApiController extends Controller
{
    public function __construct(
        protected FinanceWalletService $wallets
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:32', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_license_number' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_contact_number' => ['nullable', 'string', 'max:32'],
        ]);

        [$user, $seller] = DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'access_panel' => GuardNameEnum::SELLER->value,
                'logged_in_type' => 'platform',
                'country' => 'Bangladesh',
                'iso_2' => 'BD',
                'country_code' => '+880',
            ]);

            $role = Role::findOrCreate('seller', GuardNameEnum::SELLER->value);
            $user->syncRoles([$role]);

            $seller = Seller::query()->create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'legal_name' => $data['legal_name'] ?? null,
                'trade_license_number' => $data['trade_license_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'verification_status' => 'pending',
                'visibility_status' => 'draft',
                'status' => 'active',
            ]);

            if (! empty($data['store_name'])) {
                Store::query()->create([
                    'seller_id' => $seller->id,
                    'name' => $data['store_name'],
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'contact_email' => $data['email'],
                    'contact_number' => $data['store_contact_number'] ?? $data['mobile'],
                    'status' => 'offline',
                    'verification_status' => 'pending',
                    'visibility_status' => 'draft',
                ]);
            }

            $this->wallets->wallet($user, 'seller');

            return [$user, $seller];
        });

        return response()->json([
            'success' => true,
            'message' => 'Seller registration submitted for verification.',
            'access_token' => $user->createToken('seller-api')->plainTextToken,
            'token_type' => 'Bearer',
            'data' => [
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'business_name' => $seller->business_name,
                'verification_status' => $seller->verification_status,
                'visibility_status' => $seller->visibility_status,
            ],
        ], 201);
    }

    public function profile(Request $request): JsonResponse
    {
        $seller = Seller::query()
            ->where('user_id', $request->user()->id)
            ->with('stores.zones')
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(true, 'Seller profile fetched.', [
            'user_id' => $request->user()->id,
            'seller_id' => $seller->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'mobile' => $request->user()->mobile,
            'business_name' => $seller->business_name,
            'legal_name' => $seller->legal_name,
            'verification_status' => $seller->verification_status,
            'visibility_status' => $seller->visibility_status,
            'status' => $seller->status,
            'stores' => $seller->stores->map(fn ($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'verification_status' => $store->verification_status,
            ])->values(),
        ]);
    }
}
