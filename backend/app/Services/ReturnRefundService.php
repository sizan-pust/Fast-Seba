<?php

namespace App\Services;

use App\Models\DeliveryBoy;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\RefundTransaction;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRefundService
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    public function request(
        User $user,
        int $orderItemId,
        array $data
    ): OrderItemReturn {
        return DB::transaction(function () use (
            $user,
            $orderItemId,
            $data
        ): OrderItemReturn {
            $item = OrderItem::query()
                ->whereHas(
                    'order',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->with([
                    'order',
                    'sellerOrder.seller',
                ])
                ->lockForUpdate()
                ->findOrFail($orderItemId);

            if ($item->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order_item' =>
                        'Only delivered items can be returned.',
                ]);
            }

            if (! $item->is_returnable) {
                throw ValidationException::withMessages([
                    'order_item' =>
                        'This item is not returnable.',
                ]);
            }

            if (
                $item->returnable_until
                && $item->returnable_until->isPast()
            ) {
                throw ValidationException::withMessages([
                    'order_item' =>
                        'The return window has expired.',
                ]);
            }

            if ($item->returnRequest()->exists()) {
                throw ValidationException::withMessages([
                    'order_item' =>
                        'A return request already exists.',
                ]);
            }

            $quantity = min(
                max(
                    1,
                    (int) ($data['quantity'] ?? $item->quantity)
                ),
                $item->quantity
            );

            $refundAmount = round(
                (
                    (float) $item->subtotal
                    / max(1, $item->quantity)
                ) * $quantity,
                2
            );

            $seller = $item->sellerOrder?->seller;

            if (! $seller) {
                throw ValidationException::withMessages([
                    'seller' => 'Seller is unavailable.',
                ]);
            }

            return OrderItemReturn::query()->create([
                'order_item_id' => $item->id,
                'order_id' => $item->order_id,
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'store_id' => $item->store_id,
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'refund_amount' => $refundAmount,
                'refund_method' =>
                    $data['refund_method'] ?? 'wallet',
                'pickup_status' => 'pending',
                'return_status' => 'requested',
                'requested_at' => now(),
            ]);
        })->fresh($this->relations());
    }

    public function cancel(
        User $user,
        int $orderItemId
    ): OrderItemReturn {
        $return = OrderItemReturn::query()
            ->where('user_id', $user->id)
            ->where('order_item_id', $orderItemId)
            ->firstOrFail();

        if ($return->return_status !== 'requested') {
            throw ValidationException::withMessages([
                'return' =>
                    'This return request can no longer be cancelled.',
            ]);
        }

        $return->update([
            'return_status' => 'cancelled',
            'pickup_status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $return->fresh($this->relations());
    }

    public function sellerReturns(
        Seller $seller,
        int $perPage = 15,
        ?string $status = null
    ): LengthAwarePaginator {
        return OrderItemReturn::query()
            ->where('seller_id', $seller->id)
            ->when(
                $status,
                fn ($query) => $query->where(
                    'return_status',
                    $status
                )
            )
            ->with($this->relations())
            ->latest()
            ->paginate($perPage);
    }

    public function sellerDecision(
        Seller $seller,
        int $returnId,
        string $decision,
        ?string $comment = null
    ): OrderItemReturn {
        return DB::transaction(function () use (
            $seller,
            $returnId,
            $decision,
            $comment
        ): OrderItemReturn {
            $return = OrderItemReturn::query()
                ->where('seller_id', $seller->id)
                ->lockForUpdate()
                ->findOrFail($returnId);

            if ($return->return_status !== 'requested') {
                throw ValidationException::withMessages([
                    'return' =>
                        'Return request has already been decided.',
                ]);
            }

            $return->update([
                'return_status' => $decision === 'approve'
                    ? 'seller_approved'
                    : 'seller_rejected',
                'seller_comment' => $comment,
                'seller_decided_at' => now(),
            ]);

            return $return->fresh($this->relations());
        });
    }

    public function availablePickups(
        DeliveryBoy $rider,
        int $perPage = 15
    ): LengthAwarePaginator {
        return OrderItemReturn::query()
            ->where('return_status', 'seller_approved')
            ->whereNull('delivery_boy_id')
            ->whereHas(
                'order',
                fn ($query) => $query->when(
                    $rider->delivery_zone_id,
                    fn ($orderQuery) => $orderQuery->where(
                        'delivery_zone_id',
                        $rider->delivery_zone_id
                    )
                )
            )
            ->with($this->relations())
            ->latest()
            ->paginate($perPage);
    }

    public function myPickups(
        DeliveryBoy $rider,
        int $perPage = 15
    ): LengthAwarePaginator {
        return OrderItemReturn::query()
            ->where('delivery_boy_id', $rider->id)
            ->with($this->relations())
            ->latest()
            ->paginate($perPage);
    }

    public function acceptPickup(
        DeliveryBoy $rider,
        int $returnId
    ): OrderItemReturn {
        return DB::transaction(function () use (
            $rider,
            $returnId
        ): OrderItemReturn {
            $return = OrderItemReturn::query()
                ->where('return_status', 'seller_approved')
                ->whereNull('delivery_boy_id')
                ->lockForUpdate()
                ->findOrFail($returnId);

            $return->update([
                'delivery_boy_id' => $rider->id,
                'return_status' => 'pickup_assigned',
                'pickup_status' => 'assigned',
                'pickup_assigned_at' => now(),
            ]);

            return $return->fresh($this->relations());
        });
    }

    public function updatePickup(
        DeliveryBoy $rider,
        int $returnId,
        string $status
    ): OrderItemReturn {
        return DB::transaction(function () use (
            $rider,
            $returnId,
            $status
        ): OrderItemReturn {
            $return = OrderItemReturn::query()
                ->where('delivery_boy_id', $rider->id)
                ->lockForUpdate()
                ->findOrFail($returnId);

            $allowed = [
                'pickup_assigned' => ['picked_up'],
                'picked_up' => ['received_by_seller'],
            ];

            if (
                ! in_array(
                    $status,
                    $allowed[$return->return_status] ?? [],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Invalid return pickup transition.',
                ]);
            }

            if ($status === 'picked_up') {
                $return->update([
                    'return_status' => 'picked_up',
                    'pickup_status' => 'picked_up',
                    'picked_up_at' => now(),
                ]);
            } else {
                $return->update([
                    'return_status' => 'received_by_seller',
                    'pickup_status' => 'delivered_to_seller',
                    'received_at' => now(),
                ]);
            }

            return $return->fresh($this->relations());
        });
    }

    public function assignPickup(
        User $admin,
        int $returnId,
        DeliveryBoy $rider
    ): OrderItemReturn {
        return DB::transaction(function () use (
            $admin,
            $returnId,
            $rider
        ): OrderItemReturn {
            $return = OrderItemReturn::query()
                ->where('return_status', 'seller_approved')
                ->whereNull('delivery_boy_id')
                ->lockForUpdate()
                ->findOrFail($returnId);

            $return->update([
                'delivery_boy_id' => $rider->id,
                'return_status' => 'pickup_assigned',
                'pickup_status' => 'assigned',
                'pickup_assigned_at' => now(),
                'admin_comment' =>
                    'Assigned by '.$admin->name,
            ]);

            return $return->fresh($this->relations());
        });
    }

    public function refund(
        User $admin,
        int $returnId,
        ?string $comment = null
    ): RefundTransaction {
        return DB::transaction(function () use (
            $admin,
            $returnId,
            $comment
        ): RefundTransaction {
            $return = OrderItemReturn::query()
                ->with([
                    'user',
                    'refundTransaction',
                ])
                ->lockForUpdate()
                ->findOrFail($returnId);

            if ($return->refundTransaction) {
                throw ValidationException::withMessages([
                    'refund' =>
                        'Refund has already been processed.',
                ]);
            }

            if (
                $return->return_status !==
                'received_by_seller'
            ) {
                throw ValidationException::withMessages([
                    'return' =>
                        'Returned item must be received by the seller first.',
                ]);
            }

            $walletTransaction =
                $this->walletService->credit(
                    $return->user,
                    (float) $return->refund_amount,
                    'order_item_return',
                    $return->id,
                    'Refund for order return #'.$return->id
                );

            $refund = RefundTransaction::query()->create([
                'order_item_return_id' => $return->id,
                'order_id' => $return->order_id,
                'user_id' => $return->user_id,
                'wallet_transaction_id' =>
                    $walletTransaction->id,
                'amount' => $return->refund_amount,
                'currency' => 'BDT',
                'method' => 'wallet',
                'status' => 'completed',
                'reason' => $comment ?: $return->reason,
                'metadata' => [
                    'processed_by' => $admin->id,
                ],
            ]);

            $return->update([
                'return_status' => 'refund_processed',
                'refund_processed_at' => now(),
                'admin_comment' => $comment,
            ]);

            return $refund->fresh([
                'orderReturn',
                'walletTransaction',
            ]);
        });
    }

    public function adminReturns(
        int $perPage = 15,
        ?string $status = null
    ): LengthAwarePaginator {
        return OrderItemReturn::query()
            ->when(
                $status,
                fn ($query) => $query->where(
                    'return_status',
                    $status
                )
            )
            ->with($this->relations())
            ->latest()
            ->paginate($perPage);
    }

    private function relations(): array
    {
        return [
            'orderItem.product',
            'orderItem.variant',
            'order',
            'user',
            'seller',
            'store',
            'deliveryBoy.user',
            'deliveryBoy.location',
            'refundTransaction',
        ];
    }
}
