<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\FeaturedSection;
use App\Models\GiftCard;
use App\Services\AuditService;
use App\Services\NotificationInboxService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminGrowthApiController extends Controller
{
    public function __construct(
        protected NotificationInboxService $notifications,
        protected AuditService $audit
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Growth dashboard fetched.',
            [
                'banners' => Banner::query()->count(),
                'active_banners' => Banner::query()
                    ->where('visibility_status', 'published')
                    ->count(),
                'featured_sections' => FeaturedSection::query()->count(),
                'active_featured_sections' => FeaturedSection::query()
                    ->where('status', 'active')
                    ->count(),
                'faqs' => Faq::query()->count(),
                'notification_campaigns' => AppNotification::query()->count(),
                'gift_cards' => GiftCard::query()->count(),
                'active_gift_cards' => GiftCard::query()
                    ->where('status', 'active')
                    ->count(),
            ]
        );
    }

    public function banners(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = Banner::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'visibility_status',
                    $request->string('status')->toString()
                )
            )
            ->when(
                $request->filled('position'),
                fn ($query) => $query->where(
                    'position',
                    $request->string('position')->toString()
                )
            )
            ->with(['zones', 'product', 'category', 'brand'])
            ->orderBy('display_order')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin banners fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => collect($items->items())
                    ->map(fn (Banner $banner) => $this->bannerPayload($banner))
                    ->values(),
            ]
        );
    }

    public function createBanner(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate($this->bannerRules());

        $zoneIds = $data['zone_ids'] ?? [];
        unset($data['zone_ids'], $data['image']);

        $banner = Banner::query()->create($data);
        $banner->zones()->sync($zoneIds);

        if ($request->hasFile('image')) {
            $banner
                ->addMediaFromRequest('image')
                ->toMediaCollection('banner_image');
        }

        $this->audit->record(
            $request->user(),
            'banner.created',
            Banner::class,
            $banner->id,
            null,
            $banner->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Banner created.',
            $this->bannerPayload($banner->fresh(['zones', 'product', 'category', 'brand'])),
            201
        );
    }

    public function updateBanner(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $banner = Banner::query()->with('zones')->findOrFail($id);
        $before = $banner->toArray();
        $data = $request->validate($this->bannerRules(true));

        $zoneIds = $data['zone_ids'] ?? null;
        unset($data['zone_ids'], $data['image']);

        $banner->update($data);

        if ($zoneIds !== null) {
            $banner->zones()->sync($zoneIds);
        }

        if ($request->hasFile('image')) {
            $banner
                ->addMediaFromRequest('image')
                ->toMediaCollection('banner_image');
        }

        $fresh = $banner->fresh(['zones', 'product', 'category', 'brand']);

        $this->audit->record(
            $request->user(),
            'banner.updated',
            Banner::class,
            $banner->id,
            $before,
            $fresh->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Banner updated.',
            $this->bannerPayload($fresh)
        );
    }

    public function deleteBanner(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $banner = Banner::query()->findOrFail($id);
        $before = $banner->toArray();
        $banner->delete();

        $this->audit->record(
            $request->user(),
            'banner.deleted',
            Banner::class,
            $id,
            $before,
            null
        );

        return ApiResponseType::sendJsonResponse(true, 'Banner deleted.', []);
    }

    public function featuredSections(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = FeaturedSection::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->with(['zones', 'products'])
            ->orderBy('sort_order')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin featured sections fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => collect($items->items())
                    ->map(fn (FeaturedSection $section) => $this->sectionPayload($section))
                    ->values(),
            ]
        );
    }

    public function createFeaturedSection(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate($this->sectionRules());

        $zoneIds = $data['zone_ids'] ?? [];
        $productIds = $data['product_ids'] ?? [];
        unset($data['zone_ids'], $data['product_ids'], $data['background_image']);

        $section = FeaturedSection::query()->create($data);
        $section->zones()->sync($zoneIds);
        $this->syncProducts($section, $productIds);

        if ($request->hasFile('background_image')) {
            $section
                ->addMediaFromRequest('background_image')
                ->toMediaCollection('featured_background');
        }

        $this->audit->record(
            $request->user(),
            'featured_section.created',
            FeaturedSection::class,
            $section->id,
            null,
            $section->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Featured section created.',
            $this->sectionPayload($section->fresh(['zones', 'products'])),
            201
        );
    }

    public function updateFeaturedSection(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $section = FeaturedSection::query()->with(['zones', 'products'])->findOrFail($id);
        $before = $section->toArray();
        $data = $request->validate($this->sectionRules(true));

        $zoneIds = $data['zone_ids'] ?? null;
        $productIds = $data['product_ids'] ?? null;
        unset($data['zone_ids'], $data['product_ids'], $data['background_image']);

        $section->update($data);

        if ($zoneIds !== null) {
            $section->zones()->sync($zoneIds);
        }

        if ($productIds !== null) {
            $this->syncProducts($section, $productIds);
        }

        if ($request->hasFile('background_image')) {
            $section
                ->addMediaFromRequest('background_image')
                ->toMediaCollection('featured_background');
        }

        $fresh = $section->fresh(['zones', 'products']);

        $this->audit->record(
            $request->user(),
            'featured_section.updated',
            FeaturedSection::class,
            $section->id,
            $before,
            $fresh->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Featured section updated.',
            $this->sectionPayload($fresh)
        );
    }

    public function deleteFeaturedSection(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $section = FeaturedSection::query()->findOrFail($id);
        $before = $section->toArray();
        $section->delete();

        $this->audit->record(
            $request->user(),
            'featured_section.deleted',
            FeaturedSection::class,
            $id,
            $before,
            null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Featured section deleted.',
            []
        );
    }

    public function faqs(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin FAQs fetched.',
            Faq::query()
                ->when(
                    $request->filled('category'),
                    fn ($query) => $query->where(
                        'category',
                        $request->string('category')->toString()
                    )
                )
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function createFaq(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate($this->faqRules());
        $faq = Faq::query()->create($data);

        $this->audit->record(
            $request->user(),
            'faq.created',
            Faq::class,
            $faq->id,
            null,
            $faq->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'FAQ created.',
            $faq,
            201
        );
    }

    public function updateFaq(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $faq = Faq::query()->findOrFail($id);
        $before = $faq->toArray();
        $faq->update($request->validate($this->faqRules(true)));

        $this->audit->record(
            $request->user(),
            'faq.updated',
            Faq::class,
            $faq->id,
            $before,
            $faq->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'FAQ updated.',
            $faq->fresh()
        );
    }

    public function deleteFaq(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $faq = Faq::query()->findOrFail($id);
        $before = $faq->toArray();
        $faq->delete();

        $this->audit->record(
            $request->user(),
            'faq.deleted',
            Faq::class,
            $id,
            $before,
            null
        );

        return ApiResponseType::sendJsonResponse(true, 'FAQ deleted.', []);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = AppNotification::query()
            ->with(['creator:id,name', 'zones'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Notification campaigns fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    public function broadcast(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'audience_type' => [
                'required',
                Rule::in(['all', 'customer', 'seller', 'delivery_boy', 'users']),
            ],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'target_type' => ['nullable', 'string', 'max:40'],
            'target_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:delivery_zones,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        $campaign = $this->notifications->broadcast(
            $request->user(),
            $data
        );

        $this->audit->record(
            $request->user(),
            'notification.broadcast',
            AppNotification::class,
            $campaign->id,
            null,
            $campaign->toArray(),
            metadata: ['recipient_count' => $campaign->users->count()]
        );

        return ApiResponseType::sendJsonResponse(
            true,
            $campaign->status === 'scheduled'
                ? 'Notification campaign scheduled.'
                : 'Notification broadcast completed.',
            [
                'id' => $campaign->id,
                'status' => $campaign->status,
                'recipient_count' => $campaign->users->count(),
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
            ],
            201
        );
    }

    public function giftCards(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = GiftCard::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->withCount('redemptions')
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift cards fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    public function createGiftCard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate($this->giftCardRules());
        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = $request->user()->id;
        $card = GiftCard::query()->create($data);

        $this->audit->record(
            $request->user(),
            'gift_card.created',
            GiftCard::class,
            $card->id,
            null,
            $card->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift card created.',
            $card,
            201
        );
    }

    public function updateGiftCard(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);
        $card = GiftCard::query()->findOrFail($id);
        $before = $card->toArray();
        $data = $request->validate($this->giftCardRules(true));

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $card->update($data);

        $this->audit->record(
            $request->user(),
            'gift_card.updated',
            GiftCard::class,
            $card->id,
            $before,
            $card->fresh()->toArray()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift card updated.',
            $card->fresh()
        );
    }

    private function bannerRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'type' => [$required, Rule::in(['custom', 'product', 'category', 'brand'])],
            'scope_type' => ['nullable', Rule::in(['global', 'category'])],
            'scope_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => [$required, 'string', 'max:255'],
            'custom_url' => ['nullable', 'url', 'max:1000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'position' => ['nullable', 'string', 'max:40'],
            'visibility_status' => [
                'nullable',
                Rule::in(['published', 'draft']),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:delivery_zones,id'],
            'metadata' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'max:8192'],
        ];
    }

    private function sectionRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'scope_type' => ['nullable', Rule::in(['global', 'category'])],
            'scope_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => [$required, 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'style' => ['nullable', 'string', 'max:40'],
            'section_type' => [
                $required,
                Rule::in(['manual', 'newly_added', 'top_rated', 'featured']),
            ],
            'background_type' => ['nullable', Rule::in(['image', 'color'])],
            'background_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'product_limit' => ['nullable', 'integer', 'between:1,50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:delivery_zones,id'],
            'product_ids' => ['nullable', 'array', 'max:100'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'metadata' => ['nullable', 'array'],
            'background_image' => ['nullable', 'image', 'max:8192'],
        ];
    }

    private function faqRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'category' => ['nullable', 'string', 'max:80'],
            'question' => [$required, 'string', 'max:1000'],
            'answer' => [$required, 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    private function giftCardRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        $unique = $update
            ? 'unique:gift_cards,code,'.request()->route('id')
            : 'unique:gift_cards,code';

        return [
            'code' => [$required, 'string', 'max:64', $unique],
            'title' => [$required, 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:1'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function syncProducts(
        FeaturedSection $section,
        array $productIds
    ): void {
        $sync = [];

        foreach (array_values(array_unique($productIds)) as $index => $productId) {
            $sync[(int) $productId] = ['sort_order' => $index];
        }

        $section->products()->sync($sync);
    }

    private function bannerPayload(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'type' => $banner->type,
            'scope_type' => $banner->scope_type,
            'scope_id' => $banner->scope_id,
            'title' => $banner->title,
            'slug' => $banner->slug,
            'custom_url' => $banner->custom_url,
            'position' => $banner->position,
            'visibility_status' => $banner->visibility_status,
            'display_order' => $banner->display_order,
            'image' => $banner->imageUrl(),
            'product_id' => $banner->product_id,
            'category_id' => $banner->category_id,
            'brand_id' => $banner->brand_id,
            'zone_ids' => $banner->zones->pluck('id')->values(),
            'starts_at' => $banner->starts_at?->toIso8601String(),
            'ends_at' => $banner->ends_at?->toIso8601String(),
        ];
    }

    private function sectionPayload(FeaturedSection $section): array
    {
        return [
            'id' => $section->id,
            'title' => $section->title,
            'slug' => $section->slug,
            'short_description' => $section->short_description,
            'style' => $section->style,
            'section_type' => $section->section_type,
            'status' => $section->status,
            'sort_order' => $section->sort_order,
            'product_limit' => $section->product_limit,
            'background_type' => $section->background_type,
            'background_color' => $section->background_color,
            'background_image' => $section->backgroundImageUrl(),
            'text_color' => $section->text_color,
            'zone_ids' => $section->zones->pluck('id')->values(),
            'product_ids' => $section->products->pluck('id')->values(),
            'starts_at' => $section->starts_at?->toIso8601String(),
            'ends_at' => $section->ends_at?->toIso8601String(),
        ];
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
