<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\DeliveryBoy;
use App\Models\FollowingSeller;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\DeliveryFeedback;
use App\Services\DeliveryCashFeedbackService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerFinalApiController extends Controller
{
    public function __construct(
        protected DeliveryCashFeedbackService $feedback
    ) {
    }

    public function followedSellers(Request $request): JsonResponse
    {
        $items = FollowingSeller::query()
            ->where('user_id', $request->user()->id)
            ->with('seller.stores')
            ->latest()
            ->get()
            ->map(fn ($follow) => [
                'seller_id' => $follow->seller_id,
                'business_name' =>
                    $follow->seller?->business_name,
                'verification_status' =>
                    $follow->seller?->verification_status,
                'stores' => $follow->seller?->stores
                    ->map(fn ($store) => [
                        'id' => $store->id,
                        'name' => $store->name,
                        'slug' => $store->slug,
                        'status' => $store->status,
                    ])->values(),
                'followed_at' =>
                    $follow->created_at?->toIso8601String(),
            ])->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Followed sellers fetched.',
            $items
        );
    }

    public function followSeller(
        Request $request,
        int $sellerId
    ): JsonResponse {
        Seller::query()
            ->where('verification_status', 'approved')
            ->where('status', 'active')
            ->findOrFail($sellerId);

        $follow = FollowingSeller::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'seller_id' => $sellerId,
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller followed.',
            [
                'seller_id' => $follow->seller_id,
                'following' => true,
            ],
            $follow->wasRecentlyCreated ? 201 : 200
        );
    }

    public function unfollowSeller(
        Request $request,
        int $sellerId
    ): JsonResponse {
        FollowingSeller::query()
            ->where('user_id', $request->user()->id)
            ->where('seller_id', $sellerId)
            ->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller unfollowed.',
            [
                'seller_id' => $sellerId,
                'following' => false,
            ]
        );
    }

    public function createSellerFeedback(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = $this->feedback->sellerFeedback(
            $request->user(),
            Order::query()->findOrFail($data['order_id']),
            Seller::query()->findOrFail($data['seller_id']),
            $data['rating'],
            $data['comment'] ?? null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback submitted.',
            $feedback,
            201
        );
    }

    public function createDeliveryFeedback(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'delivery_boy_id' => [
                'required',
                'integer',
                'exists:delivery_boys,id',
            ],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = $this->feedback->deliveryFeedback(
            $request->user(),
            Order::query()->findOrFail($data['order_id']),
            DeliveryBoy::query()->findOrFail(
                $data['delivery_boy_id']
            ),
            $data['rating'],
            $data['comment'] ?? null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery feedback submitted.',
            $feedback,
            201
        );
    }


    public function updateSellerFeedback(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = SellerFeedback::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $feedback->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => 'published',
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback updated.',
            $feedback->fresh()
        );
    }

    public function updateDeliveryFeedback(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = DeliveryFeedback::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $feedback->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => 'published',
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery feedback updated.',
            $feedback->fresh()
        );
    }

    public function mySellerFeedback(
        Request $request
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback fetched.',
            SellerFeedback::query()
                ->where('user_id', $request->user()->id)
                ->with('seller')
                ->latest()
                ->paginate(
                    min(100, max(1, (int) $request->input('per_page', 15)))
                )
        );
    }

    public function myDeliveryFeedback(
        Request $request
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery feedback fetched.',
            DeliveryFeedback::query()
                ->where('user_id', $request->user()->id)
                ->with('deliveryBoy.user')
                ->latest()
                ->paginate(
                    min(100, max(1, (int) $request->input('per_page', 15)))
                )
        );
    }
}
