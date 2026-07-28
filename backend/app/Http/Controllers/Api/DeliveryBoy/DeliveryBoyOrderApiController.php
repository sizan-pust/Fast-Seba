<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\DeliveryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryBoyOrderApiController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService
    ) {
    }

    public function available(
        Request $request
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        $items = $this->deliveryService
            ->availableOrders(
                $rider,
                min(
                    100,
                    max(
                        1,
                        (int) $request->input(
                            'per_page',
                            15
                        )
                    )
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            $items->total()
                ? 'Available orders fetched.'
                : 'No available orders.',
            [
                'current_page' =>
                    $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'orders' => OrderResource::collection(
                    $items->items()
                )->resolve($request),
            ]
        );
    }

    public function myOrders(
        Request $request
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        $items = $this->deliveryService->myOrders(
            $rider,
            min(
                100,
                max(
                    1,
                    (int) $request->input(
                        'per_page',
                        15
                    )
                )
            )
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Assigned orders fetched.',
            [
                'current_page' =>
                    $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'orders' => OrderResource::collection(
                    $items->items()
                )->resolve($request),
            ]
        );
    }

    public function show(
        Request $request,
        int $orderId
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Assigned order fetched.',
            new OrderResource(
                $this->deliveryService->assignedOrder(
                    $rider,
                    $orderId
                )
            )
        );
    }

    public function accept(
        Request $request,
        int $orderId
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Order accepted successfully.',
            new OrderResource(
                $this->deliveryService->acceptOrder(
                    $rider,
                    $orderId
                )
            )
        );
    }

    public function updateStatus(
        Request $request,
        int $orderId
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'picked_up',
                    'out_for_delivery',
                    'delivered',
                    'delivery_failed',
                ]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $rider = $this->deliveryService
            ->riderFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery status updated.',
            new OrderResource(
                $this->deliveryService
                    ->updateOrderStatus(
                        $rider,
                        $orderId,
                        $data['status'],
                        $data['reason'] ?? null
                    )
            )
        );
    }

    public function updateLocation(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'heading' => [
                'nullable',
                'numeric',
            ],
            'speed' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'accuracy' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $rider = $this->deliveryService
            ->riderFor($request->user());

        $location = $this->deliveryService
            ->updateLocation($rider, $data);

        return ApiResponseType::sendJsonResponse(
            true,
            'Current location updated.',
            [
                'latitude' =>
                    (float) $location->latitude,
                'longitude' =>
                    (float) $location->longitude,
                'recorded_at' =>
                    $location->recorded_at
                        ?->toIso8601String(),
            ]
        );
    }

    public function lastLocation(
        Request $request
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        $location = $rider->location;

        return ApiResponseType::sendJsonResponse(
            true,
            $location
                ? 'Last location fetched.'
                : 'Location not available.',
            $location ? [
                'latitude' =>
                    (float) $location->latitude,
                'longitude' =>
                    (float) $location->longitude,
                'recorded_at' =>
                    $location->recorded_at
                        ?->toIso8601String(),
            ] : null
        );
    }
}
