<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\PrescriptionResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\GiftCardRedemption;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\SupportTicketType;
use App\Services\GiftCardService;
use App\Services\NotificationInboxService;
use App\Services\PrescriptionService;
use App\Services\ReferralService;
use App\Services\ReviewService;
use App\Services\SupportService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserEngagementApiController extends Controller
{
    public function __construct(
        protected NotificationInboxService $notifications,
        protected ReviewService $reviews,
        protected SupportService $support,
        protected PrescriptionService $prescriptions,
        protected ReferralService $referrals,
        protected GiftCardService $giftCards
    ) {
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = $this->notifications->inbox(
            $request->user(),
            min(100, max(1, (int) $request->input('per_page', 20)))
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Notifications fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'unread_count' => $this->notifications->unreadCount($request->user()),
                'data' => NotificationResource::collection($items->items())
                    ->resolve($request),
            ]
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Unread notification count fetched.',
            ['count' => $this->notifications->unreadCount($request->user())]
        );
    }

    public function markNotification(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'is_read' => ['required', 'boolean'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Notification state updated.',
            new NotificationResource(
                $this->notifications->mark(
                    $request->user(),
                    $id,
                    (bool) $data['is_read']
                )
            )
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'All notifications marked as read.',
            ['updated' => $this->notifications->markAllRead($request->user())]
        );
    }

    public function availableReviews(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Reviewable order items fetched.',
            $this->reviews->availableItems($request->user())
        );
    }

    public function createReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:3000'],
            'review_images' => ['nullable', 'array', 'max:5'],
            'review_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $review = $this->reviews->create(
            $request->user(),
            collect($data)->except('review_images')->all()
        );

        foreach ($request->file('review_images', []) as $image) {
            $review->addMedia($image)
                ->toMediaCollection('review_images');
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Review submitted.',
            new ReviewResource($review->fresh(['user', 'product', 'store'])),
            201
        );
    }

    public function updateReview(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comment' => ['sometimes', 'string', 'max:3000'],
            'review_images' => ['nullable', 'array', 'max:5'],
            'review_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $review = $this->reviews->update(
            $request->user(),
            $id,
            collect($data)->except('review_images')->all()
        );

        foreach ($request->file('review_images', []) as $image) {
            $review->addMedia($image)
                ->toMediaCollection('review_images');
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Review updated.',
            new ReviewResource($review->fresh(['user', 'product', 'store']))
        );
    }

    public function deleteReview(
        Request $request,
        int $id
    ): JsonResponse {
        $this->reviews->delete($request->user(), $id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Review deleted.',
            []
        );
    }

    public function askProductQuestion(
        Request $request,
        string $slug
    ): JsonResponse {
        $product = Product::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $faq = ProductFaq::query()->create([
            'product_id' => $product->id,
            'asked_by' => $request->user()->id,
            'question' => $data['question'],
            'status' => 'pending',
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Question submitted.',
            $faq,
            201
        );
    }

    public function supportTypes(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket types fetched.',
            SupportTicketType::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $items = $this->support->userTickets(
            $request->user(),
            min(100, max(1, (int) $request->input('per_page', 15)))
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Support tickets fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => SupportTicketResource::collection($items->items())
                    ->resolve($request),
            ]
        );
    }

    public function createSupportTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_type_id' => [
                'required',
                'integer',
                'exists:support_ticket_types,id',
            ],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'subject' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => [
                'nullable',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,txt',
                'max:10240',
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket created.',
            new SupportTicketResource(
                $this->support->create(
                    $request->user(),
                    $data,
                    $request->file('attachments', [])
                )
            ),
            201
        );
    }

    public function supportTicket(
        Request $request,
        string $uuid
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket fetched.',
            new SupportTicketResource(
                $this->support->userTicket($request->user(), $uuid)
            )
        );
    }

    public function replySupportTicket(
        Request $request,
        string $uuid
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,txt',
                'max:10240',
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Support reply added.',
            new SupportTicketResource(
                $this->support->userReply(
                    $request->user(),
                    $uuid,
                    $data['message'],
                    $request->file('attachments', [])
                )
            )
        );
    }

    public function closeSupportTicket(
        Request $request,
        string $uuid
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket closed.',
            new SupportTicketResource(
                $this->support->close($request->user(), $uuid)
            )
        );
    }

    public function prescriptions(Request $request): JsonResponse
    {
        $items = $this->prescriptions->userPrescriptions(
            $request->user(),
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->input('status')
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Prescriptions fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => PrescriptionResource::collection($items->items())
                    ->resolve($request),
            ]
        );
    }

    public function createPrescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_age' => ['nullable', 'integer', 'between:0,120'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_registration_no' => ['nullable', 'string', 'max:255'],
            'prescribed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.medicine_name' => ['required', 'string', 'max:255'],
            'items.*.strength' => ['nullable', 'string', 'max:100'],
            'items.*.dosage' => ['nullable', 'string', 'max:255'],
            'items.*.duration' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
        ]);

        if (
            ! empty($data['order_id'])
            && ! \App\Models\Order::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($data['order_id'])
                ->exists()
        ) {
            abort(403);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Prescription uploaded.',
            new PrescriptionResource(
                $this->prescriptions->create(
                    $request->user(),
                    $data,
                    $request->file('files', [])
                )
            ),
            201
        );
    }

    public function prescription(
        Request $request,
        string $uuid
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Prescription fetched.',
            new PrescriptionResource(
                $this->prescriptions->userPrescription(
                    $request->user(),
                    $uuid
                )
            )
        );
    }

    public function cancelPrescription(
        Request $request,
        string $uuid
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Prescription cancelled.',
            new PrescriptionResource(
                $this->prescriptions->cancel($request->user(), $uuid)
            )
        );
    }

    public function referralInfo(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Referral information fetched.',
            $this->referrals->info($request->user())
        );
    }

    public function submitReferral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'referral_code' => ['required', 'string', 'max:32'],
        ]);

        $referral = $this->referrals->submit(
            $request->user(),
            $data['referral_code']
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Referral code applied.',
            [
                'id' => $referral->id,
                'referrer' => $referral->referrer?->name,
                'status' => $referral->status,
            ]
        );
    }

    public function referralEarnings(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Referral earnings fetched.',
            $this->referrals->earnings($request->user())
        );
    }

    public function dismissReferralPrompt(Request $request): JsonResponse
    {
        $request->user()
            ->forceFill(['referral_prompt_dismissed_at' => now()])
            ->save();

        return ApiResponseType::sendJsonResponse(
            true,
            'Referral prompt dismissed.',
            [
                'referral_prompt_dismissed_at' => now()->toIso8601String(),
            ]
        );
    }

    public function redeemGiftCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $redemption = $this->giftCards->redeem(
            $request->user(),
            $data['code']
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift card redeemed to wallet.',
            [
                'id' => $redemption->id,
                'code' => $redemption->giftCard?->code,
                'amount' => $redemption->amount,
                'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
            ],
            201
        );
    }

    public function giftCardHistory(Request $request): JsonResponse
    {
        $items = GiftCardRedemption::query()
            ->where('user_id', $request->user()->id)
            ->with('giftCard')
            ->latest('redeemed_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->giftCard?->code,
                'title' => $item->giftCard?->title,
                'amount' => $item->amount,
                'redeemed_at' => $item->redeemed_at?->toIso8601String(),
            ])
            ->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift card history fetched.',
            $items
        );
    }
}
