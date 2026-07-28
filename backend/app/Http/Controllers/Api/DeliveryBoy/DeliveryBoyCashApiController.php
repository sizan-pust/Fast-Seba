<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Http\Controllers\Controller;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyCashTransaction;
use App\Services\DeliveryCashFeedbackService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryBoyCashApiController extends Controller
{
    public function __construct(
        protected DeliveryCashFeedbackService $cash
    ) {
    }

    private function rider(Request $request): DeliveryBoy
    {
        return DeliveryBoy::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $items = DeliveryBoyCashTransaction::query()
            ->where('delivery_boy_id', $rider->id)
            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->string('status')->toString()
                    )
            )
            ->latest()
            ->paginate(
                min(
                    100,
                    max(1, (int) $request->input('per_page', 15))
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery cash transactions fetched.',
            [
                'summary' => $this->cash->riderBalance($rider),
                'transactions' => $items,
            ]
        );
    }

    public function statistics(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery cash statistics fetched.',
            $this->cash->riderBalance(
                $this->rider($request)
            )
        );
    }

    public function requestRemittance(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Cash remittance submitted.',
            $this->cash->requestRemittance(
                $this->rider($request),
                (float) $data['amount'],
                $data['reference'] ?? null,
                $data['note'] ?? null
            ),
            201
        );
    }
}
