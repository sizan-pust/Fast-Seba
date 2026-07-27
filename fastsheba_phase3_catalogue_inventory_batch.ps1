param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    $directory = Split-Path $Path -Parent

    if ($directory) {
        New-Item -ItemType Directory -Force -Path $directory |
            Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Backup-File {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$BackupRoot
    )

    if (-not (Test-Path $Path)) {
        return
    }

    $relative = $Path.Substring($BackendPath.Length).TrimStart("\")
    $destination = Join-Path $BackupRoot $relative
    $directory = Split-Path $destination -Parent

    New-Item -ItemType Directory -Force -Path $directory |
        Out-Null

    Copy-Item $Path $destination -Force
}

if (-not (Test-Path (Join-Path $BackendPath "artisan"))) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase3_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot |
    Out-Null

$apiRoutesPath = Join-Path $BackendPath "routes\api.php"
$databaseSeederPath = Join-Path $BackendPath "database\seeders\DatabaseSeeder.php"

Backup-File -Path $apiRoutesPath -BackupRoot $backupRoot
Backup-File -Path $databaseSeederPath -BackupRoot $backupRoot

$targetPath = Join-Path $BackendPath "app\Http\Controllers\Api\BrandApiController.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Category;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandApiController extends Controller
{
    public function __construct(
        protected CatalogueQueryService $catalogue
    ) {
    }

    public function index(Request $request): JsonResponse
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
            'scope_category_slug' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $query = $this->catalogue->brandQueryForLocation(
            isset($validated['latitude'])
                ? (float) $validated['latitude']
                : null,
            isset($validated['longitude'])
                ? (float) $validated['longitude']
                : null
        );

        if (! empty($validated['scope_category_slug'])) {
            $categoryId = Category::query()
                ->where('slug', $validated['scope_category_slug'])
                ->where('status', 'active')
                ->value('id');

            if ($categoryId) {
                $query->where(function ($scopeQuery) use (
                    $categoryId
                ): void {
                    $scopeQuery
                        ->where('scope_type', 'global')
                        ->orWhere(function ($categoryScope) use (
                            $categoryId
                        ): void {
                            $categoryScope
                                ->where('scope_type', 'category')
                                ->where('scope_id', $categoryId);
                        });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->where('scope_type', 'global');
        }

        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $query->having('products_count', '>', 0)
            ->orderBy('title');

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Brands fetched successfully.',
            $this->paginatorData(
                $paginator,
                fn ($item) => (new BrandResource($item))
                    ->resolve($request)
            )
        );
    }

    public function sidebar(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Brand::query()
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
            'Brands fetched successfully.',
            $this->paginatorData(
                $paginator,
                fn ($item) => [
                    'id' => $item->id,
                    'value' => $item->id,
                    'text' => $item->title,
                    'image' => $item->logoUrl(),
                ]
            )
        );
    }

    private function paginatorData(
        $paginator,
        callable $map
    ): array {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => collect($paginator->items())
                ->map($map)
                ->values()
                ->all(),
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Controllers\Api\CategoryApiController.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
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
'@

$targetPath = Join-Path $BackendPath "app\Http\Controllers\Api\ProductApiController.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
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
                'keywords' => [],
                'category_ids' => [],
                'brand_ids' => [],
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
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Controllers\Api\ProductSidebarApiController.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSidebarApiController extends Controller
{
    public function __construct(
        protected CatalogueQueryService $catalogue
    ) {
    }

    public function filters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'categories' => ['nullable', 'string'],
            'brands' => ['nullable', 'string'],
            'attribute_values' => ['nullable', 'string'],
            'type' => [
                'nullable',
                'in:category,brand,store,search,featured_section',
            ],
            'value' => ['nullable', 'string'],
        ]);

        $filters = [
            'categories' => $validated['categories'] ?? null,
            'brands' => $validated['brands'] ?? null,
            'attribute_values' =>
                $validated['attribute_values'] ?? null,
        ];

        if (! empty($validated['type']) && ! empty($validated['value'])) {
            match ($validated['type']) {
                'category' =>
                    $filters['categories'] = $validated['value'],
                'brand' => $filters['brands'] = $validated['value'],
                'store' => $filters['store'] = $validated['value'],
                'search' => $filters['search'] = $validated['value'],
                default => null,
            };
        }

        $storeIds = $this->catalogue->storeIdsForLocation(
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        $productIds = $this->catalogue
            ->availableProductsQuery($storeIds, $filters)
            ->pluck('products.id');

        if ($productIds->isEmpty()) {
            return ApiResponseType::sendJsonResponse(
                true,
                'Filters fetched successfully.',
                [
                    'categories' => [],
                    'brands' => [],
                    'attributes' => [],
                ]
            );
        }

        $categories = Category::query()
            ->whereHas(
                'products',
                fn ($query) => $query->whereIn('products.id', $productIds)
            )
            ->where('status', 'active')
            ->with('parent')
            ->withCount('children')
            ->withCount([
                'products as products_count' =>
                    fn ($query) => $query
                        ->whereIn('products.id', $productIds),
            ])
            ->orderBy('title')
            ->get()
            ->map(
                fn ($item) => (new CategoryResource($item))
                    ->resolve($request)
            )
            ->values()
            ->all();

        $brands = Brand::query()
            ->whereHas(
                'products',
                fn ($query) => $query->whereIn('products.id', $productIds)
            )
            ->where('status', 'active')
            ->with('scopeCategory')
            ->withCount([
                'products as products_count' =>
                    fn ($query) => $query
                        ->whereIn('products.id', $productIds),
            ])
            ->orderBy('title')
            ->get()
            ->map(
                fn ($item) => (new BrandResource($item))
                    ->resolve($request)
            )
            ->values()
            ->all();

        $attributes = GlobalProductAttribute::query()
            ->whereHas(
                'variantAttributes',
                fn ($query) => $query
                    ->whereIn('product_id', $productIds)
            )
            ->with([
                'values' => fn ($query) => $query->orderBy('title'),
            ])
            ->orderBy('title')
            ->get()
            ->map(fn ($attribute) => [
                'title' => $attribute->title,
                'slug' => $attribute->slug,
                'label' => $attribute->label,
                'swatche_type' => $attribute->swatche_type,
                'values' => $attribute->values
                    ->map(fn ($value) => [
                        'id' => $value->id,
                        'title' => $value->title,
                        'swatche_value' => $value->swatchValue(),
                        'enabled' => true,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return ApiResponseType::sendJsonResponse(
            true,
            'Filters fetched successfully.',
            [
                'categories' => $categories,
                'brands' => $brands,
                'attributes' => $attributes,
            ]
        );
    }

    public function getTypes(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Product types fetched successfully.',
            [
                'product_types' => ['simple', 'variant', 'digital'],
                'sort_types' => [
                    'relevance',
                    'price_asc',
                    'price_desc',
                    'featured',
                    'recommended',
                ],
            ]
        );
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Controllers\Api\StoreApiController.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StorePublicResource;
use App\Models\Store;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreApiController extends Controller
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
            'recommended' => ['nullable'],
        ]);

        $query = $this->catalogue->withAvailableProductCount(
            Store::query()
        )
            ->where('verification_status', 'approved')
            ->where('visibility_status', 'visible')
            ->where('status', 'online')
            ->with('zones')
            ->orderBy('name');

        if (filter_var(
            $validated['recommended'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        )) {
            $query->where('is_recommended', true);
        }

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 15)
        );

        return $this->response($request, $paginator);
    }

    public function location(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'search' => ['nullable', 'string', 'max:255'],
            'recommended' => ['nullable'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->catalogue->storesForLocation(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['search'] ?? null,
            array_key_exists('recommended', $validated)
                ? filter_var(
                    $validated['recommended'],
                    FILTER_VALIDATE_BOOLEAN
                )
                : null,
            (int) ($validated['per_page'] ?? 15)
        );

        return $this->response($request, $paginator);
    }

    public function show(
        Request $request,
        string $slug
    ): JsonResponse {
        $store = $this->catalogue->withAvailableProductCount(
            Store::query()
        )
            ->where('slug', $slug)
            ->where('verification_status', 'approved')
            ->where('visibility_status', 'visible')
            ->with('zones')
            ->first();

        if (! $store) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Store not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Store fetched successfully.',
            new StorePublicResource($store)
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Store::query()
            ->where('verification_status', 'approved')
            ->where('visibility_status', 'visible')
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(
                    'name',
                    'like',
                    '%'.$search.'%'
                )
            )
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return ApiResponseType::sendJsonResponse(
            true,
            'Stores fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(fn ($store) => [
                        'id' => $store->id,
                        'value' => $store->id,
                        'text' => $store->name,
                        'image' => $store->getFirstMediaUrl('store_logo'),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    public function map(Request $request): JsonResponse
    {
        return $this->location($request);
    }

    private function response(
        Request $request,
        $paginator
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Stores fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(
                        fn ($store) =>
                            (new StorePublicResource($store))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ]
        );
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\BrandResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'logo' => $this->logoUrl(),
            'status' => $this->status,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'scope_category_slug' => $this->scopeCategory?->slug ?? '',
            'scope_category_title' => $this->scopeCategory?->title ?? '',
            'description' => $this->description,
            'metadata' => $this->metadata ?? [],
            'total_products' => (int) (
                $this->products_count ?? 0
            ),
            'enabled' => (bool) ($this->enabled ?? true),
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\CategoryResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->imageUrl(),
            'banner' => '',
            'icon' => $this->iconUrl(),
            'active_icon' => $this->activeIconUrl(),
            'background_type' => $this->background_type,
            'background_color' => $this->background_color ?? '',
            'background_image' => $this->backgroundImageUrl(),
            'font_color' => $this->font_color ?? '',
            'search_labels' => $this->search_labels ?? [],
            'parent_id' => $this->parent_id,
            'commission' => $this->commission ?? '0.00',
            'parent_slug' => $this->parent?->slug,
            'description' => $this->description,
            'status' => $this->status,
            'requires_approval' => (bool) $this->requires_approval,
            'metadata' => $this->metadata ?? [],
            'subcategory_count' => (int) (
                $this->children_count ?? 0
            ),
            'product_count' => (int) (
                $this->products_count ?? 0
            ),
            'enabled' => (bool) ($this->enabled ?? true),
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\ProductListResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'seller_id' => $product->seller_id,
            'title' => $product->title,
            'slug' => $product->slug,
            'type' => $product->type,
            'short_description' => $product->short_description,
            'category' => $product->category?->slug,
            'brand' => $product->brand?->slug,
            'category_name' => $product->category?->title,
            'brand_name' => $product->brand?->title,
            'seller' => $product->seller?->owner?->name
                ?? $product->seller?->business_name
                ?? 'N/A',
            'indicator' => $product->indicator,
            'favorite' => null,
            'estimated_delivery_time' => null,
            'base_prep_time' => (int) $product->base_prep_time,
            'ratings' => 0.0,
            'rating_count' => 0,
            'main_image' => $product->mainImageUrl(),
            'image_fit' => $product->image_fit,
            'item_count_in_cart' => 0,
            'is_save_for_later' => false,
            'additional_images' => $product->additionalImageUrls(),
            'minimum_order_quantity' => (int) (
                $product->minimum_order_quantity
            ),
            'quantity_step_size' => (int) $product->quantity_step_size,
            'total_allowed_quantity' => (int) (
                $product->total_allowed_quantity
            ),
            'is_returnable' => (float) $product->is_returnable,
            'is_attachment_required' => (float) (
                $product->is_attachment_required
            ),
            'attachment_mode' => $product->attachment_mode,
            'requires_otp' => (float) $product->requires_otp,
            'tags' => $product->tags ?? [],
            'warranty_period' => $product->warranty_period,
            'guarantee_period' => $product->guarantee_period,
            'made_in' => $product->made_in,
            'is_inclusive_tax' => (bool) $product->is_inclusive_tax,
            'video_type' => $product->video_type,
            'video_link' => $product->video_link,
            'status' => $product->status,
            'featured' => $product->featured ? '1' : '0',
            'badge' => $product->badge ? [
                'id' => $product->badge->id,
                'label' => $product->badge->label,
                'bg_color' => $product->badge->bg_color,
                'text_color' => $product->badge->text_color,
                'border_color' => $product->badge->border_color,
            ] : null,
            'metadata' => $product->metadata ?? [],
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'store_status' => $this->storeStatus($product),
            'variants' => ProductVariantResource::collection(
                $product->variants
            ),
            'attributes' => $this->formattedAttributes($product),
            'is_sponsored' => false,
            'campaign_id' => null,
            'visitor_key' => null,
        ];
    }

    protected function formattedAttributes(Product $product): array
    {
        $groups = [];

        foreach ($product->variantAttributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if (! $attribute || ! $value) {
                continue;
            }

            $slug = $attribute->slug;

            $groups[$slug] ??= [
                'name' => $attribute->title,
                'slug' => $slug,
                'swatche_type' => $attribute->swatche_type,
                'values' => [],
                'swatch_values' => [],
            ];

            if (! in_array(
                $value->title,
                $groups[$slug]['values'],
                true
            )) {
                $groups[$slug]['values'][] = $value->title;
                $groups[$slug]['swatch_values'][] = [
                    'value' => $value->title,
                    'swatch' => $value->swatchValue(),
                ];
            }
        }

        return array_values($groups);
    }

    protected function storeStatus(Product $product): array
    {
        $store = $product->variants
            ->first()
            ?->storeProductVariants
            ->first()
            ?->store;

        if (! $store) {
            return [];
        }

        return [
            'is_open' => $store->status === 'online',
            'current_slot' => null,
            'next_opening_time' => '',
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\ProductResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProductResource extends ProductListResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'returnable_days' => $this->returnable_days,
            'is_cancelable' => (float) $this->is_cancelable,
            'cancelable_till' => $this->cancelable_till,
            'custom_fields' => $this->custom_fields ?? [],
            'seller_ratings' => [
                'average_rating' => 0,
                'total_reviews' => 0,
            ],
            'custom_product_sections' => [],
        ]);
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\ProductVariantResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inventory = $this->storeProductVariants->first();

        $attributes = [];

        foreach ($this->attributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if ($attribute && $value) {
                $attributes[$attribute->slug] = $value->title;
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->imageUrl(),
            'weight' => (float) ($this->weight ?? 0),
            'height' => (float) ($this->height ?? 0),
            'breadth' => (float) ($this->breadth ?? 0),
            'length' => (float) ($this->length ?? 0),
            'availability' => (bool) $this->availability,
            'cart_item' => [
                'exists' => false,
                'cart_item_id' => null,
            ],
            'barcode' => $this->barcode,
            'is_default' => (bool) $this->is_default,
            'price' => $inventory?->price,
            'special_price' => $inventory?->special_price,
            'store_id' => $inventory?->store_id,
            'store_slug' => $inventory?->store?->slug,
            'store_name' => $inventory?->store?->name,
            'stock' => $inventory?->stock,
            'sku' => $inventory?->sku,
            'attributes' => $attributes,
            'addon_groups' => [],
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Http\Resources\StorePublicResource.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => (int) (
                $this->product_count ?? 0
            ),
            'description' => $this->description,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'seller_id' => $this->seller_id,
            'tax_name' => $this->tax_name,
            'tax_number' => $this->tax_number,
            'currency_code' => $this->currency_code,
            'max_delivery_distance' => $this->max_delivery_distance,
            'order_preparation_time' => $this->order_preparation_time,
            'promotional_text' => $this->promotional_text,
            'about_us' => $this->about_us,
            'return_replacement_policy' =>
                $this->return_replacement_policy,
            'refund_policy' => $this->refund_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'delivery_policy' => $this->delivery_policy,
            'domestic_shipping_charges' =>
                $this->domestic_shipping_charges,
            'international_shipping_charges' =>
                $this->international_shipping_charges,
            'zones' => DeliveryZoneResource::collection(
                $this->whenLoaded('zones')
            ),
            'metadata' => $this->metadata ?? [],
            'fulfillment_type' => $this->fulfillment_type,
            'address' => $this->address,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'zipcode' => $this->zipcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance' => 0,
            'timing' => $this->timing,
            'logo' => $this->getFirstMediaUrl('store_logo'),
            'banner' => $this->getFirstMediaUrl('store_banner'),
            'same_location' => true,
            'avg_products_rating' => '0.00',
            'avg_store_rating' => '0.00',
            'total_store_feedback' => '0',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'is_recommended' => (bool) $this->is_recommended,
            'status' => [
                'is_open' => $this->status === 'online',
                'current_slot' => null,
                'next_opening_time' => '',
            ],
            'allows_pickup' => (bool) $this->allows_pickup,
            'pickup_instructions' => $this->pickup_instructions ?? '',
        ];
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\Badge.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Badge extends Model
{
    protected $fillable = [
        'uuid',
        'label',
        'slug',
        'bg_color',
        'text_color',
        'border_color',
        'status',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $badge): void {
            $badge->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $badge): void {
            if (! $badge->slug || $badge->isDirty('label')) {
                $badge->slug = Str::slug($badge->label);
            }
        });
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\Brand.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Brand extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'uuid',
        'scope_type',
        'scope_id',
        'title',
        'slug',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logoUrl(): string
    {
        return $this->getFirstMediaUrl('brand_logo');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brand_logo')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $brand): void {
            $brand->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $brand): void {
            if (! $brand->slug || $brand->isDirty('title')) {
                $brand->slug = self::uniqueSlug(
                    $brand->title,
                    $brand->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'brand';
        $slug = $base;
        $counter = 2;

        while (
            self::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\Category.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'slug',
        'description',
        'status',
        'requires_approval',
        'commission',
        'sort_order',
        'is_home_category',
        'background_type',
        'background_color',
        'font_color',
        'search_labels',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'commission' => 'decimal:2',
            'sort_order' => 'integer',
            'is_home_category' => 'boolean',
            'search_labels' => 'array',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function secondaryProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'category_product'
        )->withTimestamps();
    }

    public function imageUrl(): string
    {
        return $this->getFirstMediaUrl('image');
    }

    public function iconUrl(): string
    {
        return $this->getFirstMediaUrl('icon');
    }

    public function activeIconUrl(): string
    {
        return $this->getFirstMediaUrl('active_icon');
    }

    public function backgroundImageUrl(): string
    {
        return $this->getFirstMediaUrl('background_image');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('icon')->singleFile();
        $this->addMediaCollection('active_icon')->singleFile();
        $this->addMediaCollection('background_image')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $category): void {
            if (! $category->slug || $category->isDirty('title')) {
                $category->slug = self::uniqueSlug(
                    $category->title,
                    $category->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (
            self::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\GlobalProductAttribute.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GlobalProductAttribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'title',
        'slug',
        'label',
        'swatche_type',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            GlobalProductAttributeValue::class,
            'global_attribute_id'
        );
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'global_attribute_id'
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $attribute): void {
            if (! $attribute->slug || $attribute->isDirty('title')) {
                $attribute->slug = Str::slug($attribute->title);
            }
        });
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\GlobalProductAttributeValue.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GlobalProductAttributeValue extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'global_attribute_id',
        'title',
        'swatche_value',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            GlobalProductAttribute::class,
            'global_attribute_id'
        );
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'global_attribute_value_id'
        );
    }

    public function swatchValue(): ?string
    {
        if ($this->attribute?->swatche_type === 'image') {
            return $this->getFirstMediaUrl('swatche_image') ?: null;
        }

        return $this->swatche_value;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('swatche_image')->singleFile();
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\Product.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'seller_id',
        'category_id',
        'brand_id',
        'product_condition_id',
        'badge_id',
        'cloned_from_id',
        'provider',
        'provider_product_id',
        'slug',
        'title',
        'product_identity',
        'type',
        'short_description',
        'description',
        'indicator',
        'download_allowed',
        'download_link',
        'minimum_order_quantity',
        'quantity_step_size',
        'total_allowed_quantity',
        'is_inclusive_tax',
        'hsn_code',
        'is_returnable',
        'returnable_days',
        'is_cancelable',
        'cancelable_till',
        'is_attachment_required',
        'attachment_mode',
        'requires_otp',
        'base_prep_time',
        'status',
        'verification_status',
        'rejection_reason',
        'featured',
        'video_type',
        'video_link',
        'tags',
        'custom_fields',
        'warranty_period',
        'guarantee_period',
        'made_in',
        'metadata',
        'image_fit',
    ];

    protected function casts(): array
    {
        return [
            'download_allowed' => 'boolean',
            'minimum_order_quantity' => 'integer',
            'quantity_step_size' => 'integer',
            'total_allowed_quantity' => 'integer',
            'is_inclusive_tax' => 'boolean',
            'is_returnable' => 'boolean',
            'returnable_days' => 'integer',
            'is_cancelable' => 'boolean',
            'is_attachment_required' => 'boolean',
            'requires_otp' => 'boolean',
            'base_prep_time' => 'integer',
            'featured' => 'boolean',
            'tags' => 'array',
            'custom_fields' => 'array',
            'metadata' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_product'
        )->withTimestamps();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productCondition(): BelongsTo
    {
        return $this->belongsTo(ProductCondition::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default');
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }

    public function mainImageUrl(): string
    {
        return $this->getFirstMediaUrl('product_main_image');
    }

    public function additionalImageUrls(): array
    {
        return $this->getMedia('product_additional_image')
            ->map(fn ($media) => $media->getUrl())
            ->values()
            ->all();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_main_image')->singleFile();
        $this->addMediaCollection('product_additional_image');
        $this->addMediaCollection('product_video')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            $product->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $product): void {
            if (! $product->slug || $product->isDirty('title')) {
                $product->slug = self::uniqueSlug(
                    $product->title,
                    $product->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $counter = 2;

        while (
            self::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\ProductCondition.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductCondition extends Model
{
    protected $fillable = [
        'uuid',
        'category_id',
        'title',
        'slug',
        'alignment',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $condition): void {
            $condition->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $condition): void {
            if (! $condition->slug || $condition->isDirty('title')) {
                $condition->slug = Str::slug($condition->title);
            }
        });
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\ProductVariant.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'product_id',
        'title',
        'slug',
        'weight',
        'height',
        'breadth',
        'length',
        'availability',
        'provider',
        'provider_product_id',
        'provider_json',
        'barcode',
        'visibility',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'height' => 'decimal:3',
            'breadth' => 'decimal:3',
            'length' => 'decimal:3',
            'availability' => 'boolean',
            'provider_json' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'product_variant_id'
        );
    }

    public function storeProductVariants(): HasMany
    {
        return $this->hasMany(
            StoreProductVariant::class,
            'product_variant_id'
        )->orderByDesc('stock');
    }

    public function imageUrl(): string
    {
        return $this->getFirstMediaUrl('variant_image');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('variant_image')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $variant): void {
            if (! $variant->slug || $variant->isDirty('title')) {
                $base = Str::slug(
                    ($variant->product?->title ?? 'product')
                    .'-'.$variant->title
                ) ?: 'variant';

                $slug = $base;
                $counter = 2;

                while (
                    self::withTrashed()
                        ->where('slug', $slug)
                        ->when(
                            $variant->id,
                            fn ($query) => $query->where(
                                'id',
                                '!=',
                                $variant->id
                            )
                        )
                        ->exists()
                ) {
                    $slug = $base.'-'.$counter;
                    $counter++;
                }

                $variant->slug = $slug;
            }
        });
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\ProductVariantAttribute.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantAttribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'global_attribute_id',
        'global_attribute_value_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            GlobalProductAttribute::class,
            'global_attribute_id'
        );
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(
            GlobalProductAttributeValue::class,
            'global_attribute_value_id'
        );
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\StoreInventoryLog.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'store_product_variant_id',
        'product_variant_id',
        'change_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(
            StoreProductVariant::class,
            'store_product_variant_id'
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Models\StoreProductVariant.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'store_id',
        'sku',
        'price',
        'special_price',
        'cost',
        'stock',
        'low_stock_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(
            StoreInventoryLog::class,
            'store_product_variant_id'
        );
    }

    public function effectivePrice(): float
    {
        $specialPrice = $this->special_price;

        if (
            $specialPrice !== null
            && (float) $specialPrice > 0
            && (float) $specialPrice < (float) $this->price
        ) {
            return (float) $specialPrice;
        }

        return (float) $this->price;
    }
}
'@

$targetPath = Join-Path $BackendPath "app\Services\CatalogueQueryService.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
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
'@

$targetPath = Join-Path $BackendPath "app\Services\InventoryService.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace App\Services;

use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    public function changeStock(
        StoreProductVariant $inventory,
        string $changeType,
        int $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): StoreProductVariant {
        if (! in_array($changeType, ['add', 'remove', 'adjust'], true)) {
            throw new InvalidArgumentException(
                'Invalid inventory change type.'
            );
        }

        if ($quantity < 0) {
            throw new InvalidArgumentException(
                'Inventory quantity cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $inventory,
            $changeType,
            $quantity,
            $reason,
            $actor,
            $referenceType,
            $referenceId
        ): StoreProductVariant {
            $locked = StoreProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($inventory->id);

            $previousStock = (int) $locked->stock;

            $newStock = match ($changeType) {
                'add' => $previousStock + $quantity,
                'remove' => $previousStock - $quantity,
                'adjust' => $quantity,
            };

            if ($newStock < 0) {
                throw new RuntimeException(
                    'Insufficient stock for this operation.'
                );
            }

            $locked->update(['stock' => $newStock]);

            $signedQuantity = match ($changeType) {
                'add' => $quantity,
                'remove' => -$quantity,
                'adjust' => $newStock - $previousStock,
            };

            StoreInventoryLog::query()->create([
                'store_id' => $locked->store_id,
                'store_product_variant_id' => $locked->id,
                'product_variant_id' => $locked->product_variant_id,
                'change_type' => $changeType,
                'quantity' => $signedQuantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
'@

$targetPath = Join-Path $BackendPath "database\migrations\2026_07_28_030000_create_catalogue_inventory_tables.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('commission', 5, 2)->default(0);
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_home_category')->default(false)->index();
            $table->string('background_type', 20)->nullable();
            $table->string('background_color', 20)->nullable();
            $table->string('font_color', 20)->nullable();
            $table->json('search_labels')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['parent_id', 'status']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 20)->default('global')->index();
            $table->foreignId('scope_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('product_conditions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->string('alignment', 30)->default('strip');
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('label');
            $table->string('slug', 500)->unique();
            $table->string('bg_color', 20)->default('#198754');
            $table->string('text_color', 20)->default('#ffffff');
            $table->string('border_color', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('global_product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('sellers')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->string('label');
            $table->string('swatche_type', 20)->default('text');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('global_product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('global_attribute_id')
                ->constrained('global_product_attributes')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('swatche_value')->nullable();
            $table->timestamps();

            $table->unique(
                ['global_attribute_id', 'title'],
                'global_attribute_value_title_unique'
            );
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('product_condition_id')
                ->nullable()
                ->constrained('product_conditions')
                ->nullOnDelete();
            $table->foreignId('badge_id')
                ->nullable()
                ->constrained('badges')
                ->nullOnDelete();
            $table->foreignId('cloned_from_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->string('provider')->nullable();
            $table->unsignedBigInteger('provider_product_id')->nullable();
            $table->string('slug', 500)->unique();
            $table->string('title');
            $table->unsignedBigInteger('product_identity')
                ->nullable()
                ->unique();
            $table->string('type', 20)->default('simple')->index();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('indicator', 20)->nullable();

            $table->boolean('download_allowed')->default(false);
            $table->text('download_link')->nullable();

            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->unsignedInteger('quantity_step_size')->default(1);
            $table->unsignedInteger('total_allowed_quantity')->default(100);

            $table->boolean('is_inclusive_tax')->default(false);
            $table->string('hsn_code')->nullable();
            $table->boolean('is_returnable')->default(false);
            $table->unsignedInteger('returnable_days')->nullable();
            $table->boolean('is_cancelable')->default(true);
            $table->string('cancelable_till', 50)->nullable();

            $table->boolean('is_attachment_required')->default(false);
            $table->string('attachment_mode', 20)->default('required');
            $table->boolean('requires_otp')->default(false);
            $table->unsignedInteger('base_prep_time')->default(0);

            $table->string('status', 20)->default('active')->index();
            $table->string('verification_status', 30)
                ->default('approved')
                ->index();
            $table->text('rejection_reason')->nullable();
            $table->boolean('featured')->default(false)->index();

            $table->string('video_type', 30)->nullable();
            $table->text('video_link')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('warranty_period')->nullable();
            $table->string('guarantee_period')->nullable();
            $table->string('made_in')->nullable();
            $table->json('metadata')->nullable();
            $table->string('image_fit', 20)->default('contain');

            $table->softDeletes();
            $table->timestamps();

            $table->index([
                'category_id',
                'brand_id',
                'status',
                'verification_status',
            ], 'products_public_filter_index');
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('height', 10, 3)->nullable();
            $table->decimal('breadth', 10, 3)->nullable();
            $table->decimal('length', 10, 3)->nullable();
            $table->boolean('availability')->default(true)->index();
            $table->string('provider')->default('self');
            $table->string('provider_product_id')->nullable();
            $table->json('provider_json')->nullable();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('visibility', 20)->default('published')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index([
                'product_id',
                'availability',
                'visibility',
            ], 'product_variants_public_index');
        });

        Schema::create('product_variant_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('global_attribute_id')
                ->constrained('global_product_attributes')
                ->cascadeOnDelete();
            $table->foreignId('global_attribute_value_id')
                ->constrained('global_product_attribute_values')
                ->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'global_attribute_id'],
                'variant_attribute_unique'
            );
        });

        Schema::create('store_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->string('sku', 100);
            $table->decimal('price', 12, 2);
            $table->decimal('special_price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['store_id', 'product_variant_id'],
                'store_product_variant_unique'
            );
            $table->unique(['store_id', 'sku']);
            $table->index(['store_id', 'status', 'stock']);
        });

        Schema::create('store_inventory_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->foreignId('store_product_variant_id')
                ->constrained('store_product_variants')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->string('change_type', 20);
            $table->integer('quantity');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'store_product_variant_id',
                'created_at',
            ], 'inventory_variant_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_logs');
        Schema::dropIfExists('store_product_variants');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('global_product_attribute_values');
        Schema::dropIfExists('global_product_attributes');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('product_conditions');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
'@

$targetPath = Join-Path $BackendPath "database\seeders\CatalogueInventorySeeder.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace Database\Seeders;

use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\Badge;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductCondition;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CatalogueInventorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedPermissions();

            $sellerRole = Role::findOrCreate(
                'seller',
                GuardNameEnum::SELLER->value
            );

            $sellerUser = User::query()->updateOrCreate(
                ['email' => 'seller@fastsheba.test'],
                [
                    'name' => 'FastSheba Demo Seller',
                    'mobile' => '01700000001',
                    'country_code' => '+880',
                    'country' => 'Bangladesh',
                    'iso_2' => 'BD',
                    'password' => env(
                        'CATALOGUE_SELLER_PASSWORD',
                        'Test@123456'
                    ),
                    'status' => 'active',
                    'access_panel' => GuardNameEnum::SELLER->value,
                    'logged_in_type' =>
                        UserLoginTypeEnum::PLATFORM->value,
                    'email_verified_at' => now(),
                    'mobile_verified_at' => now(),
                ]
            );

            $sellerUser->syncRoles([$sellerRole]);

            $seller = Seller::query()->updateOrCreate(
                ['user_id' => $sellerUser->id],
                [
                    'business_name' => 'FastSheba Demo Pharmacy',
                    'legal_name' => 'FastSheba Demo Pharmacy',
                    'address' => 'Dhaka, Bangladesh',
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'country_code' => '+880',
                    'commission_rate' => 10,
                    'verification_status' => 'approved',
                    'visibility_status' => 'visible',
                    'status' => 'active',
                    'verified_at' => now(),
                ]
            );

            $store = Store::query()->firstOrNew([
                'slug' => 'fastsheba-demo-pharmacy',
            ]);

            $store->fill([
                'seller_id' => $seller->id,
                'name' => 'FastSheba Demo Pharmacy',
                'slug' => 'fastsheba-demo-pharmacy',
                'address' => 'Dhaka, Bangladesh',
                'city' => 'Dhaka',
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'contact_email' => 'seller@fastsheba.test',
                'contact_number' => '01700000001',
                'description' => 'Demo pharmacy for local development.',
                'currency_code' => 'BDT',
                'status' => 'online',
                'verification_status' => 'approved',
                'visibility_status' => 'visible',
                'is_recommended' => true,
                'fulfillment_type' => 'hyperlocal',
                'allows_pickup' => true,
                'pickup_instructions' =>
                    'Bring the order confirmation.',
            ]);

            $store->save();

            $zone = DeliveryZone::query()
                ->where('slug', 'dhaka-test-zone')
                ->first();

            if ($zone) {
                $store->zones()->syncWithoutDetaching([$zone->id]);
            }

            $root = Category::query()->updateOrCreate(
                ['slug' => 'pharmacy'],
                [
                    'title' => 'Pharmacy',
                    'description' => 'Medicine and healthcare products.',
                    'status' => 'active',
                    'requires_approval' => true,
                    'commission' => 10,
                    'sort_order' => 1,
                    'is_home_category' => true,
                    'background_type' => 'color',
                    'background_color' => '#E8F5E9',
                    'font_color' => '#1B5E20',
                    'search_labels' => [
                        'medicine',
                        'healthcare',
                        'pharmacy',
                    ],
                    'metadata' => [],
                ]
            );

            $category = Category::query()->updateOrCreate(
                ['slug' => 'otc-medicines'],
                [
                    'parent_id' => $root->id,
                    'title' => 'OTC Medicines',
                    'description' => 'Over-the-counter medicines.',
                    'status' => 'active',
                    'requires_approval' => true,
                    'commission' => 10,
                    'sort_order' => 1,
                    'is_home_category' => false,
                    'search_labels' => [
                        'tablet',
                        'pain relief',
                    ],
                    'metadata' => [],
                ]
            );

            $brand = Brand::query()->updateOrCreate(
                ['slug' => 'fastsheba-generic'],
                [
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'title' => 'FastSheba Generic',
                    'description' =>
                        'Generic demo brand for development.',
                    'status' => 'active',
                    'metadata' => [],
                ]
            );

            $condition = ProductCondition::query()->updateOrCreate(
                ['slug' => 'new'],
                [
                    'category_id' => $category->id,
                    'title' => 'New',
                    'alignment' => 'strip',
                ]
            );

            $badge = Badge::query()->updateOrCreate(
                ['slug' => 'popular'],
                [
                    'label' => 'Popular',
                    'bg_color' => '#198754',
                    'text_color' => '#ffffff',
                    'border_color' => '#198754',
                    'status' => 'active',
                ]
            );

            $attribute = GlobalProductAttribute::query()
                ->updateOrCreate(
                    [
                        'seller_id' => $seller->id,
                        'slug' => 'pack-size',
                    ],
                    [
                        'title' => 'Pack Size',
                        'label' => 'Pack Size',
                        'swatche_type' => 'text',
                    ]
                );

            $attributeValue = GlobalProductAttributeValue::query()
                ->updateOrCreate(
                    [
                        'global_attribute_id' => $attribute->id,
                        'title' => '10 Tablets',
                    ],
                    ['swatche_value' => '10 Tablets']
                );

            $product = Product::query()->firstOrNew([
                'slug' => 'paracetamol-500-mg-tablet',
            ]);

            $product->fill([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'product_condition_id' => $condition->id,
                'badge_id' => $badge->id,
                'slug' => 'paracetamol-500-mg-tablet',
                'title' => 'Paracetamol 500 mg Tablet',
                'type' => 'variant',
                'short_description' =>
                    'Common pain and fever relief medicine.',
                'description' =>
                    'Demo catalogue item for local development only.',
                'minimum_order_quantity' => 1,
                'quantity_step_size' => 1,
                'total_allowed_quantity' => 20,
                'is_inclusive_tax' => true,
                'is_returnable' => false,
                'is_cancelable' => true,
                'is_attachment_required' => false,
                'attachment_mode' => 'optional',
                'requires_otp' => false,
                'base_prep_time' => 5,
                'status' => 'active',
                'verification_status' => 'approved',
                'featured' => true,
                'tags' => [
                    'paracetamol',
                    'pain relief',
                    'fever',
                ],
                'custom_fields' => [
                    'generic_name' => 'Paracetamol',
                    'strength' => '500 mg',
                    'dosage_form' => 'Tablet',
                ],
                'made_in' => 'Bangladesh',
                'metadata' => [],
                'image_fit' => 'contain',
            ]);

            $product->save();
            $product->categories()->syncWithoutDetaching([
                $category->id,
            ]);

            $variant = ProductVariant::query()->firstOrNew([
                'slug' => 'paracetamol-500-mg-tablet-10-tablets',
            ]);

            $variant->fill([
                'product_id' => $product->id,
                'title' => '10 Tablets',
                'slug' =>
                    'paracetamol-500-mg-tablet-10-tablets',
                'weight' => 0.020,
                'availability' => true,
                'provider' => 'self',
                'barcode' => 'FS-PARA-500-10',
                'visibility' => 'published',
                'is_default' => true,
            ]);

            $variant->save();

            ProductVariantAttribute::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'global_attribute_id' => $attribute->id,
                ],
                [
                    'product_id' => $product->id,
                    'global_attribute_value_id' =>
                        $attributeValue->id,
                ]
            );

            $inventory = StoreProductVariant::query()
                ->updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'sku' => 'FS-PARA-500-10',
                        'price' => 20,
                        'special_price' => 18,
                        'cost' => 15,
                        'stock' => 100,
                        'low_stock_threshold' => 10,
                        'status' => 'active',
                    ]
                );

            StoreInventoryLog::query()->firstOrCreate(
                [
                    'store_product_variant_id' => $inventory->id,
                    'reason' => 'Initial catalogue seed',
                ],
                [
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'change_type' => 'add',
                    'quantity' => 100,
                    'previous_stock' => 0,
                    'new_stock' => 100,
                    'created_by' => $sellerUser->id,
                    'created_at' => now(),
                ]
            );
        });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'catalogue.view',
            'catalogue.manage',
            'inventory.view',
            'inventory.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                GuardNameEnum::ADMIN->value
            );
        }

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', GuardNameEnum::ADMIN->value)
            ->first();

        $superAdmin?->givePermissionTo($permissions);
    }
}
'@

