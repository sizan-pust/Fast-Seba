<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerStatementResource;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\Seller;
use App\Models\SellerWithdrawalRequest;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\FinanceWalletService;
use App\Services\SellerFinanceService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerFinanceApiController extends Controller
{
    public function __construct(
        protected SellerFinanceService $finance,
        protected FinanceWalletService $wallets
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function wallet(Request $request): JsonResponse
    {
        $wallet = $this->wallets->wallet($request->user(), 'seller');

        return ApiResponseType::sendJsonResponse(true, 'Seller wallet fetched.', [
            'id' => $wallet->id,
            'type' => $wallet->type,
            'balance' => $wallet->balance,
            'blocked_balance' => $wallet->blocked_balance,
            'available_balance' => $this->wallets->availableBalance($wallet),
            'currency_code' => $wallet->currency_code,
        ]);
    }

    public function walletTransactions(Request $request): JsonResponse
    {
        $wallet = $this->wallets->wallet($request->user(), 'seller');

        $items = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->when($request->filled('type'), fn ($query) => $query->where(
                'type',
                $request->string('type')->toString()
            ))
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Seller wallet transactions fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'type' => $item->type,
                'amount' => $item->amount,
                'opening_balance' => $item->opening_balance,
                'closing_balance' => $item->closing_balance,
                'reference_type' => $item->reference_type,
                'reference_id' => $item->reference_id,
                'status' => $item->status,
                'description' => $item->description,
                'created_at' => $item->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function statements(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = $this->finance->sellerStatements(
            $seller,
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->input('status'),
            $request->input('direction')
        );

        return ApiResponseType::sendJsonResponse(true, 'Seller statements fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => SellerStatementResource::collection($items->items())
                ->resolve($request),
            'summary' => [
                'unsettled_credit' => \App\Models\SellerStatement::query()
                    ->where('seller_id', $seller->id)
                    ->where('settlement_status', 'unsettled')
                    ->where('direction', 'credit')
                    ->sum('amount'),
                'unsettled_debit' => \App\Models\SellerStatement::query()
                    ->where('seller_id', $seller->id)
                    ->where('settlement_status', 'unsettled')
                    ->where('direction', 'debit')
                    ->sum('amount'),
            ],
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = SellerWithdrawalRequest::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Seller withdrawals fetched.', [
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
            'Withdrawal request created.',
            new WithdrawalRequestResource(
                $this->finance->requestSellerWithdrawal(
                    $this->seller($request),
                    (float) $data['amount'],
                    $data['note'] ?? null
                )
            ),
            201
        );
    }

    public function withdrawal(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);

        $item = SellerWithdrawalRequest::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller withdrawal fetched.',
            new WithdrawalRequestResource($item)
        );
    }
}
