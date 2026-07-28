<?php

namespace App\Services;

use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyLocation;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function riderFor(User $user): DeliveryBoy
    {
        return DeliveryBoy::query()
            ->with(['user', 'deliveryZone', 'location'])
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function availableOrders(
        DeliveryBoy $rider,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Order::query()
            ->where('delivery_type', 'delivery')
            ->where('status', 'ready_for_pickup')
            ->when(
                $rider->delivery_zone_id,
                fn ($query) => $query->where(
                    'delivery_zone_id',
                    $rider->delivery_zone_id
                )
            )
            ->whereDoesntHave(
                'deliveryAssignments',
                fn ($query) => $query->whereIn('status', [
                    'accepted',
                    'picked_up',
                    'out_for_delivery',
                ])
            )
            ->with($this->orderRelations())
            ->latest()
            ->paginate($perPage);
    }

    public function myOrders(
        DeliveryBoy $rider,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Order::query()
            ->where('delivery_boy_id', $rider->id)
            ->with($this->orderRelations())
            ->latest()
            ->paginate($perPage);
    }

    public function assignedOrder(
        DeliveryBoy $rider,
        int $orderId
    ): Order {
        return Order::query()
            ->where('delivery_boy_id', $rider->id)
            ->with($this->orderRelations())
            ->findOrFail($orderId);
    }

    public function acceptOrder(
        DeliveryBoy $rider,
        int $orderId,
        ?User $assignedBy = null
    ): Order {
        if (! $rider->canWork()) {
            throw ValidationException::withMessages([
                'delivery_boy' =>
                    'Delivery partner is unavailable or blocked.',
            ]);
        }

        return DB::transaction(function () use (
            $rider,
            $orderId,
            $assignedBy
        ): Order {
            $order = Order::query()
                ->lockForUpdate()
                ->with('deliveryZone')
                ->findOrFail($orderId);

            if (
                $order->delivery_type !== 'delivery'
                || $order->status !== 'ready_for_pickup'
            ) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Order is not available for delivery.',
                ]);
            }

            $hasActiveAssignment = DeliveryBoyAssignment::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [
                    'accepted',
                    'picked_up',
                    'out_for_delivery',
                ])
                ->exists();

            if ($hasActiveAssignment) {
                throw ValidationException::withMessages([
                    'order' => 'Order has already been assigned.',
                ]);
            }

            DeliveryBoyAssignment::query()->create([
                'order_id' => $order->id,
                'delivery_boy_id' => $rider->id,
                'assigned_by' => $assignedBy?->id,
                'status' => 'accepted',
                'assigned_at' => now(),
                'accepted_at' => now(),
                'base_fee' =>
                    $order->deliveryZone?->delivery_boy_base_fee ?? 0,
                'distance_fee' => 0,
                'total_earnings' =>
                    $order->deliveryZone?->delivery_boy_base_fee ?? 0,
            ]);

            $from = $order->status;

            $order->update([
                'delivery_boy_id' => $rider->id,
                'status' => 'assigned',
            ]);

            $rider->update(['status' => 'on_delivery']);

            $this->logStatus(
                $order,
                $from,
                'assigned',
                $assignedBy?->id ?? $rider->user_id,
                $assignedBy ? 'admin' : 'delivery_boy',
                'Delivery partner assigned.'
            );

            return $this->loadOrder($order);
        });
    }

    public function updateOrderStatus(
        DeliveryBoy $rider,
        int $orderId,
        string $status,
        ?string $reason = null
    ): Order {
        return DB::transaction(function () use (
            $rider,
            $orderId,
            $status,
            $reason
        ): Order {
            $order = Order::query()
                ->where('delivery_boy_id', $rider->id)
                ->lockForUpdate()
                ->findOrFail($orderId);

            $assignment = DeliveryBoyAssignment::query()
                ->where('order_id', $order->id)
                ->where('delivery_boy_id', $rider->id)
                ->whereIn('status', [
                    'accepted',
                    'picked_up',
                    'out_for_delivery',
                ])
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            $allowed = [
                'accepted' => ['picked_up'],
                'picked_up' => ['out_for_delivery'],
                'out_for_delivery' => [
                    'delivered',
                    'delivery_failed',
                ],
            ];

            if (
                ! in_array(
                    $status,
                    $allowed[$assignment->status] ?? [],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Invalid delivery status transition.',
                ]);
            }

            $from = $order->status;

            if ($status === 'picked_up') {
                $assignment->update([
                    'status' => 'picked_up',
                    'picked_up_at' => now(),
                ]);

                $order->items()
                    ->where('status', 'ready_for_pickup')
                    ->update(['status' => 'picked_up']);

                $order->update([
                    'status' => 'picked_up',
                    'delivery_started_at' => now(),
                ]);
            }

            if ($status === 'out_for_delivery') {
                $assignment->update([
                    'status' => 'out_for_delivery',
                    'out_for_delivery_at' => now(),
                ]);

                $order->items()
                    ->where('status', 'picked_up')
                    ->update(['status' => 'out_for_delivery']);

                $order->update([
                    'status' => 'out_for_delivery',
                ]);
            }

            if ($status === 'delivered') {
                $cash = $order->payment_method === 'cod'
                    ? (float) $order->total_payable
                    : 0;

                $assignment->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                    'cash_collected' => $cash,
                    'payment_status' => 'completed',
                ]);

                $order->items()
                    ->whereNotIn('status', [
                        'cancelled',
                        'rejected_by_seller',
                    ])
                    ->update(['status' => 'delivered']);

                $updates = [
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ];

                if ($order->payment_method === 'cod') {
                    $updates['payment_status'] = 'completed';
                    $updates['paid_at'] = now();
                }

                $order->update($updates);
                $rider->update(['status' => 'available']);
            }

            if ($status === 'delivery_failed') {
                $assignment->update([
                    'status' => 'failed',
                    'failure_reason' =>
                        $reason ?: 'Delivery failed.',
                ]);

                $order->update([
                    'status' => 'delivery_failed',
                ]);

                $rider->update(['status' => 'available']);
            }

            $this->logStatus(
                $order,
                $from,
                $status,
                $rider->user_id,
                'delivery_boy',
                $reason
            );

            return $this->loadOrder($order);
        });
    }

    public function updateLocation(
        DeliveryBoy $rider,
        array $data
    ): DeliveryBoyLocation {
        return DeliveryBoyLocation::query()->updateOrCreate(
            ['delivery_boy_id' => $rider->id],
            [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'heading' => $data['heading'] ?? null,
                'speed' => $data['speed'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'recorded_at' => now(),
            ]
        );
    }

    public function customerLocation(
        User $user,
        string $orderSlug
    ): array {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('slug', $orderSlug)
            ->with([
                'deliveryBoy.user',
                'deliveryBoy.location',
            ])
            ->firstOrFail();

        if (! $order->deliveryBoy) {
            return [
                'assigned' => false,
                'order_status' => $order->status,
                'location' => null,
                'delivery_boy' => null,
            ];
        }

        $location = $order->deliveryBoy->location;

        return [
            'assigned' => true,
            'order_status' => $order->status,
            'delivery_boy' => [
                'id' => $order->deliveryBoy->id,
                'name' => $order->deliveryBoy->user?->name,
                'mobile' => $order->deliveryBoy->user?->mobile,
                'vehicle_type' =>
                    $order->deliveryBoy->vehicle_type,
                'vehicle_number' =>
                    $order->deliveryBoy->vehicle_number,
            ],
            'location' => $location ? [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'heading' => $location->heading === null
                    ? null
                    : (float) $location->heading,
                'recorded_at' =>
                    $location->recorded_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function orderRelations(): array
    {
        return [
            'deliveryZone',
            'deliveryBoy.user',
            'deliveryBoy.location',
            'deliveryAssignments.deliveryBoy.user',
            'items.product',
            'items.variant',
            'items.store',
            'items.returnRequest.deliveryBoy.user',
            'items.returnRequest.refundTransaction',
            'paymentTransactions',
            'statusLogs',
        ];
    }

    private function loadOrder(Order $order): Order
    {
        return $order->fresh($this->orderRelations());
    }

    private function logStatus(
        Order $order,
        ?string $from,
        string $to,
        ?int $changedBy,
        string $actorType,
        ?string $note
    ): void {
        OrderStatusLog::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'actor_type' => $actorType,
            'note' => $note,
        ]);
    }
}