$targetPath = Join-Path $BackendPath "database\seeders\DatabaseSeeder.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            AuthProviderSeeder::class,
            CatalogueInventorySeeder::class,
        ]);
    }
}
'@

$targetPath = Join-Path $BackendPath "routes\catalogue.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

use App\Http\Controllers\Api\BrandApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductSidebarApiController;
use App\Http\Controllers\Api\StoreApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->name('categories.')->group(
    function (): void {
        Route::get('/', [CategoryApiController::class, 'index']);
        Route::get(
            'sub-categories',
            [CategoryApiController::class, 'subCategories']
        );
        Route::get(
            'sidebar',
            [CategoryApiController::class, 'sidebar']
        );
        Route::get(
            'search',
            [CategoryApiController::class, 'search']
        )->name('search');
    }
);

Route::prefix('brands')->name('brands.')->group(
    function (): void {
        Route::get('/', [BrandApiController::class, 'index']);
        Route::get(
            'sidebar',
            [BrandApiController::class, 'sidebar']
        );
        Route::get(
            'search',
            [BrandApiController::class, 'search']
        )->name('search');
    }
);

Route::prefix('products')->name('products.')->group(
    function (): void {
        Route::get(
            'sidebar-filters',
            [ProductSidebarApiController::class, 'filters']
        );
        Route::get(
            'get-types',
            [ProductSidebarApiController::class, 'getTypes']
        );
        Route::get(
            'search-by-keywords',
            [ProductApiController::class, 'searchByKeywords']
        );
        Route::get(
            'store-wise',
            [ProductApiController::class, 'storeWise']
        );
        Route::get(
            'search',
            [ProductApiController::class, 'search']
        )->name('search');
        Route::get(
            '{slug}',
            [ProductApiController::class, 'show']
        );
    }
);

