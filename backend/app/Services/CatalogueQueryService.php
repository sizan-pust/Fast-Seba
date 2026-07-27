<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogueQueryService
{
    public function storeIdsForLocation(
        float $latitude,
        float $longitude
    ): array {
        if (! DeliveryZoneService::validateCoordinates(
            $latitude,
            $longitude
        )) {
            return [];
        }

        $zoneInfo = DeliveryZoneService::getZonesAtPoint(
            $latitude,
            $longitude
        );

        $zoneId = $zoneInfo['zone_id'] ?? null;

        if (! $zoneId) {
            return [];
        }

        return Store::query()
            ->whereHas(
                'zones',
                fn ($query) => $query->where(
                    'delivery_zones.id',
                    $zoneId
                )
            )
            ->where('verification_status', 'approved')
            ->where('visibility_status', 'visible')
            ->where('status', 'online')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function products(
        float $latitude,
        float $longitude,
        array $filters,
        int $perPage
    ): LengthAwarePaginator {
        $storeIds = $this->storeIdsForLocation(
            $latitude,
            $longitude
        );

        $query = $this->availableProductsQuery(
            $storeIds,
            $filters
        );

        $this->applySorting(
            $query,
            $filters['sort'] ?? null,
            $storeIds
        );

        return $query->paginate($perPage);
    }

    public function productBySlug(
        string $slug,
        float $latitude,
        float $longitude
    ): ?Product {
        $storeIds = $this->storeIdsForLocation(
            $latitude,
            $longitude
        );

        return $this->availableProductsQuery(
            $storeIds,
            []
        )->where('slug', $slug)->first();
    }

    public function availableProductsQuery(
        array $storeIds,
        array $filters
    ): Builder {
        $query = Product::query()
            ->where('products.status', 'active')
            ->where('products.verification_status', 'approved');

        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0');
        }

        $query
            ->whereHas(
                'variants.storeProductVariants',
                function ($storeQuery) use ($storeIds): void {
                    $storeQuery
                        ->whereIn('store_id', $storeIds)
                        ->where('status', 'active')
                        ->where('stock', '>', 0);
                }
            )
            ->with([
                'category.parent',
                'brand.scopeCategory',
                'seller.owner',
                'badge',
                'variantAttributes.attribute',
                'variantAttributes.attributeValue.attribute',
                'variants' => function ($variantQuery): void {
                    $variantQuery
                        ->where('availability', true)
                        ->where('visibility', 'published');
                },
                'variants.attributes.attribute',
                'variants.attributes.attributeValue.attribute',
                'variants.storeProductVariants' => function (
                    $inventoryQuery
                ) use ($storeIds): void {
                    $inventoryQuery
                        ->whereIn('store_id', $storeIds)
                        ->where('status', 'active')
                        ->where('stock', '>', 0)
                        ->orderByRaw(
                            'COALESCE(NULLIF(special_price, 0), price) ASC'
                        );
                },
                'variants.storeProductVariants.store',
            ]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    public function categoryQueryForLocation(
        ?float $latitude,
        ?float $longitude
    ): Builder {
        $query = Category::query()
            ->where('status', 'active')
            ->with('parent')
            ->withCount('children');

        if ($latitude === null || $longitude === null) {
            return $query->withCount([
                'products as products_count' => fn ($productQuery) =>
                    $productQuery
                        ->where('status', 'active')
                        ->where('verification_status', 'approved'),
            ]);
        }

        $storeIds = $this->storeIdsForLocation(
            $latitude,
            $longitude
        );

        return $query->withCount([
            'products as products_count' => function (
                $productQuery
            ) use ($storeIds): void {
                if (empty($storeIds)) {
                    $productQuery->whereRaw('1 = 0');

                    return;
                }

                $productQuery
                    ->where('status', 'active')
                    ->where('verification_status', 'approved')
                    ->whereHas(
                        'variants.storeProductVariants',
                        fn ($inventoryQuery) => $inventoryQuery
                            ->whereIn('store_id', $storeIds)
                            ->where('status', 'active')
                            ->where('stock', '>', 0)
                    );
            },
        ]);
    }

    public function brandQueryForLocation(
        ?float $latitude,
        ?float $longitude
    ): Builder {
        $query = Brand::query()
            ->where('status', 'active')
            ->with('scopeCategory');

        if ($latitude === null || $longitude === null) {
            return $query->withCount([
                'products as products_count' => fn ($productQuery) =>
                    $productQuery
                        ->where('status', 'active')
                        ->where('verification_status', 'approved'),
            ]);
        }

        $storeIds = $this->storeIdsForLocation(
            $latitude,
            $longitude
        );

        return $query->withCount([
            'products as products_count' => function (
                $productQuery
            ) use ($storeIds): void {
                if (empty($storeIds)) {
                    $productQuery->whereRaw('1 = 0');

                    return;
                }

                $productQuery
                    ->where('status', 'active')
                    ->where('verification_status', 'approved')
                    ->whereHas(
                        'variants.storeProductVariants',
                        fn ($inventoryQuery) => $inventoryQuery
                            ->whereIn('store_id', $storeIds)
                            ->where('status', 'active')
                            ->where('stock', '>', 0)
                    );
            },
        ]);
    }

    public function storesForLocation(
        float $latitude,
        float $longitude,
        ?string $search,
        ?bool $recommended,
        int $perPage
    ): LengthAwarePaginator {
        $storeIds = $this->storeIdsForLocation(
            $latitude,
            $longitude
        );

        $query = $this->withAvailableProductCount(
            Store::query()
        )
            ->whereIn('stores.id', $storeIds)
            ->where('verification_status', 'approved')
            ->where('visibility_status', 'visible')
            ->where('status', 'online')
            ->with('zones')
            ->orderBy('name');

        if ($search) {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%');
            });
        }

        if ($recommended === true) {
            $query->where('is_recommended', true);
        }

        return $query->paginate($perPage);
    }

    public function withAvailableProductCount(
        Builder $query
    ): Builder {
        $count = StoreProductVariant::query()
            ->selectRaw(
                'COUNT(DISTINCT product_variants.product_id)'
            )
            ->join(
                'product_variants',
                'product_variants.id',
                '=',
                'store_product_variants.product_variant_id'
            )
            ->join(
                'products',
                'products.id',
                '=',
                'product_variants.product_id'
            )
            ->whereColumn(
                'store_product_variants.store_id',
                'stores.id'
            )
            ->where('store_product_variants.status', 'active')
            ->where('store_product_variants.stock', '>', 0)
            ->whereNull('store_product_variants.deleted_at')
            ->where('product_variants.availability', true)
            ->where('product_variants.visibility', 'published')
            ->whereNull('product_variants.deleted_at')
            ->where('products.status', 'active')
            ->where('products.verification_status', 'approved')
            ->whereNull('products.deleted_at');

        return $query
            ->select('stores.*')
            ->addSelect(['product_count' => $count]);
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        $categorySlugs = $this->csv(
            $filters['categories'] ?? null
        );

        if (! empty($categorySlugs)) {
            if (! empty($filters['include_child_categories'])) {
                $categoryIds = Category::query()
                    ->whereIn('slug', $categorySlugs)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $categoryIds = array_values(array_unique(array_merge(
                    $categoryIds,
                    $this->descendantCategoryIds($categoryIds)
                )));

                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery
                        ->whereIn('slug', $categorySlugs)
                );
            }
        }

        $brandSlugs = $this->csv($filters['brands'] ?? null);

        if (! empty($brandSlugs)) {
            $query->whereHas(
                'brand',
                fn ($brandQuery) => $brandQuery
                    ->whereIn('slug', $brandSlugs)
            );
        }

        $storeSlug = $filters['store'] ?? null;

        if ($storeSlug) {
            $query->whereHas(
                'variants.storeProductVariants.store',
                fn ($storeQuery) => $storeQuery
                    ->where('slug', $storeSlug)
            );
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('products.title', 'like', '%'.$search.'%')
                    ->orWhere(
                        'products.short_description',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'products.description',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhereHas(
                        'category',
                        fn ($categoryQuery) => $categoryQuery
                            ->where('title', 'like', '%'.$search.'%')
                    )
                    ->orWhereHas(
                        'brand',
                        fn ($brandQuery) => $brandQuery
                            ->where('title', 'like', '%'.$search.'%')
                    );
            });
        }

        $attributeValueIds = $this->csvInts(
            $filters['attribute_values'] ?? null
        );

        if (! empty($attributeValueIds)) {
            $query->whereHas(
                'variantAttributes',
                fn ($attributeQuery) => $attributeQuery
                    ->whereIn(
                        'global_attribute_value_id',
                        $attributeValueIds
                    )
            );
        }

        $excluded = $this->csv(
            $filters['exclude_product'] ?? null
        );

        if (! empty($excluded)) {
            $query->whereNotIn('slug', $excluded);
        }

        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }

        if (filter_var(
            $filters['recommended'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        )) {
            $query->whereNotNull('badge_id');
        }

        $query->distinct('products.id');
    }

    private function applySorting(
        Builder $query,
        ?string $sort,
        array $storeIds
    ): void {
        if (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $priceSubquery = StoreProductVariant::query()
                ->selectRaw(
                    'MIN(COALESCE(NULLIF(special_price, 0), price))'
                )
                ->join(
                    'product_variants',
                    'product_variants.id',
                    '=',
                    'store_product_variants.product_variant_id'
                )
                ->whereColumn(
                    'product_variants.product_id',
                    'products.id'
                )
                ->whereIn(
                    'store_product_variants.store_id',
                    $storeIds
                )
                ->where(
                    'store_product_variants.status',
                    'active'
                )
                ->where('store_product_variants.stock', '>', 0);

            $query->addSelect(['sort_price' => $priceSubquery])
                ->orderBy(
                    'sort_price',
                    $sort === 'price_desc' ? 'desc' : 'asc'
                );

            return;
        }

        if ($sort === 'featured') {
            $query->orderByDesc('featured')
                ->orderBy('title');

            return;
        }

        if ($sort === 'recommended') {
            $query->orderByDesc('badge_id')
                ->orderBy('title');

            return;
        }

        $query->orderBy('title');
    }

    private function csv(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map('trim', $value)
            ));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value))
        ));
    }

    private function csvInts(mixed $value): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                'intval',
                $this->csv($value)
            ),
            fn ($id) => $id > 0
        )));
    }

    private function descendantCategoryIds(array $parentIds): array
    {
        $all = [];
        $frontier = $parentIds;
        $visited = [];

        while (! empty($frontier)) {
            $frontier = array_values(array_diff(
                $frontier,
                $visited
            ));

            if (empty($frontier)) {
                break;
            }

            $visited = array_merge($visited, $frontier);

            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return array_values(array_unique($all));
    }
}