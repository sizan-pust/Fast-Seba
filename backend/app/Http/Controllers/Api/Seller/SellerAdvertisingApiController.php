<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Seller;
use App\Models\WalletTransaction;
use App\Services\AdvertisingService;
use App\Services\PaymentOrchestrationService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerAdvertisingApiController extends Controller
{
    public function __construct(
        protected AdvertisingService $advertising,
        protected PaymentOrchestrationService $payments
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
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising wallet fetched.',
            $this->advertising->sellerWallet(
                $request->user()
            )
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = \App\Models\Wallet::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'seller_ad')
            ->first();

        $items = $wallet
            ? WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->latest()
                ->paginate(
                    min(
                        100,
                        max(
                            1,
                            (int) $request->input('per_page', 15)
                        )
                    )
                )
            : null;

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising wallet transactions fetched.',
            $items
        );
    }

    public function topupFromEarnings(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising wallet topped up.',
            $this->advertising->topupFromSellerWallet(
                $request->user(),
                (float) $data['amount']
            )
        );
    }

    public function topupGateway(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'provider' => [
                'required',
                Rule::in([
                    'sslcommerz',
                    'stripe',
                    'razorpay',
                    'paystack',
                    'flutterwave',
                ]),
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising wallet payment initialized.',
            $this->payments->createIntent(
                $request->user(),
                $data['provider'],
                'ad_wallet',
                (float) $data['amount'],
                'BDT',
                [
                    'seller_id' =>
                        $this->seller($request)->id,
                ]
            ),
            201
        );
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaigns fetched.',
            $this->advertising->sellerCampaigns(
                $this->seller($request)
            )
        );
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $campaign = AdCampaign::query()
            ->where('seller_id', $this->seller($request)->id)
            ->with(['store', 'product', 'stats'])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign fetched.',
            $campaign
        );
    }

    public function store(Request $request): JsonResponse
    {
        $campaign = $this->advertising->saveCampaign(
            $this->seller($request),
            $request->user(),
            null,
            $request->validate($this->rules())
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign submitted.',
            $campaign,
            201
        );
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign updated.',
            $this->advertising->saveCampaign(
                $this->seller($request),
                $request->user(),
                AdCampaign::query()->findOrFail($id),
                $request->validate($this->rules(true))
            )
        );
    }

    public function pause(
        Request $request,
        int $id
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign paused.',
            $this->advertising->pause(
                $this->seller($request),
                AdCampaign::query()->findOrFail($id)
            )
        );
    }

    public function resume(
        Request $request,
        int $id
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign resumed.',
            $this->advertising->resume(
                $this->seller($request),
                AdCampaign::query()->findOrFail($id)
            )
        );
    }

    public function config(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising configuration fetched.',
            [
                'ad_types' => ['cpc', 'cpm'],
                'placements' => [
                    'home_feed',
                    'search',
                    'category',
                    'store',
                ],
                'minimum_budget' => 100,
                'minimum_bid' => 0.1,
                'currency' => 'BDT',
            ]
        );
    }

    private function rules(bool $update = false): array
    {
        return [
            'title' => [
                $update ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'ad_type' => [
                $update ? 'sometimes' : 'required',
                Rule::in(['cpc', 'cpm']),
            ],
            'placement' => [
                $update ? 'sometimes' : 'required',
                Rule::in([
                    'home_feed',
                    'search',
                    'category',
                    'store',
                ]),
            ],
            'budget' => [
                $update ? 'sometimes' : 'required',
                'numeric',
                'min:100',
            ],
            'bid_amount' => [
                $update ? 'sometimes' : 'required',
                'numeric',
                'min:0.1',
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