Route::prefix('stores')->name('stores.')->group(
    function (): void {
        Route::get('/', [StoreApiController::class, 'index']);
        Route::get(
            'search',
            [StoreApiController::class, 'search']
        )->name('search');
        Route::get(
            '{slug}',
            [StoreApiController::class, 'show']
        );
    }
);

Route::post(
    'stores/map',
    [StoreApiController::class, 'map']
);

Route::get(
    'delivery-zone/products',
    [ProductApiController::class, 'index']
);

Route::get(
    'delivery-zone/stores',
    [StoreApiController::class, 'location']
);
'@

$targetPath = Join-Path $BackendPath "tests\Feature\Api\CatalogueInventoryApiTest.php"
Backup-File -Path $targetPath -BackupRoot $backupRoot
Write-Utf8NoBom -Path $targetPath -Content @'
<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\StoreProductVariant;
use App\Services\InventoryService;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CatalogueInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
    }

    public function test_catalogue_tables_and_seed_data_exist(): void
    {
        $this->assertDatabaseHas('categories', [
            'slug' => 'pharmacy',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('products', [
            'slug' => 'paracetamol-500-mg-tablet',
            'verification_status' => 'approved',
        ]);

        $this->assertDatabaseHas('store_product_variants', [
            'sku' => 'FS-PARA-500-10',
            'stock' => 100,
        ]);
    }

    public function test_home_categories_are_flutter_compatible(): void
    {
        $this->getJson(
            '/api/categories'
            .'?home=true'
            .'&latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'pharmacy')
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'data' => [[
                        'id',
                        'title',
                        'slug',
                        'image',
                        'search_labels',
                        'subcategory_count',
                        'product_count',
                    ]],
                ],
            ]);
    }

    public function test_brands_are_filtered_by_available_products(): void
    {
        $this->getJson(
            '/api/brands'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.data.0.slug',
                'fastsheba-generic'
            );
    }

    public function test_location_product_listing_and_detail_work(): void
    {
        $list = $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        );

        $list
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.slug',
                'paracetamol-500-mg-tablet'
            )
            ->assertJsonPath(
                'data.data.0.variants.0.stock',
                100
            )
            ->assertJsonPath(
                'data.data.0.variants.0.special_price',
                '18.00'
            );

        $this->getJson(
            '/api/products/paracetamol-500-mg-tablet'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.custom_fields.generic_name',
                'Paracetamol'
            )
            ->assertJsonPath(
                'data.attributes.0.slug',
                'pack-size'
            );
    }

    public function test_product_filters_and_store_location_work(): void
    {
        $this->getJson(
            '/api/products/sidebar-filters'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.attributes.0.slug',
                'pack-size'
            );

        $this->getJson(
            '/api/delivery-zone/stores'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.data.0.slug',
                'fastsheba-demo-pharmacy'
            );
    }

    public function test_outside_zone_returns_empty_products(): void
    {
        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_inventory_service_is_atomic_and_logs_changes(): void
    {
        $inventory = StoreProductVariant::query()->firstOrFail();
        $service = app(InventoryService::class);

        $inventory = $service->changeStock(
            $inventory,
            'remove',
            5,
            'Test order allocation'
        );

        $this->assertSame(95, $inventory->stock);

        $this->assertDatabaseHas('store_inventory_logs', [
            'store_product_variant_id' => $inventory->id,
            'change_type' => 'remove',
            'quantity' => -5,
            'previous_stock' => 100,
            'new_stock' => 95,
        ]);

        try {
            $service->changeStock(
                $inventory,
                'remove',
                500,
                'Should fail'
            );

            $this->fail('Expected insufficient stock exception.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('store_product_variants', [
                'id' => $inventory->id,
                'stock' => 95,
            ]);
        }
    }

    public function test_price_sorting_and_category_filter_work(): void
    {
        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&categories=pharmacy'
            .'&include_child_categories=1'
            .'&sort=price_asc'
        )
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.slug',
                'paracetamol-500-mg-tablet'
            );
    }
}
'@

