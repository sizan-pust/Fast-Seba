<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerCatalogueManagementService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected SubscriptionService $subscriptions
    ) {
    }

    public function sellerFor(User $user): Seller
    {
        return Seller::query()
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function stores(
        Seller $seller,
        int $perPage,
        ?string $search = null,
        ?string $status = null
    ): LengthAwarePaginator {
        return Store::query()
            ->where('seller_id', $seller->id)
            ->when($search, fn ($query) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('zones')
            ->latest()
            ->paginate($perPage);
    }

    public function store(
        Seller $seller,
        array $data
    ): Store {
        return DB::transaction(function () use ($seller, $data): Store {
            $eligibility = $this->subscriptions->eligibility(
                $seller,
                'stores'
            );

            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'subscription' => $eligibility['reason'],
                ]);
            }

            $zoneIds = $data['zone_ids'] ?? [];
            unset($data['zone_ids']);

            $store = Store::query()->create(array_merge($data, [
                'seller_id' => $seller->id,
                'verification_status' => 'pending',
                'visibility_status' => 'draft',
                'currency_code' => $data['currency_code'] ?? 'BDT',
            ]));

            if ($zoneIds) {
                $store->zones()->sync($zoneIds);
            }

            $this->subscriptions->consume(
                $seller,
                'stores'
            );

            return $store->fresh('zones');
        });
    }

    public function updateStore(
        Seller $seller,
        int $storeId,
        array $data
    ): Store {
        return DB::transaction(function () use ($seller, $storeId, $data): Store {
            $store = Store::query()
                ->where('seller_id', $seller->id)
                ->findOrFail($storeId);

            $zoneIds = $data['zone_ids'] ?? null;
            unset($data['zone_ids']);

            $store->update($data);

            if ($zoneIds !== null) {
                $store->zones()->sync($zoneIds);
            }

            return $store->fresh('zones');
        });
    }

    public function products(
        Seller $seller,
        int $perPage,
        array $filters
    ): LengthAwarePaginator {
        return Product::query()
            ->where('seller_id', $seller->id)
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(fn ($subQuery) => $subQuery
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%'));
            })
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when(
                $filters['verification_status'] ?? null,
                fn ($query, $value) => $query->where('verification_status', $value)
            )
            ->when(
                $filters['category_id'] ?? null,
                fn ($query, $value) => $query->where('category_id', $value)
            )
            ->when(($filters['product_filter'] ?? null) === 'featured', fn ($query) => $query->where('featured', true))
            ->when(($filters['product_filter'] ?? null) === 'low_stock', function ($query): void {
                $query->whereHas(
                    'variants.storeProductVariants',
                    fn ($inventoryQuery) => $inventoryQuery
                        ->whereColumn('stock', '<=', 'low_stock_threshold')
                        ->where('stock', '>', 0)
                );
            })
            ->when(($filters['product_filter'] ?? null) === 'out_of_stock', function ($query): void {
                $query->whereDoesntHave(
                    'variants.storeProductVariants',
                    fn ($inventoryQuery) => $inventoryQuery->where('stock', '>', 0)
                );
            })
            ->with($this->productRelations())
            ->latest()
            ->paginate($perPage);
    }

    public function product(
        Seller $seller,
        int $productId
    ): Product {
        return Product::query()
            ->where('seller_id', $seller->id)
            ->with($this->productRelations())
            ->findOrFail($productId);
    }

    public function createProduct(
        Seller $seller,
        User $actor,
        array $data
    ): Product {
        return DB::transaction(function () use ($seller, $actor, $data): Product {
            $eligibility = $this->subscriptions->eligibility(
                $seller,
                'products'
            );

            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'subscription' => $eligibility['reason'],
                ]);
            }

            $variants = $data['variants'];
            $categoryIds = $data['category_ids'] ?? [];
            unset($data['variants'], $data['category_ids']);

            $category = Category::query()->findOrFail($data['category_id']);

            if (! empty($data['brand_id'])) {
                Brand::query()->findOrFail($data['brand_id']);
            }

            $product = Product::query()->create(array_merge($data, [
                'seller_id' => $seller->id,
                'verification_status' => $category->requires_approval
                    ? 'pending'
                    : 'approved',
                'status' => $data['status'] ?? 'active',
                'type' => count($variants) > 1 ? 'variant' : ($data['type'] ?? 'simple'),
            ]));

            $product->categories()->sync(array_values(array_unique(array_merge(
                [$category->id],
                $categoryIds
            ))));

            foreach ($variants as $index => $variantData) {
                $this->upsertVariant(
                    $seller,
                    $product,
                    $actor,
                    $variantData,
                    $index === 0
                );
            }

            $this->subscriptions->consume(
                $seller,
                'products'
            );

            return $product->fresh($this->productRelations());
        });
    }

    public function updateProduct(
        Seller $seller,
        User $actor,
        int $productId,
        array $data
    ): Product {
        return DB::transaction(function () use (
            $seller,
            $actor,
            $productId,
            $data
        ): Product {
            $product = Product::query()
                ->where('seller_id', $seller->id)
                ->lockForUpdate()
                ->findOrFail($productId);

            $variants = $data['variants'] ?? null;
            $categoryIds = $data['category_ids'] ?? null;
            unset($data['variants'], $data['category_ids']);

            if (isset($data['category_id'])) {
                $category = Category::query()->findOrFail($data['category_id']);

                if ($category->requires_approval) {
                    $data['verification_status'] = 'pending';
                    $data['rejection_reason'] = null;
                }
            }

            if (! empty($data['brand_id'])) {
                Brand::query()->findOrFail($data['brand_id']);
            }

            $product->update($data);

            if ($categoryIds !== null) {
                $product->categories()->sync(array_values(array_unique(array_merge(
                    [$product->category_id],
                    $categoryIds
                ))));
            }

            if ($variants !== null) {
                foreach ($variants as $index => $variantData) {
                    $this->upsertVariant(
                        $seller,
                        $product,
                        $actor,
                        $variantData,
                        $index === 0
                    );
                }
            }

            return $product->fresh($this->productRelations());
        });
    }

    public function adjustInventory(
        Seller $seller,
        User $actor,
        int $inventoryId,
        string $changeType,
        int $quantity,
        ?string $reason
    ): StoreProductVariant {
        $inventory = StoreProductVariant::query()
            ->whereHas(
                'store',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->with(['store', 'productVariant.product'])
            ->findOrFail($inventoryId);

        return $this->inventoryService->changeStock(
            $inventory,
            $changeType,
            $quantity,
            $reason,
            $actor,
            'seller_manual_adjustment',
            $inventory->id
        );
    }

    private function upsertVariant(
        Seller $seller,
        Product $product,
        User $actor,
        array $data,
        bool $default
    ): ProductVariant {
        $variantId = $data['id'] ?? null;
        $attributes = $data['attributes'] ?? [];
        $stores = $data['stores'] ?? [];
        unset($data['id'], $data['attributes'], $data['stores']);

        if ($variantId) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->findOrFail($variantId);
            $variant->update($data);
        } else {
            $baseSlug = Str::slug($product->title.'-'.($data['title'] ?? 'variant'));
            $slug = $baseSlug ?: 'variant-'.Str::lower(Str::random(6));
            $counter = 2;

            while (ProductVariant::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            $variant = ProductVariant::query()->create(array_merge($data, [
                'product_id' => $product->id,
                'slug' => $slug,
                'is_default' => $data['is_default'] ?? $default,
                'availability' => $data['availability'] ?? true,
                'visibility' => $data['visibility'] ?? 'published',
                'provider' => $data['provider'] ?? 'self',
            ]));
        }

        foreach ($attributes as $attributeData) {
            $value = GlobalProductAttributeValue::query()
                ->where('global_attribute_id', $attributeData['attribute_id'])
                ->findOrFail($attributeData['attribute_value_id']);

            ProductVariantAttribute::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'global_attribute_id' => $attributeData['attribute_id'],
                ],
                [
                    'product_id' => $product->id,
                    'global_attribute_value_id' => $value->id,
                ]
            );
        }

        foreach ($stores as $storeData) {
            $store = Store::query()
                ->where('seller_id', $seller->id)
                ->findOrFail($storeData['store_id']);

            $inventory = StoreProductVariant::query()->updateOrCreate(
                [
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'sku' => $storeData['sku'],
                    'price' => $storeData['price'],
                    'special_price' => $storeData['special_price'] ?? null,
                    'cost' => $storeData['cost'] ?? 0,
                    'low_stock_threshold' => $storeData['low_stock_threshold'] ?? 5,
                    'status' => $storeData['status'] ?? 'active',
                ]
            );

            if (array_key_exists('stock', $storeData)) {
                $this->inventoryService->changeStock(
                    $inventory,
                    'adjust',
                    (int) $storeData['stock'],
                    'Product inventory saved',
                    $actor,
                    'product_variant',
                    $variant->id
                );
            }
        }

        return $variant->fresh([
            'attributes.attribute',
            'attributes.attributeValue',
            'storeProductVariants.store',
        ]);
    }

    private function productRelations(): array
    {
        return [
            'category',
            'categories',
            'brand',
            'variants.attributes.attribute',
            'variants.attributes.attributeValue',
            'variants.storeProductVariants.store',
        ];
    }
}
