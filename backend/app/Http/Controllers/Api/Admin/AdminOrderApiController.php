<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\DeliveryBoy;
use App\Models\Order;
use App\Services\DeliveryService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderApiController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        $orders = Order::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string(
                        'status'
                    )->toString()
                )
            )
            ->with($this->relations())
            ->latest()
            ->paginate(
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
            'Admin orders fetched.',
            [
                'current_page' =>
                    $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'data' => OrderResource::collection(
                    $orders->items()
                )->resolve($request),
            ]
        );
    }

    public function show(
        Request $request,
        int $orderId
    ): JsonResponse {
        $this->ensureAdmin($request);

        $order = Order::query()
            ->with($this->relations())
            ->findOrFail($orderId);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin order fetched.',
            new OrderResource($order)
        );
    }

    public function assignRider(
        Request $request,
        int $orderId
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
            ->with([
                'user',
                'deliveryZone',
                'location',
            ])
            ->findOrFail(
                $data['delivery_boy_id']
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery partner assigned.',
            new OrderResource(
                $this->deliveryService->acceptOrder(
                    $rider,
                    $orderId,
                    $request->user()
                )
            )
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

    private function relations(): array
    {
        return [
            'deliveryZone',
            'deliveryBoy.user',
            'deliveryBoy.location',
            'deliveryAssignments.deliveryBoy.user',
            'items.product',
            'items.variant',
            'items.store',
            'items.returnRequest.deliveryBoy.user',
            'items.returnRequest.refundTransaction',
            'paymentTransactions',
            'statusLogs',
        ];
    }
}
