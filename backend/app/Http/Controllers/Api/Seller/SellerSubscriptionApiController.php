<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Services\PaymentOrchestrationService;
use App\Services\SubscriptionService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerSubscriptionApiController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected PaymentOrchestrationService $payments
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function plans(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription plans fetched.',
            SubscriptionPlan::query()
                ->where('status', 'active')
                ->with('limits')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function current(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Current subscription fetched.',
            $this->subscriptions->current(
                $this->seller($request)
            )
        );
    }

    public function eligibility(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'feature_key' => ['required', 'string', 'max:80'],
            'additional' => ['nullable', 'integer', 'min:0'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription eligibility checked.',
            $this->subscriptions->eligibility(
                $this->seller($request),
                $data['feature_key'],
                $data['additional'] ?? 1
            )
        );
    }

    public function buy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                'exists:subscription_plans,id',
            ],
            'payment_method' => [
                'required',
                Rule::in([
                    'wallet',
                    'sslcommerz',
                    'stripe',
                    'razorpay',
                    'paystack',
                    'flutterwave',
                ]),
            ],
        ]);

        $seller = $this->seller($request);
        $plan = SubscriptionPlan::query()
            ->with('limits')
            ->findOrFail($data['plan_id']);

        $result = $this->subscriptions->buy(
            $seller,
            $request->user(),
            $plan,
            $data['payment_method']
        );

        if ($result['requires_external_payment']) {
            $transaction = $result['transaction'];

            $intent = $this->payments->createIntent(
                $request->user(),
                $data['payment_method'],
                'subscription',
                (float) $transaction->amount,
                $transaction->currency,
                [
                    'seller_id' => $seller->id,
                    'subscription_transaction_id' =>
                        $transaction->id,
                ]
            );

            $result['payment_intent'] = $intent;
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription purchase initialized.',
            $result,
            201
        );
    }

    public function history(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription history fetched.',
            [
                'subscriptions' => SellerSubscription::query()
                    ->where('seller_id', $seller->id)
                    ->with('plan')
                    ->latest('starts_at')
                    ->paginate(
                        min(
                            100,
                            max(
                                1,
                                (int) $request->input('per_page', 15)
                            )
                        )
                    ),
                'transactions' => SubscriptionTransaction::query()
                    ->where('seller_id', $seller->id)
                    ->latest()
                    ->limit(100)
                    ->get(),
            ]
        );
    }
}
