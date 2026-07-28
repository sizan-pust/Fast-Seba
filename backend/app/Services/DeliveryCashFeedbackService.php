<?php

namespace App\Services;

use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyCashTransaction;
use App\Models\DeliveryFeedback;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCashFeedbackService
{
    public function syncCashCollections(): array
    {
        $created = 0;

        DeliveryBoyAssignment::query()
            ->where('status', 'delivered')
            ->where('cash_collected', '>', 0)
            ->with('order')
            ->chunkById(100, function ($assignments) use (&$created): void {
                foreach ($assignments as $assignment) {
                    if (
                        ! $assignment->order
                        || $assignment->order->payment_method !== 'cod'
                    ) {
                        continue;
                    }

                    $transaction = DeliveryBoyCashTransaction::query()
                        ->firstOrCreate(
                            [
                                'delivery_boy_assignment_id' =>
                                    $assignment->id,
                                'type' => 'collection',
                            ],
                            [
                                'delivery_boy_id' =>
                                    $assignment->delivery_boy_id,
                                'order_id' => $assignment->order_id,
                                'amount' =>
                                    $assignment->cash_collected,
                                'status' => 'pending',
                                'reference' =>
                                    $assignment->order->slug,
                                'note' =>
                                    'COD cash collected from customer.',
                            ]
                        );

                    if ($transaction->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return ['collections_created' => $created];
    }

    public function riderBalance(DeliveryBoy $rider): array
    {
        $collected = (float) DeliveryBoyCashTransaction::query()
            ->where('delivery_boy_id', $rider->id)
            ->where('type', 'collection')
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        $remitted = (float) DeliveryBoyCashTransaction::query()
            ->where('delivery_boy_id', $rider->id)
            ->where('type', 'remittance')
            ->where('status', 'approved')
            ->sum('amount');

        $adjustments = (float) DeliveryBoyCashTransaction::query()
            ->where('delivery_boy_id', $rider->id)
            ->where('type', 'adjustment')
            ->where('status', 'approved')
            ->sum('amount');

        return [
            'collected' => round($collected, 2),
            'remitted' => round($remitted, 2),
            'adjustments' => round($adjustments, 2),
            'cash_in_hand' => round(
                $collected - $remitted + $adjustments,
                2
            ),
            'currency' => 'BDT',
        ];
    }

    public function requestRemittance(
        DeliveryBoy $rider,
        float $amount,
        ?string $reference,
        ?string $note
    ): DeliveryBoyCashTransaction {
        $balance = $this->riderBalance($rider);

        if ($amount <= 0 || $amount > $balance['cash_in_hand']) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Remittance amount exceeds rider cash-in-hand.',
            ]);
        }

        return DeliveryBoyCashTransaction::query()->create([
            'delivery_boy_id' => $rider->id,
            'type' => 'remittance',
            'amount' => $amount,
            'status' => 'pending',
            'reference' => $reference,
            'note' => $note,
        ]);
    }

    public function processCashTransaction(
        DeliveryBoyCashTransaction $transaction,
        User $admin,
        string $status,
        ?string $note
    ): DeliveryBoyCashTransaction {
        if ($transaction->status !== 'pending') {
            throw ValidationException::withMessages([
                'transaction' =>
                    'Cash transaction has already been processed.',
            ]);
        }

        $transaction->update([
            'status' => $status,
            'note' => $note ?? $transaction->note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        return $transaction->fresh();
    }

    public function sellerFeedback(
        User $user,
        Order $order,
        Seller $seller,
        int $rating,
        ?string $comment
    ): SellerFeedback {
        $this->ensureDeliveredCustomerOrder($user, $order);

        $sellerOrderExists = $order->sellerOrders()
            ->where('seller_id', $seller->id)
            ->exists();

        if (! $sellerOrderExists) {
            throw ValidationException::withMessages([
                'seller_id' =>
                    'Seller did not participate in this order.',
            ]);
        }

        if (
            SellerFeedback::query()
                ->where('user_id', $user->id)
                ->where('seller_id', $seller->id)
                ->where('order_id', $order->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order_id' =>
                    'Seller feedback was already submitted for this order.',
            ]);
        }

        return SellerFeedback::query()->create([
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'published',
        ]);
    }

    public function deliveryFeedback(
        User $user,
        Order $order,
        DeliveryBoy $rider,
        int $rating,
        ?string $comment
    ): DeliveryFeedback {
        $this->ensureDeliveredCustomerOrder($user, $order);

        if ($order->delivery_boy_id !== $rider->id) {
            throw ValidationException::withMessages([
                'delivery_boy_id' =>
                    'Delivery partner is not assigned to this order.',
            ]);
        }

        if (
            DeliveryFeedback::query()
                ->where('user_id', $user->id)
                ->where('delivery_boy_id', $rider->id)
                ->where('order_id', $order->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order_id' =>
                    'Delivery feedback was already submitted for this order.',
            ]);
        }

        return DeliveryFeedback::query()->create([
            'user_id' => $user->id,
            'delivery_boy_id' => $rider->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'published',
        ]);
    }

    public function replySellerFeedback(
        Seller $seller,
        SellerFeedback $feedback,
        string $reply
    ): SellerFeedback {
        abort_unless($feedback->seller_id === $seller->id, 404);

        $feedback->update([
            'seller_reply' => $reply,
            'seller_replied_at' => now(),
        ]);

        return $feedback->fresh();
    }

    public function sellerRating(Seller $seller): array
    {
        $query = SellerFeedback::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'published');

        return [
            'seller_id' => $seller->id,
            'average_rating' => round(
                (float) $query->avg('rating'),
                2
            ),
            'review_count' => $query->count(),
            'breakdown' => collect(range(1, 5))
                ->mapWithKeys(
                    fn ($rating) => [
                        (string) $rating => (
                            clone $query
                        )->where('rating', $rating)->count(),
                    ]
                )
                ->all(),
        ];
    }

    public function riderRating(DeliveryBoy $rider): array
    {
        $query = DeliveryFeedback::query()
            ->where('delivery_boy_id', $rider->id)
            ->where('status', 'published');

        return [
            'delivery_boy_id' => $rider->id,
            'average_rating' => round(
                (float) $query->avg('rating'),
                2
            ),
            'review_count' => $query->count(),
            'breakdown' => collect(range(1, 5))
                ->mapWithKeys(
                    fn ($rating) => [
                        (string) $rating => (
                            clone $query
                        )->where('rating', $rating)->count(),
                    ]
                )
                ->all(),
        ];
    }

    private function ensureDeliveredCustomerOrder(
        User $user,
        Order $order
    ): void {
        if (
            $order->user_id !== $user->id
            || $order->status !== 'delivered'
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'Feedback requires the customer delivered order.',
            ]);
        }
    }
}
