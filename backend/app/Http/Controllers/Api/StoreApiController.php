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