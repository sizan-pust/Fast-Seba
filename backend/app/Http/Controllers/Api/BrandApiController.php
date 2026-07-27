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