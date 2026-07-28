<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        protected NotificationInboxService $notifications
    ) {
    }

    public function publicReviews(
        int $productId,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Review::query()
            ->where('product_id', $productId)
            ->where('status', 'published')
            ->with('user:id,name')
            ->latest()
            ->paginate($perPage);
    }

    public function availableItems(User $user): array
    {
        return OrderItem::query()
            ->where('status', 'delivered')
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->whereNotIn(
                'id',
                Review::query()->select('order_item_id')
            )
            ->with(['product', 'store', 'order'])
            ->latest()
            ->get()
            ->map(fn (OrderItem $item) => [
                'order_item_id' => $item->id,
                'order_slug' => $item->order?->slug,
                'product_id' => $item->product_id,
                'product_title' => $item->product_title,
                'store_id' => $item->store_id,
                'store_name' => $item->store?->name,
                'delivered_at' => $item->order?->delivered_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function create(
        User $user,
        array $data
    ): Review {
        return DB::transaction(function () use ($user, $data): Review {
            $item = OrderItem::query()
                ->whereKey($data['order_item_id'])
                ->where('status', 'delivered')
                ->whereHas(
                    'order',
                    fn ($query) => $query->where('user_id', $user->id)
                )
                ->with(['order', 'product', 'store'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $item->product_id) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'The product is no longer available for review.',
                ]);
            }

            if (Review::query()->where('order_item_id', $item->id)->exists()) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'This order item has already been reviewed.',
                ]);
            }

            return Review::query()->create([
                'user_id' => $user->id,
                'product_id' => $item->product_id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'store_id' => $item->store_id,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'],
                'status' => 'published',
            ])->fresh(['user', 'product', 'store']);
        });
    }

    public function update(
        User $user,
        int $id,
        array $data
    ): Review {
        $review = Review::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $review->update(array_merge($data, [
            'status' => 'published',
            'moderation_note' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]));

        return $review->fresh(['user', 'product', 'store']);
    }

    public function delete(User $user, int $id): void
    {
        Review::query()
            ->where('user_id', $user->id)
            ->findOrFail($id)
            ->delete();
    }

    public function sellerReviews(
        Seller $seller,
        int $perPage,
        ?string $status = null
    ): LengthAwarePaginator {
        return Review::query()
            ->whereHas(
                'store',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['user:id,name', 'product:id,title,slug', 'store:id,name'])
            ->latest()
            ->paginate($perPage);
    }

    public function sellerReply(
        Seller $seller,
        User $sellerUser,
        int $reviewId,
        string $reply
    ): Review {
        $review = Review::query()
            ->whereHas(
                'store',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->with('user')
            ->findOrFail($reviewId);

        $review->update([
            'seller_reply' => $reply,
            'seller_replied_at' => now(),
        ]);

        if ($review->user) {
            $this->notifications->notifyUser(
                $review->user,
                'Seller replied to your review',
                $reply,
                'review_reply',
                ['review_id' => $review->id, 'product_id' => $review->product_id]
            );
        }

        return $review->fresh(['user', 'product', 'store']);
    }

    public function moderate(
        User $admin,
        int $reviewId,
        string $status,
        ?string $note
    ): Review {
        $review = Review::query()->findOrFail($reviewId);

        $review->update([
            'status' => $status,
            'moderated_by' => $admin->id,
            'moderation_note' => $note,
            'moderated_at' => now(),
        ]);

        return $review->fresh(['user', 'product', 'store', 'moderator']);
    }
}
