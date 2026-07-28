<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\PrescriptionResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\Seller;
use App\Services\NotificationInboxService;
use App\Services\PrescriptionService;
use App\Services\ReviewService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerEngagementApiController extends Controller
{
    public function __construct(
        protected NotificationInboxService $notifications,
        protected ReviewService $reviews,
        protected PrescriptionService $prescriptions
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = $this->notifications->inbox(
            $request->user(),
            min(100, max(1, (int) $request->input('per_page', 20)))
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller notifications fetched.',
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

    public function notificationCount(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Seller unread notification count fetched.',
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
            'Seller notification state updated.',
            new NotificationResource(
                $this->notifications->mark(
                    $request->user(),
                    $id,
                    (bool) $data['is_read']
                )
            )
        );
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Seller notifications marked as read.',
            ['updated' => $this->notifications->markAllRead($request->user())]
        );
    }

    public function reviews(Request $request): JsonResponse
    {
        $items = $this->reviews->sellerReviews(
            $this->seller($request),
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->input('status')
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller reviews fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => ReviewResource::collection($items->items())
                    ->resolve($request),
            ]
        );
    }

    public function replyReview(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:3000'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller reply saved.',
            new ReviewResource(
                $this->reviews->sellerReply(
                    $this->seller($request),
                    $request->user(),
                    $id,
                    $data['reply']
                )
            )
        );
    }

    public function productFaqs(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = ProductFaq::query()
            ->whereHas(
                'product',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->with(['product:id,title,slug', 'asker:id,name', 'answerer:id,name'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller product FAQs fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => collect($items->items())->map(fn ($faq) => [
                    'id' => $faq->id,
                    'product' => [
                        'id' => $faq->product?->id,
                        'title' => $faq->product?->title,
                        'slug' => $faq->product?->slug,
                    ],
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'status' => $faq->status,
                    'asked_by' => $faq->asker?->name,
                    'answered_by' => $faq->answerer?->name,
                    'answered_at' => $faq->answered_at?->toIso8601String(),
                    'created_at' => $faq->created_at?->toIso8601String(),
                ])->values(),
            ]
        );
    }

    public function createProductFaq(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:5000'],
        ]);

        Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($data['product_id']);

        $faq = ProductFaq::query()->create([
            'product_id' => $data['product_id'],
            'answered_by' => $request->user()->id,
            'question' => $data['question'],
            'answer' => $data['answer'],
            'status' => 'active',
            'answered_at' => now(),
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Product FAQ created.',
            $faq->fresh(['product', 'answerer']),
            201
        );
    }

    public function answerProductFaq(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->seller($request);

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $faq = ProductFaq::query()
            ->whereHas(
                'product',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->findOrFail($id);

        $faq->update([
            'answer' => $data['answer'],
            'status' => $data['status'] ?? 'active',
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Product FAQ answered.',
            $faq->fresh(['product', 'asker', 'answerer'])
        );
    }

    public function deleteProductFaq(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->seller($request);

        ProductFaq::query()
            ->whereHas(
                'product',
                fn ($query) => $query->where('seller_id', $seller->id)
            )
            ->findOrFail($id)
            ->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Product FAQ deleted.',
            []
        );
    }

    public function prescriptions(Request $request): JsonResponse
    {
        $items = $this->prescriptions->sellerPrescriptions(
            $this->seller($request),
            min(100, max(1, (int) $request->input('per_page', 15))),
            $request->input('status')
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Assigned prescriptions fetched.',
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

    public function fulfillPrescription(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Prescription marked as fulfilled.',
            new PrescriptionResource(
                $this->prescriptions->fulfill(
                    $this->seller($request),
                    $request->user(),
                    $id,
                    $data['note'] ?? null
                )
            )
        );
    }
}
