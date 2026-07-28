<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertisingService
{
    public function __construct(
        protected FinanceWalletService $wallets,
        protected SubscriptionService $subscriptions
    ) {
    }

    public function sellerWallet(User $user): array
    {
        $wallet = $this->wallets->wallet($user, 'seller_ad');

        return [
            'id' => $wallet->id,
            'balance' => $wallet->balance,
            'blocked_balance' => $wallet->blocked_balance,
            'available_balance' =>
                $this->wallets->availableBalance($wallet),
            'currency_code' => $wallet->currency_code,
        ];
    }

    public function topupFromSellerWallet(
        User $user,
        float $amount
    ): array {
        return DB::transaction(function () use (
            $user,
            $amount
        ): array {
            $sellerWallet = $this->wallets->wallet($user, 'seller');

            if (
                $this->wallets->availableBalance($sellerWallet)
                < $amount
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Seller wallet has insufficient available balance.',
                ]);
            }

            $this->wallets->block($user, 'seller', $amount);

            $debit = $this->wallets->approveBlockedWithdrawal(
                $user,
                'seller',
                $amount,
                'ad_wallet_topup',
                $user->id,
                'Transfer to advertising wallet'
            );

            $credit = $this->wallets->credit(
                $user,
                'seller_ad',
                $amount,
                'seller_wallet_transaction',
                $debit->id,
                'Advertising wallet top-up'
            );

            return [
                'seller_wallet_transaction_id' => $debit->id,
                'ad_wallet_transaction_id' => $credit->id,
                'ad_wallet' => $this->sellerWallet($user),
            ];
        });
    }

    public function sellerCampaigns(
        Seller $seller
    ): Collection {
        return AdCampaign::query()
            ->where('seller_id', $seller->id)
            ->with(['store', 'product', 'stats'])
            ->latest()
            ->get();
    }

    public function saveCampaign(
        Seller $seller,
        User $user,
        ?AdCampaign $campaign,
        array $data
    ): AdCampaign {
        return DB::transaction(function () use (
            $seller,
            $user,
            $campaign,
            $data
        ): AdCampaign {
            if (! empty($data['store_id'])) {
                \App\Models\Store::query()
                    ->where('seller_id', $seller->id)
                    ->findOrFail($data['store_id']);
            }

            if (! empty($data['product_id'])) {
                \App\Models\Product::query()
                    ->where('seller_id', $seller->id)
                    ->findOrFail($data['product_id']);
            }

            if ($campaign) {
                $campaign->loadMissing('seller.owner');

                abort_unless(
                    $campaign->seller_id === $seller->id,
                    404
                );

                if (
                    ! in_array(
                        $campaign->status,
                        ['draft', 'rejected', 'paused'],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'campaign' =>
                            'Only draft, rejected, or paused campaigns can be edited.',
                    ]);
                }

                $metadata = $campaign->metadata ?? [];

                if (
                    ($metadata['wallet_reserved'] ?? false)
                    && $campaign->seller?->owner
                ) {
                    $remainingReservation = max(
                        0,
                        (float) $campaign->budget
                            - (float) $campaign->spent_amount
                    );

                    if ($remainingReservation > 0) {
                        $this->wallets->releaseBlocked(
                            $campaign->seller->owner,
                            'seller_ad',
                            $remainingReservation
                        );
                    }

                    $metadata['wallet_reserved'] = false;
                    $metadata['reservation_released_at'] =
                        now()->toIso8601String();
                }

                $campaign->update(array_merge($data, [
                    'status' => 'pending_approval',
                    'rejection_reason' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'metadata' => $metadata,
                ]));
            } else {
                $eligibility = $this->subscriptions->eligibility(
                    $seller,
                    'ad_campaigns'
                );

                if (! $eligibility['eligible']) {
                    throw ValidationException::withMessages([
                        'subscription' => $eligibility['reason'],
                    ]);
                }

                $campaign = AdCampaign::query()->create(
                    array_merge($data, [
                        'seller_id' => $seller->id,
                        'status' => 'pending_approval',
                    ])
                );

                $this->subscriptions->consume(
                    $seller,
                    'ad_campaigns'
                );
            }

            return $campaign->fresh(['store', 'product', 'stats']);
        });
    }

    public function approve(
        AdCampaign $campaign,
        User $admin
    ): AdCampaign {
        return DB::transaction(function () use (
            $campaign,
            $admin
        ): AdCampaign {
            $campaign = AdCampaign::query()
                ->with('seller.owner')
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if ($campaign->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'campaign' =>
                        'Only pending campaigns can be approved.',
                ]);
            }

            $owner = $campaign->seller?->owner;

            if (! $owner) {
                throw ValidationException::withMessages([
                    'seller' => 'Seller owner is unavailable.',
                ]);
            }

            $this->wallets->wallet($owner, 'seller_ad');

            $remainingBudget = max(
                0,
                (float) $campaign->budget
                    - (float) $campaign->spent_amount
            );

            if ($remainingBudget > 0) {
                $this->wallets->block(
                    $owner,
                    'seller_ad',
                    $remainingBudget
                );
            }

            $metadata = $campaign->metadata ?? [];
            $metadata['wallet_reserved'] = true;
            $metadata['reserved_amount'] = $remainingBudget;
            $metadata['reserved_at'] = now()->toIso8601String();

            $campaign->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'metadata' => $metadata,
            ]);

            return $campaign->fresh();
        });
    }

    public function reject(
        AdCampaign $campaign,
        User $admin,
        string $reason
    ): AdCampaign {
        $campaign->update([
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $campaign->fresh();
    }

    public function pause(
        Seller $seller,
        AdCampaign $campaign
    ): AdCampaign {
        abort_unless($campaign->seller_id === $seller->id, 404);

        if (! in_array($campaign->status, ['approved', 'active'], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign cannot be paused.',
            ]);
        }

        $campaign->update(['status' => 'paused']);

        return $campaign->fresh();
    }

    public function resume(
        Seller $seller,
        AdCampaign $campaign
    ): AdCampaign {
        abort_unless($campaign->seller_id === $seller->id, 404);

        if ($campaign->status !== 'paused') {
            throw ValidationException::withMessages([
                'campaign' => 'Only a paused campaign can be resumed.',
            ]);
        }

        $campaign->update(['status' => 'approved']);

        return $campaign->fresh();
    }

    public function publicCampaigns(
        string $placement
    ): Collection {
        return AdCampaign::query()
            ->whereIn('status', ['approved', 'active'])
            ->where('placement', $placement)
            ->whereColumn('spent_amount', '<', 'budget')
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with(['seller', 'store', 'product'])
            ->orderByDesc('bid_amount')
            ->limit(30)
            ->get();
    }

    public function recordEvent(
        AdCampaign $campaign,
        string $eventType,
        string $eventUuid,
        ?User $user,
        ?string $sessionHash
    ): array {
        return DB::transaction(function () use (
            $campaign,
            $eventType,
            $eventUuid,
            $user,
            $sessionHash
        ): array {
            $inserted = DB::table('ad_event_dedup')->insertOrIgnore([
                'event_uuid' => $eventUuid,
                'ad_campaign_id' => $campaign->id,
                'user_id' => $user?->id,
                'event_type' => $eventType,
                'session_hash' => $sessionHash,
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return [
                    'recorded' => false,
                    'duplicate' => true,
                ];
            }

            $campaign = AdCampaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if (
                ! in_array(
                    $campaign->status,
                    ['approved', 'active'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'campaign' => 'Campaign is not active.',
                ]);
            }

            $field = match ($eventType) {
                'impression' => 'impressions',
                'click' => 'clicks',
                'conversion' => 'conversions',
                default => throw ValidationException::withMessages([
                    'event_type' => 'Unsupported advertising event.',
                ]),
            };

            $cost = match ($eventType) {
                'impression' => $campaign->ad_type === 'cpm'
                    ? ((float) $campaign->bid_amount / 1000)
                    : 0.0,
                'click' => $campaign->ad_type === 'cpc'
                    ? (float) $campaign->bid_amount
                    : 0.0,
                default => 0.0,
            };

            $remaining = max(
                0,
                (float) $campaign->budget
                    - (float) $campaign->spent_amount
            );

            $cost = round(min($cost, $remaining), 4);

            $stat = AdCampaignStat::query()->firstOrCreate(
                [
                    'ad_campaign_id' => $campaign->id,
                    'stat_date' => now()->toDateString(),
                ],
                [
                    'impressions' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'spent_amount' => 0,
                ]
            );

            $stat->increment($field);

            if ($cost > 0) {
                $metadata = $campaign->metadata ?? [];

                if ($metadata['wallet_reserved'] ?? false) {
                    $campaign->loadMissing('seller.owner');
                    $owner = $campaign->seller?->owner;

                    if (! $owner) {
                        throw ValidationException::withMessages([
                            'seller' => 'Seller owner is unavailable.',
                        ]);
                    }

                    $this->wallets->approveBlockedWithdrawal(
                        $owner,
                        'seller_ad',
                        $cost,
                        'ad_campaign_event',
                        DB::table('ad_event_dedup')
                            ->where('event_uuid', $eventUuid)
                            ->value('id'),
                        'Advertising '.$eventType.' cost'
                    );
                }

                $stat->increment('spent_amount', $cost);
                $campaign->increment('spent_amount', $cost);
            }

            $campaign->refresh();

            if ((float) $campaign->spent_amount >= (float) $campaign->budget) {
                $campaign->update(['status' => 'completed']);
            } elseif ($campaign->status === 'approved') {
                $campaign->update(['status' => 'active']);
            }

            return [
                'recorded' => true,
                'duplicate' => false,
                'cost' => $cost,
                'campaign_status' => $campaign->fresh()->status,
            ];
        });
    }

    public function settleExpired(): int
    {
        $settled = 0;

        AdCampaign::query()
            ->whereIn('status', ['approved', 'active', 'paused'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('seller.owner')
            ->chunkById(100, function ($campaigns) use (&$settled): void {
                foreach ($campaigns as $campaign) {
                    DB::transaction(function () use (
                        $campaign,
                        &$settled
                    ): void {
                        $locked = AdCampaign::query()
                            ->with('seller.owner')
                            ->lockForUpdate()
                            ->findOrFail($campaign->id);

                        $metadata = $locked->metadata ?? [];
                        $remaining = max(
                            0,
                            (float) $locked->budget
                                - (float) $locked->spent_amount
                        );

                        if (
                            $remaining > 0
                            && ($metadata['wallet_reserved'] ?? false)
                            && $locked->seller?->owner
                        ) {
                            $this->wallets->releaseBlocked(
                                $locked->seller->owner,
                                'seller_ad',
                                $remaining
                            );
                        }

                        $metadata['wallet_reserved'] = false;
                        $metadata['completed_at'] =
                            now()->toIso8601String();

                        $locked->update([
                            'status' => 'completed',
                            'metadata' => $metadata,
                        ]);

                        $settled++;
                    });
                }
            });

        return $settled;
    }

    public function pruneDedup(int $days = 7): int
    {
        return DB::table('ad_event_dedup')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
