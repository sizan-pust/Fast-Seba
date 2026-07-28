<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\WalletTransaction;
use App\Services\FinanceWalletService;
use App\Services\SellerFinanceService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryBoyFinanceApiController extends Controller
{
    public function __construct(
        protected SellerFinanceService $finance,
        protected FinanceWalletService $wallets
    ) {
    }

    private function rider(Request $request): DeliveryBoy
    {
        return DeliveryBoy::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function wallet(Request $request): JsonResponse
    {
        $wallet = $this->wallets->wallet($request->user(), 'delivery_boy');

        return ApiResponseType::sendJsonResponse(true, 'Delivery wallet fetched.', [
            'id' => $wallet->id,
            'balance' => $wallet->balance,
            'blocked_balance' => $wallet->blocked_balance,
            'available_balance' => $this->wallets->availableBalance($wallet),
            'currency_code' => $wallet->currency_code,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->wallets->wallet($request->user(), 'delivery_boy');

        $items = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Delivery wallet transactions fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'amount' => $item->amount,
                'opening_balance' => $item->opening_balance,
                'closing_balance' => $item->closing_balance,
                'description' => $item->description,
                'created_at' => $item->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $items = DeliveryBoyWithdrawalRequest::query()
            ->where('delivery_boy_id', $rider->id)
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Delivery withdrawals fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => WithdrawalRequestResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function createWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery withdrawal request created.',
            new WithdrawalRequestResource(
                $this->finance->requestRiderWithdrawal(
                    $this->rider($request),
                    (float) $data['amount'],
                    $data['note'] ?? null
                )
            ),
            201
        );
    }
}
