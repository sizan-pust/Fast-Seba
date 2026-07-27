<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function __construct(
        protected CatalogueQueryService $catalogue
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'categories' => ['nullable', 'string'],
            'brands' => ['nullable', 'string'],
            'exclude_product' => ['nullable', 'string'],
            'sort' => ['nullable', 'string', 'max:50'],
            'store' => ['nullable', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:255'],
            'include_child_categories' => ['nullable'],
            'attribute_values' => ['nullable', 'string'],
            'recommended' => ['nullable'],
        ]);

        $validated['include_child_categories'] = filter_var(
            $validated['include_child_categories'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $paginator = $this->catalogue->products(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated,
            (int) ($validated['per_page'] ?? 15)
        );

        $categorySlugs = $this->csv($validated['categories'] ?? null);
        $brandSlugs = $this->csv($validated['brands'] ?? null);

        $categoryIds = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $brandIds = Brand::query()
            ->whereIn('slug', $brandSlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return ApiResponseType::sendJsonResponse(
            true,
            $paginator->total() > 0
                ? 'Products fetched successfully.'
                : 'Products not found.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'keywords' => array_values(array_filter([
                    $validated['search'] ?? null,
                ])),
                'category_ids' => $categoryIds,
                'brand_ids' => $brandIds,
                'data' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ]
        );
    }

    public function show(
        Request $request,
        string $slug
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $product = $this->catalogue->productBySlug(
            $slug,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if (! $product) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Product not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Product fetched successfully.',
            new ProductResource($product)
        );
    }

    public function storeWise(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'store_slug' => ['nullable', 'string', 'max:500'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $store = ! empty($validated['store_id'])
            ? Store::query()->find($validated['store_id'])
            : Store::query()
                ->where('slug', $validated['store_slug'] ?? '')
                ->first();

        if (! $store) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Store ID or slug is required.',
                [],
                422
            );
        }

        $query = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->whereHas(
                'variants.storeProductVariants',
                fn ($inventoryQuery) => $inventoryQuery
                    ->where('store_id', $store->id)
                    ->where('status', 'active')
                    ->where('stock', '>', 0)
            )
            ->with([
                'category.parent',
                'brand.scopeCategory',
                'seller.owner',
                'badge',
                'variantAttributes.attribute',
                'variantAttributes.attributeValue.attribute',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue.attribute',
                'variants.storeProductVariants' =>
                    fn ($inventoryQuery) => $inventoryQuery
                        ->where('store_id', $store->id)
                        ->where('status', 'active')
                        ->where('stock', '>', 0),
                'variants.storeProductVariants.store',
            ])
            ->orderBy('title');

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ]
        );
    }

    public function searchByKeywords(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'keywords' => ['required', 'string', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $groups = [];

        foreach (array_filter(
            array_map('trim', explode(',', $validated['keywords']))
        ) as $keyword) {
            $paginator = $this->catalogue->products(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                ['search' => $keyword],
                (int) ($validated['per_page'] ?? 10)
            );

            $groups[] = [
                'keyword' => $keyword,
                'total_products' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'products' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ];
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched by keywords successfully.',
            $groups
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(
                    'title',
                    'like',
                    '%'.$search.'%'
                )
            )
            ->orderBy('title')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(fn ($product) => [
                        'id' => $product->id,
                        'value' => $product->id,
                        'text' => $product->title,
                        'image' => $product->mainImageUrl(),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function csv(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value))
        ));
    }

}