<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerManagedProductResource;
use App\Http\Resources\SellerManagedStoreResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Services\SellerCatalogueManagementService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerManagementApiController extends Controller
{
    public function __construct(
        protected SellerCatalogueManagementService $catalogue
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        $data = [
            'seller_id' => $seller->id,
            'business_name' => $seller->business_name,
            'verification_status' => $seller->verification_status,
            'visibility_status' => $seller->visibility_status,
            'stores' => Store::query()->where('seller_id', $seller->id)->count(),
            'products' => Product::query()->where('seller_id', $seller->id)->count(),
            'pending_products' => Product::query()
                ->where('seller_id', $seller->id)
                ->where('verification_status', 'pending')
                ->count(),
            'low_stock_items' => StoreProductVariant::query()
                ->whereHas('store', fn ($query) => $query->where('seller_id', $seller->id))
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->count(),
            'order_summary' => [
                'awaiting' => $seller->id
                    ? \App\Models\SellerOrder::query()
                        ->where('seller_id', $seller->id)
                        ->where('status', 'awaiting_store_response')
                        ->count()
                    : 0,
                'preparing' => \App\Models\SellerOrder::query()
                    ->where('seller_id', $seller->id)
                    ->where('status', 'preparing')
                    ->count(),
                'ready_for_pickup' => \App\Models\SellerOrder::query()
                    ->where('seller_id', $seller->id)
                    ->where('status', 'ready_for_pickup')
                    ->count(),
            ],
        ];

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller dashboard fetched.',
            $data
        );
    }

    public function storeEnums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Store enums fetched.',
            [
                'statuses' => ['online', 'offline'],
                'verification_statuses' => [
                    'pending',
                    'approved',
                    'rejected',
                ],
                'visibility_statuses' => [
                    'draft',
                    'visible',
                    'hidden',
                ],
            ]
        );
    }

    public function stores(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());
        $items = $this->catalogue->stores(
            $seller,
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->input('search'),
            $request->input('status')
        );

        return ApiResponseType::sendJsonResponse(true, 'Seller stores fetched.', [
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
        $seller = $this->catalogue->sellerFor($request->user());

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->with('zones')
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller store fetched.',
            new SellerManagedStoreResource($store)
        );
    }

    public function createStore(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());
        $data = $request->validate($this->storeRules());

        return ApiResponseType::sendJsonResponse(
            true,
            'Store created successfully.',
            new SellerManagedStoreResource(
                $this->catalogue->store($seller, $data)
            ),
            201
        );
    }

    public function updateStore(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->catalogue->sellerFor($request->user());
        $data = $request->validate($this->storeRules(true));

        return ApiResponseType::sendJsonResponse(
            true,
            'Store updated successfully.',
            new SellerManagedStoreResource(
                $this->catalogue->updateStore($seller, $id, $data)
            )
        );
    }

    public function updateStoreStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->catalogue->sellerFor($request->user());

        $data = $request->validate([
            'status' => ['required', Rule::in(['online', 'offline'])],
        ]);

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $store->update(['status' => $data['status']]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Store status updated.',
            new SellerManagedStoreResource($store->fresh('zones'))
        );
    }

    public function deleteStore(Request $request, int $id): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        if (StoreProductVariant::query()->where('store_id', $store->id)->exists()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Store with inventory cannot be deleted.',
                [],
                422
            );
        }

        $store->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Store deleted successfully.',
            []
        );
    }

    public function products(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        $items = $this->catalogue->products(
            $seller,
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->only([
                'search',
                'type',
                'status',
                'verification_status',
                'category_id',
                'product_filter',
            ])
        );

        return ApiResponseType::sendJsonResponse(true, 'Seller products fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => SellerManagedProductResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function productEnums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true, 'Product enums fetched.', [
            'types' => ['simple', 'variant'],
            'statuses' => ['active', 'draft', 'inactive'],
            'verification_statuses' => ['pending', 'approved', 'rejected'],
            'visibility' => ['published', 'hidden'],
            'inventory_change_types' => ['add', 'remove', 'adjust'],
            'categories' => Category::query()
                ->where('status', 'active')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'requires_approval']),
            'brands' => Brand::query()
                ->where('status', 'active')
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
            'attributes' => GlobalProductAttribute::query()
                ->with('values')
                ->orderBy('title')
                ->get()
                ->map(fn ($attribute) => [
                    'id' => $attribute->id,
                    'title' => $attribute->title,
                    'label' => $attribute->label,
                    'values' => $attribute->values->map(fn ($value) => [
                        'id' => $value->id,
                        'title' => $value->title,
                    ])->values(),
                ])->values(),
        ]);
    }

    public function showProduct(Request $request, int $id): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller product fetched.',
            new SellerManagedProductResource(
                $this->catalogue->product($seller, $id)
            )
        );
    }

    public function createProduct(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());
        $data = $request->validate($this->productRules());

        return ApiResponseType::sendJsonResponse(
            true,
            'Product created successfully.',
            new SellerManagedProductResource(
                $this->catalogue->createProduct(
                    $seller,
                    $request->user(),
                    $data
                )
            ),
            201
        );
    }

    public function updateProduct(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->catalogue->sellerFor($request->user());
        $data = $request->validate($this->productRules(true));

        return ApiResponseType::sendJsonResponse(
            true,
            'Product updated successfully.',
            new SellerManagedProductResource(
                $this->catalogue->updateProduct(
                    $seller,
                    $request->user(),
                    $id,
                    $data
                )
            )
        );
    }

    public function updateProductStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->catalogue->sellerFor($request->user());
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'draft', 'inactive'])],
        ]);

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $product->update(['status' => $data['status']]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Product status updated.',
            new SellerManagedProductResource(
                $this->catalogue->product($seller, $product->id)
            )
        );
    }

    public function deleteProduct(Request $request, int $id): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $product->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Product deleted successfully.',
            []
        );
    }

    public function inventory(Request $request): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        $items = StoreProductVariant::query()
            ->whereHas('store', fn ($query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->with(['store', 'productVariant.product'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Seller inventory fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => collect($items->items())->map(fn ($inventory) => [
                'id' => $inventory->id,
                'store_id' => $inventory->store_id,
                'store_name' => $inventory->store?->name,
                'product_id' => $inventory->productVariant?->product_id,
                'product_title' => $inventory->productVariant?->product?->title,
                'variant_id' => $inventory->product_variant_id,
                'variant_title' => $inventory->productVariant?->title,
                'sku' => $inventory->sku,
                'price' => $inventory->price,
                'special_price' => $inventory->special_price,
                'cost' => $inventory->cost,
                'stock' => $inventory->stock,
                'low_stock_threshold' => $inventory->low_stock_threshold,
                'status' => $inventory->status,
            ])->values(),
        ]);
    }

    public function adjustInventory(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->catalogue->sellerFor($request->user());

        $data = $request->validate([
            'change_type' => ['required', Rule::in(['add', 'remove', 'adjust'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = $this->catalogue->adjustInventory(
            $seller,
            $request->user(),
            $id,
            $data['change_type'],
            $data['quantity'],
            $data['reason'] ?? null
        );

        return ApiResponseType::sendJsonResponse(true, 'Inventory updated.', [
            'id' => $inventory->id,
            'stock' => $inventory->stock,
        ]);
    }

    public function inventoryLogs(Request $request, int $id): JsonResponse
    {
        $seller = $this->catalogue->sellerFor($request->user());

        StoreProductVariant::query()
            ->whereHas('store', fn ($query) => $query->where('seller_id', $seller->id))
            ->findOrFail($id);

        $logs = StoreInventoryLog::query()
            ->where('store_product_variant_id', $id)
            ->with('creator')
            ->latest('created_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Inventory logs fetched.', [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'data' => collect($logs->items())->map(fn ($log) => [
                'id' => $log->id,
                'change_type' => $log->change_type,
                'quantity' => $log->quantity,
                'previous_stock' => $log->previous_stock,
                'new_stock' => $log->new_stock,
                'reason' => $log->reason,
                'created_by' => $log->creator?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    private function storeRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_email' => ['nullable', 'email'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'timing' => ['nullable', 'array'],
            'max_delivery_distance' => ['nullable', 'numeric', 'min:0'],
            'order_preparation_time' => ['nullable', 'integer', 'min:0'],
            'allows_pickup' => ['nullable', 'boolean'],
            'pickup_instructions' => ['nullable', 'string', 'max:500'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:delivery_zones,id'],
        ];
    }

    private function productRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'product_condition_id' => ['nullable', 'integer', 'exists:product_conditions,id'],
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'type' => ['nullable', Rule::in(['simple', 'variant'])],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_step_size' => ['nullable', 'integer', 'min:1'],
            'total_allowed_quantity' => ['nullable', 'integer', 'min:1'],
            'is_returnable' => ['nullable', 'boolean'],
            'returnable_days' => ['nullable', 'integer', 'min:1'],
            'is_cancelable' => ['nullable', 'boolean'],
            'cancelable_till' => ['nullable', 'string', 'max:50'],
            'requires_otp' => ['nullable', 'boolean'],
            'base_prep_time' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'draft', 'inactive'])],
            'featured' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
            'variants' => $update
                ? ['nullable', 'array']
                : ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.title' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.availability' => ['nullable', 'boolean'],
            'variants.*.visibility' => ['nullable', Rule::in(['published', 'hidden'])],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.attributes.*.attribute_id' => ['required', 'integer', 'exists:global_product_attributes,id'],
            'variants.*.attributes.*.attribute_value_id' => ['required', 'integer', 'exists:global_product_attribute_values,id'],
            'variants.*.stores' => ['required_with:variants', 'array', 'min:1'],
            'variants.*.stores.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            'variants.*.stores.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.stores.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stores.*.special_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stores.*.cost' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stores.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.stores.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.stores.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
