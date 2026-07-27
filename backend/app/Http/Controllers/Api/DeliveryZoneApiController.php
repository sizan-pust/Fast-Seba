<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryZoneApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DeliveryZone::query()->where('status', 'active');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $zones = $query
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery zones found.',
            ApiResponseType::responseFromPaginator(
                $zones,
                DeliveryZoneResource::collection($zones->items())->resolve()
            )
        );
    }

    public function show(int $id): JsonResponse
    {
        $zone = DeliveryZone::query()
            ->where('status', 'active')
            ->find($id);

        if (! $zone) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery zone not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery zone found.',
            new DeliveryZoneResource($zone)
        );
    }

    public function checkDelivery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        $zoneInfo = DeliveryZoneService::getZonesAtPoint(
            $latitude,
            $longitude
        );

        $isDeliverable = $zoneInfo['zone_count'] > 0;

        if ($isDeliverable && $zoneInfo['zone_id']) {
            $user = Auth::guard('sanctum')->user();

            if ($user) {
                $user->deliveryZones()->sync([$zoneInfo['zone_id']]);
            }
        }

        return ApiResponseType::sendJsonResponse(
            true,
            $isDeliverable
                ? 'Delivery is available.'
                : 'Delivery is not available.',
            [
                'is_deliverable' => $isDeliverable,
                'zone_count' => $zoneInfo['zone_count'],
                'zone' => $zoneInfo['zone']
                    ? (new DeliveryZoneResource(
                        $zoneInfo['zone']
                    ))->resolve($request)
                    : null,
                'zone_id' => $zoneInfo['zone_id'],
                'coordinates' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ]
        );
    }

    public function search(Request $request): JsonResponse
    {
        $search = (string) $request->input('search', '');

        $results = DeliveryZone::query()
            ->where('status', 'active')
            ->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (DeliveryZone $zone): array => [
                'id' => $zone->id,
                'value' => $zone->id,
                'text' => $zone->name,
            ]);

        return response()->json($results);
    }
}