<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15));

        $items = collect($paginator->items());

        if (! empty($validated['zone_id'])) {
            $zoneId = (int) $validated['zone_id'];
            $items = $items->filter(function (Address $address) use (
                $zoneId
            ): bool {
                $info = DeliveryZoneService::getZonesAtPoint(
                    (float) $address->latitude,
                    (float) $address->longitude
                );

                return (int) ($info['zone_id'] ?? 0) === $zoneId;
            })->values();
        }

        $data = $paginator->toArray();
        $data['data'] = $items
            ->map(
                fn (Address $address) =>
                    (new AddressResource($address))->resolve($request)
            )
            ->all();
        $data['total'] = count($data['data']);

        return ApiResponseType::sendJsonResponse(
            true,
            'Addresses fetched successfully.',
            $data
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);

        if (! DeliveryZoneService::existsAtPoint(
            (float) $validated['latitude'],
            (float) $validated['longitude']
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery is not available at this address.',
                [],
                422
            );
        }

        $address = DB::transaction(function () use (
            $request,
            $validated
        ): Address {
            if (! empty($validated['is_default'])) {
                Address::query()
                    ->where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            return Address::query()->create(array_merge(
                $validated,
                ['user_id' => $request->user()->id]
            ));
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Address created successfully.',
            new AddressResource($address),
            201
        );
    }

    public function show(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Address fetched successfully.',
            new AddressResource($address)
        );
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);
        $validated = $this->validateAddress($request, true);

        $latitude = (float) (
            $validated['latitude'] ?? $address->latitude
        );
        $longitude = (float) (
            $validated['longitude'] ?? $address->longitude
        );

        if (! DeliveryZoneService::existsAtPoint(
            $latitude,
            $longitude
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery is not available at this address.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $request,
            $address,
            $validated
        ): void {
            if (! empty($validated['is_default'])) {
                Address::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Address updated successfully.',
            new AddressResource($address->fresh())
        );
    }

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            Address::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->first()
                ?->update(['is_default' => true]);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Address deleted successfully.',
            []
        );
    }

    private function validateAddress(
        Request $request,
        bool $partial = false
    ): array {
        $prefix = $partial ? 'sometimes|' : '';

        return $request->validate([
            'address_line1' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'mobile' => [$partial ? 'sometimes' : 'required', 'string', 'max:32'],
            'address_type' => [$partial ? 'sometimes' : 'required', 'in:home,work,other'],
            'country' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'country_code' => [$partial ? 'sometimes' : 'required', 'string', 'max:10'],
            'latitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'longitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function ownedAddress(
        Request $request,
        string $id
    ): Address {
        $address = Address::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        abort_if(! $address, 404, 'Address not found.');

        return $address;
    }
}