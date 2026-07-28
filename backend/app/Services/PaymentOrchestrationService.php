<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\PaymentIntent;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentOrchestrationService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected FinanceWalletService $wallets
    ) {
    }

    public function createIntent(
        ?User $user,
        string $provider,
        string $purpose,
        float $amount,
        string $currency,
        array $context
    ): PaymentIntent {
        $gateway = PaymentGatewayConfig::query()
            ->where('code', $provider)
            ->where('enabled', true)
            ->firstOrFail();

        if (
            ! in_array(
                $currency,
                $gateway->supported_currencies ?? [],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'Currency is unsupported by the selected gateway.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be positive.',
            ]);
        }

        $intent = PaymentIntent::query()->create([
            'user_id' => $user?->id,
            'order_id' => $context['order_id'] ?? null,
            'seller_id' => $context['seller_id'] ?? null,
            'purpose' => $purpose,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'external_id' => null,
            'expires_at' => now()->addMinutes(30),
            'metadata' => $context,
        ]);

        $publicConfig = $gateway->public_config ?? [];
        $baseUrl = rtrim(
            (string) ($publicConfig['checkout_base_url']
                ?? config('app.url')),
            '/'
        );

        $intent->update([
            'checkout_url' => $baseUrl
                .'/api/payments/intents/'
                .$intent->uuid
                .'/redirect',
        ]);

        return $intent->fresh();
    }

    public function verifyWebhookSignature(
        PaymentGatewayConfig $gateway,
        string $rawPayload,
        ?string $signature
    ): bool {
        $secret = $gateway->secret_config['webhook_secret']
            ?? null;

        if (! $secret || ! $signature) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $rawPayload,
            (string) $secret
        );

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(
        string $provider,
        string $eventId,
        ?string $eventType,
        array $payload,
        bool $signatureValid
    ): WebhookEvent {
        return DB::transaction(function () use (
            $provider,
            $eventId,
            $eventType,
            $payload,
            $signatureValid
        ): WebhookEvent {
            $event = WebhookEvent::query()->firstOrCreate(
                [
                    'provider' => $provider,
                    'event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'status' => 'received',
                    'signature_valid' => $signatureValid,
                    'payload' => $payload,
                ]
            );

            if (! $event->wasRecentlyCreated) {
                return $event;
            }

            if (! $signatureValid) {
                $event->update([
                    'status' => 'rejected',
                    'error' => 'Invalid webhook signature.',
                    'processed_at' => now(),
                ]);

                return $event->fresh();
            }

            $intentUuid = data_get($payload, 'payment_intent_uuid')
                ?? data_get($payload, 'metadata.payment_intent_uuid');

            $externalId = data_get($payload, 'transaction_id')
                ?? data_get($payload, 'id')
                ?? $eventId;

            $status = Str::lower(
                (string) (
                    data_get($payload, 'status')
                    ?? data_get($payload, 'payment_status')
                    ?? ''
                )
            );

            $intent = PaymentIntent::query()
                ->where('uuid', $intentUuid)
                ->lockForUpdate()
                ->first();

            if (! $intent) {
                $event->update([
                    'status' => 'failed',
                    'error' => 'Payment intent not found.',
                    'processed_at' => now(),
                ]);

                return $event->fresh();
            }

            if (
                in_array(
                    $status,
                    ['paid', 'success', 'successful', 'completed'],
                    true
                )
            ) {
                $this->completeIntent($intent, $externalId);
                $eventStatus = 'processed';
            } elseif (
                in_array(
                    $status,
                    ['failed', 'cancelled', 'canceled', 'expired'],
                    true
                )
            ) {
                $intent->update([
                    'status' => 'failed',
                    'external_id' => $externalId,
                ]);

                $eventStatus = 'processed';
            } else {
                $eventStatus = 'ignored';
            }

            $event->update([
                'status' => $eventStatus,
                'processed_at' => now(),
            ]);

            return $event->fresh();
        });
    }

    public function completeIntent(
        PaymentIntent $intent,
        string $externalId
    ): PaymentIntent {
        return DB::transaction(function () use (
            $intent,
            $externalId
        ): PaymentIntent {
            $intent = PaymentIntent::query()
                ->lockForUpdate()
                ->findOrFail($intent->id);

            if ($intent->status === 'completed') {
                return $intent;
            }

            $intent->update([
                'status' => 'completed',
                'external_id' => $externalId,
            ]);

            if ($intent->purpose === 'order' && $intent->order_id) {
                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($intent->order_id);

                $order->update([
                    'payment_status' => 'completed',
                    'paid_at' => now(),
                ]);

                \App\Models\OrderPaymentTransaction::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'transaction_id' => $externalId,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'payment_method' => $intent->provider,
                    'payment_status' => 'completed',
                    'message' => 'Gateway payment completed.',
                    'payment_details' => [
                        'payment_intent_uuid' => $intent->uuid,
                    ],
                ]);
            }

            if ($intent->purpose === 'subscription') {
                $transactionId = data_get(
                    $intent->metadata,
                    'subscription_transaction_id'
                );

                if ($transactionId) {
                    $transaction = SubscriptionTransaction::query()
                        ->findOrFail($transactionId);

                    $this->subscriptions->activatePaidTransaction(
                        $transaction,
                        $externalId
                    );
                }
            }

            if ($intent->purpose === 'ad_wallet' && $intent->user_id) {
                $user = User::query()->findOrFail($intent->user_id);

                $this->wallets->credit(
                    $user,
                    'seller_ad',
                    (float) $intent->amount,
                    'payment_intent',
                    $intent->id,
                    'Advertising wallet gateway top-up'
                );
            }

            return $intent->fresh();
        });
    }
}
