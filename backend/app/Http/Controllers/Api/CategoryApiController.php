<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryApiController extends Controller
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'slug' => ['nullable', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:255'],
            'home' => ['nullable'],
            'include_no_product' => ['nullable'],
        ]);

        $latitude = isset($validated['latitude'])
            ? (float) $validated['latitude']
            : null;
        $longitude = isset($validated['longitude'])
            ? (float) $validated['longitude']
            : null;
        $useZoneFilter = $latitude !== null && $longitude !== null;
        $storeIds = $useZoneFilter
            ? $this->catalogue->storeIdsForLocation(
                $latitude,
                $longitude
            )
            : [];

        $query = $this->catalogue->categoryQueryForLocation(
            $latitude,
            $longitude
        );

        $parentData = null;
        $home = filter_var(
            $validated['home'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($home) {
            $query->whereNull('parent_id')
                ->where('is_home_category', true)
                ->orderBy('sort_order');
        } elseif (! empty($validated['slug'])) {
            $parent = Category::query()
                ->where('slug', $validated['slug'])
                ->where('status', 'active')
                ->first();

            if (! $parent) {
                return ApiResponseType::sendJsonResponse(
                    true,
                    'Categories fetched successfully.',
                    $this->emptyPaginator(
                        (int) ($validated['per_page'] ?? 15),
                        ['main_category_data' => []]
                    )
                );
            }

            $parentData = [
                'id' => $parent->id,
                'title' => $parent->title,
                'search_labels' => $parent->search_labels ?? [],
            ];

            $query->where('parent_id', $parent->id)
                ->orderBy('title');
        } else {
            $query->whereNull('parent_id')->orderBy('title');
        }

        if (! empty($validated['search'])) {
            $query->where(
                'title',
                'like',
                '%'.$validated['search'].'%'
            );
        }

        $categories = $query->get();

        $categories = $this->aggregateDescendantProducts(
            $categories,
            ! empty($validated['slug'])
                ? fn (Category $category): bool => true
                : fn (Category $category): bool => $category->parent_id === null,
            $useZoneFilter,
            $storeIds
        );

        $includeNoProduct = filter_var(
            $validated['include_no_product'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $includeNoProduct) {
            $categories = $this->filterNonZeroProducts($categories);
        }

        $data = $this->paginateCollection(
            $categories,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            $request
        );

        if ($parentData !== null) {
            $data['main_category_data'] = $parentData;
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Categories fetched successfully.',
            $data
        );
    }

    public function subCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'filter' => ['nullable', 'in:random,top_category'],
        ]);

        $latitude = isset($validated['latitude'])
            ? (float) $validated['latitude']
            : null;
        $longitude = isset($validated['longitude'])
            ? (float) $validated['longitude']
            : null;
        $useZoneFilter = $latitude !== null && $longitude !== null;
        $storeIds = $useZoneFilter
            ? $this->catalogue->storeIdsForLocation(
                $latitude,
                $longitude
            )
            : [];

        $query = $this->catalogue->categoryQueryForLocation(
            $latitude,
            $longitude
        )->whereNotNull('parent_id');

        if (($validated['filter'] ?? null) === 'top_category') {
            $top = Category::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->first();

            if (! $top) {
                return ApiResponseType::sendJsonResponse(
                    true,
                    'Categories fetched successfully.',
                    $this->emptyPaginator(
                        (int) ($validated['per_page'] ?? 15),
                        ['filter' => 'top_category']
                    )
                );
            }

            $query->where('parent_id', $top->id);
        }

        if (($validated['filter'] ?? null) === 'random') {
            $query->inRandomOrder();
        } else {
            $query->orderBy('title');
        }

        $categories = $this->aggregateDescendantProducts(
            $query->get(),
            fn (Category $category): bool =>
                (int) ($category->children_count ?? 0) > 0,
            $useZoneFilter,
            $storeIds
        );

        $data = $this->paginateCollection(
            $this->filterNonZeroProducts($categories),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            $request
        );
        $data['filter'] = $validated['filter'] ?? null;

        return ApiResponseType::sendJsonResponse(
            true,
            'Categories fetched successfully.',
            $data
        );
    }

    public function sidebar(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (is_string($ids)) {
            $request->merge(['ids' => explode(',', $ids)]);
        }

        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $latitude = isset($validated['latitude'])
            ? (float) $validated['latitude']
            : null;
        $longitude = isset($validated['longitude'])
            ? (float) $validated['longitude']
            : null;
        $useZoneFilter = $latitude !== null && $longitude !== null;
        $storeIds = $useZoneFilter
            ? $this->catalogue->storeIdsForLocation(
                $latitude,
                $longitude
            )
            : [];

        $query = $this->catalogue->categoryQueryForLocation(
            $latitude,
            $longitude
        );

        $requestedIds = array_values(array_unique(array_map(
            'intval',
            $validated['ids'] ?? []
        )));

        if (! empty($requestedIds)) {
            $query->whereIn('id', $requestedIds);
            $categories = $query->get()
                ->sortBy(fn (Category $category): int|false =>
                    array_search($category->id, $requestedIds, true)
                )
                ->values();
        } else {
            $categories = $query->orderBy('title')->get();
        }

        $categories = $this->aggregateDescendantProducts(
            $categories,
            fn (Category $category): bool => $category->parent_id === null,
            $useZoneFilter,
            $storeIds
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Categories fetched successfully.',
            $this->paginateCollection(
                $this->filterNonZeroProducts($categories),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 15),
                $request
            )
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Category::query()
            ->where('status', 'active')
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
            'Categories fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'value' => $item->id,
                        'text' => $item->title,
                        'image' => $item->imageUrl(),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function aggregateDescendantProducts(
        Collection $categories,
        callable $predicate,
        bool $useZoneFilter,
        array $storeIds
    ): Collection {
        return $categories->map(function (Category $category) use (
            $predicate,
            $useZoneFilter,
            $storeIds
        ): Category {
            if (! $predicate($category)) {
                return $category;
            }

            $descendantIds = $this->collectAllDescendantIds(
                [$category->id],
                [$category->id]
            );

            if (empty($descendantIds)) {
                return $category;
            }

            $query = Product::query()
                ->whereIn('category_id', $descendantIds)
                ->where('status', 'active')
                ->where('verification_status', 'approved');

            if ($useZoneFilter) {
                if (empty($storeIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas(
                        'variants.storeProductVariants',
                        fn ($inventoryQuery) => $inventoryQuery
                            ->whereIn('store_id', $storeIds)
                            ->where('status', 'active')
                            ->where('stock', '>', 0)
                    );
                }
            }

            $category->products_count =
                (int) ($category->products_count ?? 0)
                + $query->count();

            return $category;
        });
    }

    private function collectAllDescendantIds(
        array $parentIds,
        array $visited = []
    ): array {
        if (empty($parentIds)) {
            return [];
        }

        $children = Category::query()
            ->whereIn('parent_id', $parentIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newIds = array_values(array_diff($children, $visited));

        if (empty($newIds)) {
            return [];
        }

        return array_merge(
            $newIds,
            $this->collectAllDescendantIds(
                $newIds,
                array_merge($visited, $newIds)
            )
        );
    }

    private function filterNonZeroProducts(
        Collection $categories
    ): Collection {
        return $categories
            ->filter(fn (Category $category): bool =>
                (int) ($category->products_count ?? 0) > 0
            )
            ->values();
    }

    private function paginateCollection(
        Collection $categories,
        int $page,
        int $perPage,
        Request $request
    ): array {
        $total = $categories->count();
        $items = $categories
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );

        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => max(1, $paginator->lastPage()),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $items
                ->map(fn (Category $item) =>
                    (new CategoryResource($item))->resolve($request)
                )
                ->all(),
        ];
    }

    private function emptyPaginator(
        int $perPage,
        array $extra = []
    ): array {
        return array_merge([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'data' => [],
        ], $extra);
    }
}