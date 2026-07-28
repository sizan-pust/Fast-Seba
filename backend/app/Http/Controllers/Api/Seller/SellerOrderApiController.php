<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderItemReturnResource;
use App\Http\Resources\OrderResource;
use App\Models\Seller;
use App\Services\OrderService;
use App\Services\ReturnRefundService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerOrderApiController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected ReturnRefundService $returns
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->orders->sellerOrders(
            $this->seller($request),
            min(
                100,
                max(
                    1,
                    (int) $request->input(
                        'per_page',
                        15
                    )
                )
            ),
            $request->input('status')
        );

        $data = collect($items->items())
            ->map(
                fn ($sellerOrder) => [
                    'id' => $sellerOrder->id,
                    'status' => $sellerOrder->status,
                    'delivery_type' =>
                        $sellerOrder->delivery_type,
                    'subtotal' =>
                        $sellerOrder->subtotal,
                    'commission_amount' =>
                        $sellerOrder->commission_amount,
                    'seller_earnings' =>
                        $sellerOrder->seller_earnings,
                    'store' => [
                        'id' =>
                            $sellerOrder->store->id,
                        'name' =>
                            $sellerOrder->store->name,
                        'slug' =>
                            $sellerOrder->store->slug,
                    ],
                    'order' => (
                        new OrderResource(
                            $sellerOrder->order
                        )
                    )->resolve($request),
                ]
            )
            ->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller orders fetched successfully.',
            [
                'current_page' =>
                    $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $data,
            ]
        );
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $sellerOrder = $this->orders->sellerOrder(
            $this->seller($request),
            $id
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller order fetched successfully.',
            [
                'id' => $sellerOrder->id,
                'status' => $sellerOrder->status,
                'delivery_type' =>
                    $sellerOrder->delivery_type,
                'subtotal' => $sellerOrder->subtotal,
                'order' => (
                    new OrderResource(
                        $sellerOrder->order
                    )
                )->resolve($request),
                'items' =>
                    OrderItemResource::collection(
                        $sellerOrder->items
                    )->resolve($request),
            ]
        );
    }

    public function enums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Order enums fetched successfully.',
            [
                'statuses' => [
                    'awaiting_store_response',
                    'accepted_by_seller',
                    'preparing',
                    'ready_for_pickup',
                    'rejected_by_seller',
                ],
            ]
        );
    }

    public function updateItemStatus(
        Request $request,
        int $itemId
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'accepted_by_seller',
                    'preparing',
                    'ready_for_pickup',
                    'rejected_by_seller',
                ]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $item = $this->orders->updateSellerItem(
            $request->user(),
            $this->seller($request),
            $itemId,
            $data['status'],
            $data['reason'] ?? null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Order item status updated successfully.',
            new OrderItemResource($item)
        );
    }

    public function returns(
        Request $request
    ): JsonResponse {
        $items = $this->returns->sellerReturns(
            $this->seller($request),
            min(
                100,
                max(
                    1,
                    (int) $request->input(
                        'per_page',
                        15
                    )
                )
            ),
            $request->input('status')
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller return requests fetched.',
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

    public function decideReturn(
        Request $request,
        int $returnId
    ): JsonResponse {
        $data = $request->validate([
            'decision' => [
                'required',
                Rule::in([
                    'approve',
                    'reject',
                ]),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Return request updated.',
            new OrderItemReturnResource(
                $this->returns->sellerDecision(
                    $this->seller($request),
                    $returnId,
                    $data['decision'],
                    $data['comment'] ?? null
                )
            )
        );
    }
}
