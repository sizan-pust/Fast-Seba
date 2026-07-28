<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\BulkUploadJob;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SellerSubscriptionUsage;
use App\Models\SubscriptionPlan;
use App\Models\Store;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        protected FinanceWalletService $wallets
    ) {
    }

    public function current(Seller $seller): ?SellerSubscription
    {
        $subscription = $this->findCurrent($seller);

        if ($subscription) {
            return $subscription;
        }

        return $this->activateDefaultPlan($seller);
    }

    private function findCurrent(
        Seller $seller
    ): ?SellerSubscription {
        return SellerSubscription::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['trial', 'active'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with(['plan.limits', 'usages'])
            ->latest('starts_at')
            ->first();
    }

    /**
     * HyperLocal assigns its default plan when a seller first enters a
     * subscription-protected flow. FastSheba uses the free `starter` plan
     * as that default and keeps existing resource usage in sync.
     */
    private function activateDefaultPlan(
        Seller $seller
    ): ?SellerSubscription {
        $plan = SubscriptionPlan::query()
            ->where('slug', 'starter')
            ->where('status', 'active')
            ->where('price', '<=', 0)
            ->with('limits')
            ->first();

        if (! $plan) {
            $plan = SubscriptionPlan::query()
                ->where('status', 'active')
                ->where('price', '<=', 0)
                ->with('limits')
                ->orderBy('sort_order')
                ->first();
        }

        if (! $plan) {
            return null;
        }

        return DB::transaction(function () use (
            $seller,
            $plan
        ): SellerSubscription {
            Seller::query()
                ->whereKey($seller->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $this->findCurrent($seller);

            if ($existing) {
                return $existing;
            }

            $startsAt = now();
            $endsAt = $plan->duration_days > 0
                ? $startsAt->copy()->addDays(
                    $plan->duration_days
                )
                : null;

            $subscription = SellerSubscription::query()->create([
                'seller_id' => $seller->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => null,
                'auto_renew' => false,
                'payment_method' => 'free_default',
                'snapshot' => [
                    'title' => $plan->title,
                    'price' => $plan->price,
                    'duration_days' => $plan->duration_days,
                    'limits' => $plan->limits
                        ->map(fn ($limit) => [
                            'feature_key' =>
                                $limit->feature_key,
                            'limit_value' =>
                                $limit->limit_value,
                            'is_unlimited' =>
                                $limit->is_unlimited,
                        ])
                        ->values()
                        ->all(),
                ],
                'metadata' => [
                    'assigned_automatically' => true,
                    'source' =>
                        'subscription_protected_flow',
                ],
            ]);

            $counts = [
                'products' => Product::query()
                    ->where('seller_id', $seller->id)
                    ->count(),
                'stores' => Store::query()
                    ->where('seller_id', $seller->id)
                    ->count(),
                'ad_campaigns' => AdCampaign::query()
                    ->where('seller_id', $seller->id)
                    ->count(),
                'bulk_uploads' => BulkUploadJob::query()
                    ->where('seller_id', $seller->id)
                    ->count(),
                'pos_access' => 0,
            ];

            foreach ($plan->limits as $limit) {
                SellerSubscriptionUsage::query()->create([
                    'seller_subscription_id' =>
                        $subscription->id,
                    'seller_id' => $seller->id,
                    'feature_key' => $limit->feature_key,
                    'used_count' =>
                        $counts[$limit->feature_key] ?? 0,
                    'period_starts_at' => $startsAt,
                    'period_ends_at' => $endsAt,
                ]);
            }

            return $subscription->fresh([
                'plan.limits',
                'usages',
            ]);
        });
    }

    public function eligibility(
        Seller $seller,
        string $featureKey,
        int $additional = 1
    ): array {
        $subscription = $this->current($seller);

        if (! $subscription) {
            return [
                'eligible' => false,
                'reason' => 'No active subscription.',
                'feature_key' => $featureKey,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
            ];
        }

        $limit = $subscription->plan->limits
            ->firstWhere('feature_key', $featureKey);

        if (! $limit) {
            return [
                'eligible' => false,
                'reason' => 'Feature is not included in the current plan.',
                'feature_key' => $featureKey,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
            ];
        }

        $usage = $subscription->usages
            ->firstWhere('feature_key', $featureKey);

        $used = (int) ($usage?->used_count ?? 0);

        if ($limit->is_unlimited) {
            return [
                'eligible' => true,
                'reason' => null,
                'feature_key' => $featureKey,
                'limit' => null,
                'used' => $used,
                'remaining' => null,
            ];
        }

        $max = (int) ($limit->limit_value ?? 0);
        $remaining = max(0, $max - $used);

        return [
            'eligible' => ($used + $additional) <= $max,
            'reason' => ($used + $additional) <= $max
                ? null
                : 'Subscription limit exceeded.',
            'feature_key' => $featureKey,
            'limit' => $max,
            'used' => $used,
            'remaining' => $remaining,
        ];
    }

    public function consume(
        Seller $seller,
        string $featureKey,
        int $amount = 1
    ): SellerSubscriptionUsage {
        return DB::transaction(function () use (
            $seller,
            $featureKey,
            $amount
        ): SellerSubscriptionUsage {
            $eligibility = $this->eligibility(
                $seller,
                $featureKey,
                $amount
            );

            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'subscription' => $eligibility['reason'],
                ]);
            }

            $subscription = $this->current($seller);

            $usage = SellerSubscriptionUsage::query()
                ->where('seller_subscription_id', $subscription->id)
                ->where('feature_key', $featureKey)
                ->lockForUpdate()
                ->first();

            if (! $usage) {
                $usage = SellerSubscriptionUsage::query()->create([
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $seller->id,
                    'feature_key' => $featureKey,
                    'used_count' => 0,
                    'period_starts_at' => $subscription->starts_at,
                    'period_ends_at' => $subscription->ends_at,
                ]);
            }

            $limit = $subscription->plan->limits
                ->firstWhere('feature_key', $featureKey);

            if (! $limit) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'Feature is not included in the current plan.',
                ]);
            }

            $increment = max(0, $amount);

            if (
                ! $limit->is_unlimited
                && ((int) $usage->used_count + $increment)
                    > (int) ($limit->limit_value ?? 0)
            ) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'Subscription limit exceeded.',
                ]);
            }

            $usage->increment('used_count', $increment);

            return $usage->fresh();
        });
    }

    public function buy(
        Seller $seller,
        User $buyer,
        SubscriptionPlan $plan,
        string $paymentMethod
    ): array {
        return DB::transaction(function () use (
            $seller,
            $buyer,
            $plan,
            $paymentMethod
        ): array {
            if ($plan->status !== 'active') {
                throw ValidationException::withMessages([
                    'plan' => 'Subscription plan is unavailable.',
                ]);
            }

            $price = (float) $plan->price;
            $transactionStatus = 'completed';

            $transaction = SubscriptionTransaction::query()->create([
                'seller_id' => $seller->id,
                'subscription_plan_id' => $plan->id,
                'amount' => $price,
                'currency' => 'BDT',
                'payment_method' => $paymentMethod,
                'status' => $price <= 0 ? 'completed' : 'pending',
                'metadata' => [
                    'plan_title' => $plan->title,
                ],
            ]);

            if ($price > 0 && $paymentMethod === 'wallet') {
                $this->wallets->block(
                    $buyer,
                    'seller',
                    $price
                );

                $walletTransaction = $this->wallets
                    ->approveBlockedWithdrawal(
                        $buyer,
                        'seller',
                        $price,
                        'subscription_transaction',
                        $transaction->id,
                        'Seller subscription purchase'
                    );

                $transaction->update([
                    'transaction_id' => $walletTransaction->uuid,
                    'status' => 'completed',
                ]);
            } elseif ($price > 0) {
                $transactionStatus = 'pending_payment';
            }

            if ($transaction->fresh()->status !== 'completed') {
                return [
                    'subscription' => null,
                    'transaction' => $transaction->fresh(),
                    'requires_external_payment' => true,
                ];
            }

            SellerSubscription::query()
                ->where('seller_id', $seller->id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'replaced',
                    'ends_at' => now(),
                ]);

            $startsAt = now();
            $trialEndsAt = $plan->trial_days > 0
                ? now()->addDays($plan->trial_days)
                : null;

            $subscription = SellerSubscription::query()->create([
                'seller_id' => $seller->id,
                'subscription_plan_id' => $plan->id,
                'status' => $trialEndsAt ? 'trial' : 'active',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()
                    ->addDays($plan->duration_days),
                'trial_ends_at' => $trialEndsAt,
                'auto_renew' => false,
                'payment_method' => $paymentMethod,
                'snapshot' => [
                    'title' => $plan->title,
                    'price' => $plan->price,
                    'duration_days' => $plan->duration_days,
                    'limits' => $plan->limits->map(fn ($limit) => [
                        'feature_key' => $limit->feature_key,
                        'limit_value' => $limit->limit_value,
                        'is_unlimited' => $limit->is_unlimited,
                    ])->values()->all(),
                ],
            ]);

            $transaction->update([
                'seller_subscription_id' => $subscription->id,
                'status' => $transactionStatus === 'pending_payment'
                    ? 'pending'
                    : 'completed',
            ]);

            foreach ($plan->limits as $limit) {
                SellerSubscriptionUsage::query()->create([
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $seller->id,
                    'feature_key' => $limit->feature_key,
                    'used_count' => 0,
                    'period_starts_at' => $subscription->starts_at,
                    'period_ends_at' => $subscription->ends_at,
                ]);
            }

            return [
                'subscription' => $subscription->fresh([
                    'plan.limits',
                    'usages',
                ]),
                'transaction' => $transaction->fresh(),
                'requires_external_payment' => false,
            ];
        });
    }

    public function activatePaidTransaction(
        SubscriptionTransaction $transaction,
        string $externalId
    ): SellerSubscription {
        return DB::transaction(function () use (
            $transaction,
            $externalId
        ): SellerSubscription {
            $transaction = SubscriptionTransaction::query()
                ->with(['subscription'])
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($transaction->status === 'completed') {
                return $transaction->subscription;
            }

            $seller = Seller::query()->findOrFail(
                $transaction->seller_id
            );

            $plan = SubscriptionPlan::query()
                ->with('limits')
                ->findOrFail($transaction->subscription_plan_id);

            SellerSubscription::query()
                ->where('seller_id', $seller->id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'replaced',
                    'ends_at' => now(),
                ]);

            $subscription = SellerSubscription::query()->create([
                'seller_id' => $seller->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days),
                'payment_method' => $transaction->payment_method,
                'snapshot' => [
                    'title' => $plan->title,
                    'price' => $plan->price,
                    'duration_days' => $plan->duration_days,
                ],
            ]);

            foreach ($plan->limits as $limit) {
                SellerSubscriptionUsage::query()->create([
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $seller->id,
                    'feature_key' => $limit->feature_key,
                    'used_count' => 0,
                    'period_starts_at' => $subscription->starts_at,
                    'period_ends_at' => $subscription->ends_at,
                ]);
            }

            $transaction->update([
                'seller_subscription_id' => $subscription->id,
                'transaction_id' => $externalId,
                'status' => 'completed',
            ]);

            return $subscription->fresh(['plan.limits', 'usages']);
        });
    }

    public function expireDue(): int
    {
        return SellerSubscription::query()
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    public function syncUsage(): array
    {
        $synced = 0;

        SellerSubscription::query()
            ->whereIn('status', ['trial', 'active'])
            ->with('plan.limits')
            ->chunkById(100, function ($subscriptions) use (&$synced): void {
                foreach ($subscriptions as $subscription) {
                    $counts = [
                        'products' => \App\Models\Product::query()
                            ->where('seller_id', $subscription->seller_id)
                            ->count(),
                        'stores' => \App\Models\Store::query()
                            ->where('seller_id', $subscription->seller_id)
                            ->count(),
                        'ad_campaigns' => \App\Models\AdCampaign::query()
                            ->where('seller_id', $subscription->seller_id)
                            ->count(),
                        'bulk_uploads' => \App\Models\BulkUploadJob::query()
                            ->where('seller_id', $subscription->seller_id)
                            ->count(),
                        'pos_access' => 0,
                    ];

                    foreach ($subscription->plan->limits as $limit) {
                        SellerSubscriptionUsage::query()->updateOrCreate(
                            [
                                'seller_subscription_id' =>
                                    $subscription->id,
                                'feature_key' => $limit->feature_key,
                            ],
                            [
                                'seller_id' => $subscription->seller_id,
                                'used_count' =>
                                    $counts[$limit->feature_key] ?? 0,
                                'period_starts_at' =>
                                    $subscription->starts_at,
                                'period_ends_at' =>
                                    $subscription->ends_at,
                            ]
                        );

                        $synced++;
                    }
                }
            });

        return ['usage_rows_synced' => $synced];
    }
}
