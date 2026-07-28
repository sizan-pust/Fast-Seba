<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrescriptionResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\ProductFaq;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\SupportTicketType;
use App\Models\SystemAuditLog;
use App\Services\AuditService;
use App\Services\PrescriptionService;
use App\Services\ReferralService;
use App\Services\ReviewService;
use App\Services\SupportService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportPharmacyApiController extends Controller
{
    public function __construct(
        protected SupportService $support,
        protected PrescriptionService $prescriptions,
        protected ReviewService $reviews,
        protected ReferralService $referrals,
        protected AuditService $audit
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Support and pharmacy dashboard fetched.',
            [
                'support' => [
                    'open' => SupportTicket::query()
                        ->whereIn('status', ['open', 'reopen'])
                        ->count(),
                    'in_progress' => SupportTicket::query()
                        ->where('status', 'in_progress')
                        ->count(),
                    'resolved' => SupportTicket::query()
                        ->where('status', 'resolved')
                        ->count(),
                ],
                'prescriptions' => [
                    'pending' => \App\Models\Prescription::query()
                        ->where('status', 'pending')
                        ->count(),
                    'under_review' => \App\Models\Prescription::query()
                        ->where('status', 'under_review')
                        ->count(),
                    'approved' => \App\Models\Prescription::query()
                        ->where('status', 'approved')
                        ->count(),
                ],
                'reviews' => [
                    'published' => Review::query()
                        ->where('status', 'published')
                        ->count(),
                    'hidden' => Review::query()
                        ->where('status', 'hidden')
                        ->count(),
                ],
                'referrals' => [
                    'active' => Referral::query()
                        ->where('status', 'active')
                        ->count(),
                    'completed' => Referral::query()
                        ->where('status', 'completed')
                        ->count(),
                ],
            ]
        );
    }

    public function supportTypes(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket types fetched.',
            SupportTicketType::query()
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function createSupportType(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = SupportTicketType::query()->create($data);

        $this->audit->record(
            $request->user(),
            'support_type.created',
            SupportTicketType::class,
            $type->id,
            null,
            $type->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket type created.',
            $type,
            201
        );
    }

    public function updateSupportType(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $type = SupportTicketType::query()->findOrFail($id);
        $before = $type->toArray();

        $type->update($request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]));

        $this->audit->record(
            $request->user(),
            'support_type.updated',
            SupportTicketType::class,
            $type->id,
            $before,
            $type->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket type updated.',
            $type->fresh()
        );
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = $this->support->adminTickets(
            min(100, max(1, (int) $request->input('per_page', 20))),
            $request->input('status'),
            $request->input('priority')
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin support tickets fetched.',
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

    public function supportTicket(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $ticket = SupportTicket::query()
            ->with(['type', 'user', 'order', 'assignee', 'messages.sender'])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin support ticket fetched.',
            new SupportTicketResource($ticket)
        );
    }

    public function replySupportTicket(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,txt',
                'max:10240',
            ],
        ]);

        $ticket = $this->support->adminReply(
            $request->user(),
            $id,
            $data['message'],
            (bool) ($data['is_internal'] ?? false),
            $request->file('attachments', [])
        );

        $this->audit->record(
            $request->user(),
            'support_ticket.replied',
            SupportTicket::class,
            $ticket->id,
            null,
            ['status' => $ticket->status],
            metadata: ['internal' => (bool) ($data['is_internal'] ?? false)]
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Support reply added.',
            new SupportTicketResource($ticket)
        );
    }

    public function updateSupportTicket(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'open',
                    'in_progress',
                    'reopen',
                    'pending_review',
                    'resolved',
                    'closed',
                ]),
            ],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $before = SupportTicket::query()->findOrFail($id)->toArray();

        $ticket = $this->support->updateStatus(
            $request->user(),
            $id,
            $data['status'],
            $data['assigned_to'] ?? null
        );

        $this->audit->record(
            $request->user(),
            'support_ticket.status_updated',
            SupportTicket::class,
            $ticket->id,
            $before,
            $ticket->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Support ticket updated.',
            new SupportTicketResource($ticket)
        );
    }

    public function prescriptions(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = $this->prescriptions->adminPrescriptions(
            min(100, max(1, (int) $request->input('per_page', 20))),
            $request->input('status'),
            $request->filled('seller_id')
                ? $request->integer('seller_id')
                : null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin prescriptions fetched.',
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

    public function prescription(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $prescription = \App\Models\Prescription::query()
            ->with(['items', 'user', 'seller', 'store', 'reviewer', 'order'])
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin prescription fetched.',
            new PrescriptionResource($prescription)
        );
    }

    public function reviewPrescription(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['under_review', 'approved', 'rejected']),
            ],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
            'rejection_reason' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $before = \App\Models\Prescription::query()
            ->findOrFail($id)
            ->toArray();

        $prescription = $this->prescriptions->review(
            $request->user(),
            $id,
            $data
        );

        $this->audit->record(
            $request->user(),
            'prescription.reviewed',
            \App\Models\Prescription::class,
            $prescription->id,
            $before,
            $prescription->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Prescription review updated.',
            new PrescriptionResource($prescription)
        );
    }

    public function reviews(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Review::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->when(
                $request->filled('rating'),
                fn ($query) => $query->where(
                    'rating',
                    $request->integer('rating')
                )
            )
            ->with(['user', 'product', 'store', 'moderator'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin reviews fetched.',
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

    public function moderateReview(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['published', 'hidden', 'rejected']),
            ],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $before = Review::query()->findOrFail($id)->toArray();

        $review = $this->reviews->moderate(
            $request->user(),
            $id,
            $data['status'],
            $data['note'] ?? null
        );

        $this->audit->record(
            $request->user(),
            'review.moderated',
            Review::class,
            $review->id,
            $before,
            $review->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Review moderation updated.',
            new ReviewResource($review)
        );
    }

    public function deleteReview(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $review = Review::query()->findOrFail($id);
        $before = $review->toArray();
        $review->delete();

        $this->audit->record(
            $request->user(),
            'review.deleted',
            Review::class,
            $id,
            $before,
            null
        );

        return ApiResponseType::sendJsonResponse(true, 'Review deleted.', []);
    }

    public function productFaqs(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = ProductFaq::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->with(['product', 'asker', 'answerer'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin product FAQs fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    public function moderateProductFaq(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['pending', 'active', 'inactive', 'rejected']),
            ],
            'answer' => ['nullable', 'string', 'max:5000'],
        ]);

        $faq = ProductFaq::query()->findOrFail($id);
        $before = $faq->toArray();

        $updates = ['status' => $data['status']];

        if (array_key_exists('answer', $data)) {
            $updates['answer'] = $data['answer'];
            $updates['answered_by'] = $request->user()->id;
            $updates['answered_at'] = now();
        }

        $faq->update($updates);

        $this->audit->record(
            $request->user(),
            'product_faq.moderated',
            ProductFaq::class,
            $faq->id,
            $before,
            $faq->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Product FAQ moderation updated.',
            $faq->fresh(['product', 'asker', 'answerer'])
        );
    }

    public function referrals(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Referral::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->with(['referrer:id,name,email', 'referred:id,name,email', 'earnings'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin referrals fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    public function referralEarnings(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = ReferralEarning::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->with(['beneficiary:id,name,email', 'order:id,slug', 'referral'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Referral earnings fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    public function syncReferrals(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $result = $this->referrals->syncDeliveredOrders();

        $this->audit->record(
            $request->user(),
            'referral.sync',
            Referral::class,
            null,
            null,
            $result
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Referral rewards synchronized.',
            $result
        );
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = SystemAuditLog::query()
            ->when(
                $request->filled('action'),
                fn ($query) => $query->where(
                    'action',
                    $request->string('action')->toString()
                )
            )
            ->when(
                $request->filled('actor_id'),
                fn ($query) => $query->where(
                    'actor_id',
                    $request->integer('actor_id')
                )
            )
            ->when(
                $request->filled('entity_type'),
                fn ($query) => $query->where(
                    'entity_type',
                    $request->string('entity_type')->toString()
                )
            )
            ->with('actor:id,name,email')
            ->latest('created_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 50))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Audit logs fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    private function ensureAdmin(Request $request): void
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless($panel === GuardNameEnum::ADMIN->value, 403);
    }
}
