<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemReturnResource;
use App\Services\DeliveryService;
use App\Services\ReturnRefundService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryBoyReturnPickupApiController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected ReturnRefundService $returns
    ) {
    }

    public function available(
        Request $request
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        $items = $this->returns->availablePickups(
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
            'Available return pickups fetched.',
            [
                'current_page' =>
                    $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' =>
                    OrderItemReturnResource::collection(
                        $items->items()
                    )->resolve($request),
            ]
        );
    }

    public function myPickups(
        Request $request
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        $items = $this->returns->myPickups(
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
            'Return pickups fetched.',
            [
                'current_page' =>
                    $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' =>
                    OrderItemReturnResource::collection(
                        $items->items()
                    )->resolve($request),
            ]
        );
    }

    public function accept(
        Request $request,
        int $returnId
    ): JsonResponse {
        $rider = $this->deliveryService
            ->riderFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Return pickup accepted.',
            new OrderItemReturnResource(
                $this->returns->acceptPickup(
                    $rider,
                    $returnId
                )
            )
        );
    }

    public function updateStatus(
        Request $request,
        int $returnId
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'picked_up',
                    'received_by_seller',
                ]),
            ],
        ]);

        $rider = $this->deliveryService
            ->riderFor($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Return pickup status updated.',
            new OrderItemReturnResource(
                $this->returns->updatePickup(
                    $rider,
                    $returnId,
                    $data['status']
                )
            )
        );
    }
}
