<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeliveryBoy;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationInboxService
{
    public function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type = 'general',
        array $metadata = [],
        ?int $storeId = null,
        ?int $orderId = null,
        ?string $roleType = null
    ): Notification {
        return Notification::query()->create([
            'user_id' => $user->id,
            'store_id' => $storeId,
            'order_id' => $orderId,
            'role_type' => $roleType ?: $this->roleType($user),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    public function inbox(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Notification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function mark(
        User $user,
        int $id,
        bool $read
    ): Notification {
        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->update([
            'is_read' => $read,
            'read_at' => $read ? now() : null,
        ]);

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function broadcast(
        User $admin,
        array $data
    ): AppNotification {
        return DB::transaction(function () use ($admin, $data): AppNotification {
            $scheduledAt = isset($data['scheduled_at'])
                ? \Illuminate\Support\Carbon::parse($data['scheduled_at'])
                : null;

            $isScheduled = $scheduledAt && $scheduledAt->isFuture();

            $metadata = array_merge(
                $data['metadata'] ?? [],
                [
                    'recipient_user_ids' => array_values(
                        array_map('intval', $data['user_ids'] ?? [])
                    ),
                ]
            );

            $campaign = AppNotification::query()->create([
                'audience_type' => $data['audience_type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'target_type' => $data['target_type'] ?? null,
                'target_id' => $data['target_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'status' => $isScheduled ? 'scheduled' : 'draft',
                'metadata' => $metadata,
                'created_by' => $admin->id,
            ]);

            $zoneIds = collect($data['zone_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($zoneIds->isNotEmpty()) {
                $campaign->zones()->sync($zoneIds->all());
            }

            if (! $isScheduled) {
                $this->dispatchCampaign($campaign);
            }

            return $campaign->fresh(['users', 'zones', 'creator']);
        });
    }

    public function dispatchScheduled(): array
    {
        $campaigns = AppNotification::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $sent = 0;

        foreach ($campaigns as $campaign) {
            $this->dispatchCampaign($campaign);
            $sent++;
        }

        return ['scheduled_sent' => $sent];
    }

    public function dispatchCampaign(
        AppNotification $campaign
    ): AppNotification {
        return DB::transaction(function () use ($campaign): AppNotification {
            $campaign = AppNotification::query()
                ->with('zones')
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if ($campaign->status === 'sent') {
                return $campaign->fresh(['users', 'zones', 'creator']);
            }

            $metadata = $campaign->metadata ?? [];
            $users = $this->recipients(
                $campaign->audience_type,
                $metadata['recipient_user_ids'] ?? [],
                $campaign->zones->pluck('id')->all()
            );

            $publicMetadata = $metadata;
            unset($publicMetadata['recipient_user_ids']);

            foreach ($users as $user) {
                $roleType = $this->roleType($user);

                DB::table('app_notification_user_map')->updateOrInsert(
                    [
                        'notification_id' => $campaign->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'user_type' => $roleType,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->notifyUser(
                    $user,
                    $campaign->title,
                    $campaign->message,
                    'broadcast',
                    array_merge(
                        $publicMetadata,
                        [
                            'campaign_id' => $campaign->id,
                            'target_type' => $campaign->target_type,
                            'target_id' => $campaign->target_id,
                        ]
                    ),
                    roleType: $roleType
                );
            }

            $campaign->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $campaign->fresh(['users', 'zones', 'creator']);
        });
    }

    private function recipients(
        string $audience,
        array $userIds,
        array $zoneIds
    ) {
        $sellerUserIds = Seller::query()->pluck('user_id');
        $riderUserIds = DeliveryBoy::query()->pluck('user_id');

        $query = User::query()->where('status', 'active');

        if ($audience === 'users') {
            $query->whereIn('id', $userIds);
        } elseif ($audience === 'seller') {
            $query->whereIn('id', $sellerUserIds);
        } elseif ($audience === 'delivery_boy') {
            $query->whereIn('id', $riderUserIds);
        } elseif ($audience === 'customer') {
            $query->where('access_panel', 'web')
                ->whereNotIn('id', $sellerUserIds)
                ->whereNotIn('id', $riderUserIds);
        } elseif ($audience !== 'all') {
            $query->whereRaw('1 = 0');
        }

        if ($zoneIds !== []) {
            $customerIds = DB::table('user_zone')
                ->whereIn('zone_id', $zoneIds)
                ->pluck('user_id');

            $sellerIds = DB::table('store_zone')
                ->join('stores', 'stores.id', '=', 'store_zone.store_id')
                ->whereIn('store_zone.zone_id', $zoneIds)
                ->pluck('stores.seller_id');

            $sellerZoneUserIds = Seller::query()
                ->whereIn('id', $sellerIds)
                ->pluck('user_id');

            $riderZoneUserIds = DeliveryBoy::query()
                ->whereIn('delivery_zone_id', $zoneIds)
                ->pluck('user_id');

            $allowedIds = $customerIds
                ->merge($sellerZoneUserIds)
                ->merge($riderZoneUserIds)
                ->unique()
                ->values();

            $query->whereIn('id', $allowedIds);
        }

        return $query->get();
    }

    private function roleType(User $user): string
    {
        if (Seller::query()->where('user_id', $user->id)->exists()) {
            return 'seller';
        }

        if (DeliveryBoy::query()->where('user_id', $user->id)->exists()) {
            return 'delivery_boy';
        }

        $panel = $user->access_panel;

        if ($panel instanceof \BackedEnum) {
            $panel = $panel->value;
        }

        return $panel === 'admin' ? 'admin' : 'customer';
    }
}
