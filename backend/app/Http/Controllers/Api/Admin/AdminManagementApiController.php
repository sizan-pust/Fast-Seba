<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerManagedProductResource;
use App\Http\Resources\SellerManagedStoreResource;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceWalletService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminManagementApiController extends Controller
{
    public function __construct(
        protected FinanceWalletService $wallets
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(true, 'Admin dashboard fetched.', [
            'sellers' => [
                'total' => Seller::query()->count(),
                'pending' => Seller::query()
                    ->where('verification_status', 'pending')
                    ->count(),
                'active' => Seller::query()
                    ->where('status', 'active')
                    ->count(),
            ],
            'stores' => [
                'total' => Store::query()->count(),
                'pending' => Store::query()
                    ->where('verification_status', 'pending')
                    ->count(),
                'online' => Store::query()
                    ->where('status', 'online')
                    ->count(),
            ],
            'products' => [
                'total' => Product::query()->count(),
                'pending' => Product::query()
                    ->where('verification_status', 'pending')
                    ->count(),
                'approved' => Product::query()
                    ->where('verification_status', 'approved')
                    ->count(),
            ],
            'finance' => [
                'pending_seller_withdrawals' =>
                    \App\Models\SellerWithdrawalRequest::query()
                        ->where('status', 'pending')
                        ->count(),
                'pending_rider_withdrawals' =>
                    \App\Models\DeliveryBoyWithdrawalRequest::query()
                        ->where('status', 'pending')
                        ->count(),
                'unsettled_statements' =>
                    \App\Models\SellerStatement::query()
                        ->where('settlement_status', 'unsettled')
                        ->count(),
            ],
        ]);
    }

    public function sellers(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Seller::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(fn ($subQuery) => $subQuery
                    ->where('business_name', 'like', '%'.$search.'%')
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')));
            })
            ->when($request->filled('verification_status'), fn ($query) => $query->where(
                'verification_status',
                $request->string('verification_status')->toString()
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->with(['owner', 'stores'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Admin sellers fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => collect($items->items())->map(fn ($seller) => [
                'id' => $seller->id,
                'business_name' => $seller->business_name,
                'legal_name' => $seller->legal_name,
                'commission_rate' => $seller->commission_rate,
                'verification_status' => $seller->verification_status,
                'visibility_status' => $seller->visibility_status,
                'status' => $seller->status,
                'owner' => [
                    'id' => $seller->owner?->id,
                    'name' => $seller->owner?->name,
                    'email' => $seller->owner?->email,
                    'mobile' => $seller->owner?->mobile,
                ],
                'store_count' => $seller->stores->count(),
                'created_at' => $seller->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function createSeller(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:32', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8'],
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'verification_status' => [
                'nullable',
                Rule::in(['pending', 'approved', 'rejected']),
            ],
        ]);

        $seller = DB::transaction(function () use ($data, $request): Seller {
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
                'mobile_verified_at' => $data['mobile'] ? now() : null,
            ]);

            $role = Role::findOrCreate('seller', GuardNameEnum::SELLER->value);
            $user->syncRoles([$role]);

            $seller = Seller::query()->create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'legal_name' => $data['legal_name'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? 0,
                'verification_status' => $data['verification_status'] ?? 'pending',
                'visibility_status' => ($data['verification_status'] ?? 'pending') === 'approved'
                    ? 'visible'
                    : 'draft',
                'status' => 'active',
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'verified_at' => ($data['verification_status'] ?? null) === 'approved'
                    ? now()
                    : null,
                'verified_by' => ($data['verification_status'] ?? null) === 'approved'
                    ? $request->user()->id
                    : null,
            ]);

            $this->wallets->wallet($user, 'seller');

            return $seller;
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller created successfully.',
            [
                'id' => $seller->id,
                'business_name' => $seller->business_name,
                'verification_status' => $seller->verification_status,
            ],
            201
        );
    }

    public function showSeller(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $seller = Seller::query()
            ->with(['owner', 'stores.zones'])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(true, 'Admin seller fetched.', [
            'id' => $seller->id,
            'business_name' => $seller->business_name,
            'legal_name' => $seller->legal_name,
            'trade_license_number' => $seller->trade_license_number,
            'tax_number' => $seller->tax_number,
            'address' => $seller->address,
            'city' => $seller->city,
            'commission_rate' => $seller->commission_rate,
            'verification_status' => $seller->verification_status,
            'visibility_status' => $seller->visibility_status,
            'status' => $seller->status,
            'owner' => [
                'id' => $seller->owner?->id,
                'name' => $seller->owner?->name,
                'email' => $seller->owner?->email,
                'mobile' => $seller->owner?->mobile,
            ],
            'stores' => SellerManagedStoreResource::collection($seller->stores)
                ->resolve($request),
        ]);
    }

    public function verifySeller(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'verification_status' => [
                'required',
                Rule::in(['approved', 'rejected', 'pending']),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $seller = Seller::query()->findOrFail($id);

        $metadata = $seller->metadata ?? [];
        $metadata['verification_reason'] = $data['reason'] ?? null;

        $seller->update([
            'verification_status' => $data['verification_status'],
            'visibility_status' => $data['verification_status'] === 'approved'
                ? 'visible'
                : 'draft',
            'verified_at' => $data['verification_status'] === 'approved'
                ? now()
                : null,
            'verified_by' => $request->user()->id,
            'metadata' => $metadata,
        ]);

        return ApiResponseType::sendJsonResponse(true, 'Seller verification updated.', [
            'id' => $seller->id,
            'verification_status' => $seller->verification_status,
            'visibility_status' => $seller->visibility_status,
        ]);
    }

    public function updateSellerStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        $seller = Seller::query()->findOrFail($id);
        $seller->update(['status' => $data['status']]);

        return ApiResponseType::sendJsonResponse(true, 'Seller status updated.', [
            'id' => $seller->id,
            'status' => $seller->status,
        ]);
    }

    public function stores(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Store::query()
            ->when($request->filled('seller_id'), fn ($query) => $query->where(
                'seller_id',
                $request->integer('seller_id')
            ))
            ->when($request->filled('verification_status'), fn ($query) => $query->where(
                'verification_status',
                $request->string('verification_status')->toString()
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->with(['seller.owner', 'zones'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Admin stores fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => SellerManagedStoreResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function showStore(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $store = Store::query()
            ->with(['seller.owner', 'zones'])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin store fetched.',
            new SellerManagedStoreResource($store)
        );
    }

    public function verifyStore(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'verification_status' => [
                'required',
                Rule::in(['approved', 'rejected', 'pending']),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $store = Store::query()->findOrFail($id);
        $metadata = $store->metadata ?? [];
        $metadata['verification_reason'] = $data['reason'] ?? null;

        $store->update([
            'verification_status' => $data['verification_status'],
            'visibility_status' => $data['verification_status'] === 'approved'
                ? 'visible'
                : 'draft',
            'metadata' => $metadata,
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Store verification updated.',
            new SellerManagedStoreResource($store->fresh('zones'))
        );
    }

    public function updateStoreStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['online', 'offline', 'blocked'])],
        ]);

        $store = Store::query()->findOrFail($id);
        $store->update(['status' => $data['status']]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Store status updated.',
            new SellerManagedStoreResource($store->fresh('zones'))
        );
    }

    public function recommendStore(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'is_recommended' => ['required', 'boolean'],
        ]);

        $store = Store::query()->findOrFail($id);
        $store->update(['is_recommended' => $data['is_recommended']]);

        return ApiResponseType::sendJsonResponse(true, 'Store recommendation updated.', [
            'id' => $store->id,
            'is_recommended' => (bool) $store->is_recommended,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Product::query()
            ->when($request->filled('seller_id'), fn ($query) => $query->where(
                'seller_id',
                $request->integer('seller_id')
            ))
            ->when($request->filled('verification_status'), fn ($query) => $query->where(
                'verification_status',
                $request->string('verification_status')->toString()
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->with([
                'seller.owner',
                'category',
                'categories',
                'brand',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
                'variants.storeProductVariants.store',
            ])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Admin products fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => SellerManagedProductResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function showProduct(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $product = Product::query()
            ->with([
                'seller.owner',
                'category',
                'categories',
                'brand',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
                'variants.storeProductVariants.store',
            ])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin product fetched.',
            new SellerManagedProductResource($product)
        );
    }

    public function verifyProduct(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'verification_status' => [
                'required',
                Rule::in(['approved', 'rejected', 'pending']),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::query()->findOrFail($id);
        $product->update([
            'verification_status' => $data['verification_status'],
            'rejection_reason' => $data['verification_status'] === 'rejected'
                ? ($data['reason'] ?? 'Rejected by admin')
                : null,
        ]);

        return ApiResponseType::sendJsonResponse(true, 'Product verification updated.', [
            'id' => $product->id,
            'verification_status' => $product->verification_status,
            'rejection_reason' => $product->rejection_reason,
        ]);
    }

    public function updateProductStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'draft', 'inactive'])],
        ]);

        $product = Product::query()->findOrFail($id);
        $product->update(['status' => $data['status']]);

        return ApiResponseType::sendJsonResponse(true, 'Product status updated.', [
            'id' => $product->id,
            'status' => $product->status,
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless($panel === GuardNameEnum::ADMIN->value, 403);
    }
}
