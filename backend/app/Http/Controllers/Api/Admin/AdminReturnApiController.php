<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemReturnResource;
use App\Models\DeliveryBoy;
use App\Services\ReturnRefundService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReturnApiController extends Controller
{
    public function __construct(
        protected ReturnRefundService $returns
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        $items = $this->returns->adminReturns(
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
            'Admin return requests fetched.',
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

    public function assignRider(
        Request $request,
        int $returnId
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'delivery_boy_id' => [
                'required',
                'integer',
                'exists:delivery_boys,id',
            ],
        ]);

        $rider = DeliveryBoy::query()
            ->findOrFail(
                $data['delivery_boy_id']
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Return pickup assigned.',
            new OrderItemReturnResource(
                $this->returns->assignPickup(
                    $request->user(),
                    $returnId,
                    $rider
                )
            )
        );
    }

    public function refund(
        Request $request,
        int $returnId
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $refund = $this->returns->refund(
            $request->user(),
            $returnId,
            $data['comment'] ?? null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Refund processed to customer wallet.',
            [
                'id' => $refund->id,
                'transaction_id' =>
                    $refund->transaction_id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'method' => $refund->method,
                'status' => $refund->status,
            ]
        );
    }

    private function ensureAdmin(
        Request $request
    ): void {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless(
            $panel === GuardNameEnum::ADMIN->value,
            403
        );
    }
}
