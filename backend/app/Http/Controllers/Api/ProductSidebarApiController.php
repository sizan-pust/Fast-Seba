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