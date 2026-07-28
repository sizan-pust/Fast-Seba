<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\PaymentIntent;
use App\Models\Seller;
use App\Models\SubscriptionTransaction;
use App\Services\PaymentOrchestrationService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentIntentApiController extends Controller
{
    public function __construct(
        protected PaymentOrchestrationService $payments
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
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
            'purpose' => [
                'required',
                Rule::in(['order', 'subscription', 'ad_wallet']),
            ],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'subscription_transaction_id' => [
                'nullable',
                'integer',
                'exists:subscription_transactions,id',
            ],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);

        [$amount, $context] = $this->resolvePurpose(
            $request,
            $data
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Payment intent created.',
            $this->payments->createIntent(
                $request->user(),
                $data['provider'],
                $data['purpose'],
                $amount,
                $data['currency'] ?? 'BDT',
                $context
            ),
            201
        );
    }

    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $intent = PaymentIntent::query()
            ->where('uuid', $uuid)
            ->where(function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id)
                    ->orWhereHas(
                        'order',
                        fn ($orderQuery) =>
                            $orderQuery->where(
                                'user_id',
                                $request->user()->id
                            )
                    );
            })
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Payment intent fetched.',
            $intent
        );
    }

    public function redirect(string $uuid): JsonResponse
    {
        $intent = PaymentIntent::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Hosted gateway handoff data fetched.',
            [
                'payment_intent_uuid' => $intent->uuid,
                'provider' => $intent->provider,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'status' => $intent->status,
                'expires_at' =>
                    $intent->expires_at?->toIso8601String(),
                'provider_message' =>
                    'Use the configured provider SDK or hosted checkout with this intent.',
            ]
        );
    }

    public function webhook(
        Request $request,
        string $provider
    ): JsonResponse {
        $gateway = PaymentGatewayConfig::query()
            ->where('code', $provider)
            ->firstOrFail();

        $raw = $request->getContent();
        $signature = $request->header('X-FastSheba-Signature')
            ?? $request->header('X-Signature');

        $payload = $request->json()->all();
        $eventId = (string) (
            $request->header('X-Event-Id')
            ?? data_get($payload, 'event_id')
            ?? data_get($payload, 'id')
            ?? hash('sha256', $raw)
        );

        $eventType = data_get($payload, 'event_type')
            ?? data_get($payload, 'type');

        $event = $this->payments->handleWebhook(
            $provider,
            $eventId,
            $eventType,
            $payload,
            $this->payments->verifyWebhookSignature(
                $gateway,
                $raw,
                $signature
            )
        );

        return ApiResponseType::sendJsonResponse(
            $event->status !== 'rejected',
            'Payment webhook processed.',
            [
                'event_id' => $event->event_id,
                'status' => $event->status,
            ],
            $event->status === 'rejected' ? 401 : 200
        );
    }

    private function resolvePurpose(
        Request $request,
        array $data
    ): array {
        if ($data['purpose'] === 'order') {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['order_id'] ?? 0);

            return [
                (float) $order->final_total,
                ['order_id' => $order->id],
            ];
        }

        if ($data['purpose'] === 'subscription') {
            $seller = Seller::query()
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $transaction = SubscriptionTransaction::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'pending')
                ->findOrFail(
                    $data['subscription_transaction_id'] ?? 0
                );

            return [
                (float) $transaction->amount,
                [
                    'seller_id' => $seller->id,
                    'subscription_transaction_id' =>
                        $transaction->id,
                ],
            ];
        }

        $seller = Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return [
            (float) ($data['amount'] ?? 0),
            ['seller_id' => $seller->id],
        ];
    }
}