$apiRoutes = [System.IO.File]::ReadAllText($apiRoutesPath)
$catalogueRequire = "require __DIR__.'/catalogue.php';"

if (-not $apiRoutes.Contains($catalogueRequire)) {
    $deliveryMarker = "Route::prefix('delivery-zone')"
    $markerIndex = $apiRoutes.IndexOf($deliveryMarker)

    if ($markerIndex -lt 0) {
        throw "Delivery-zone route marker was not found in routes/api.php."
    }

    $apiRoutes = $apiRoutes.Insert(
        $markerIndex,
        $catalogueRequire + [Environment]::NewLine + [Environment]::NewLine
    )

    Write-Utf8NoBom -Path $apiRoutesPath -Content $apiRoutes
}

Write-Host ""
Write-Host "Phase 3 catalogue and inventory batch written." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next commands:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate"
Write-Host "  herd php artisan db:seed --class=CatalogueInventorySeeder"
Write-Host "  herd php artisan route:list --path=api/categories"
Write-Host "  herd php artisan route:list --path=api/brands"
Write-Host "  herd php artisan route:list --path=api/products"
Write-Host "  herd php artisan route:list --path=api/delivery-zone"
Write-Host "  herd php artisan test --filter=CatalogueInventoryApiTest"
Write-Host "  herd php artisan test"
