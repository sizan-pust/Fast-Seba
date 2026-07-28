<?php

namespace App\Services\Admin;

use App\Models\AdCampaign;
use App\Models\AppNotification;
use App\Models\Banner;
use App\Models\DeliveryBoyCashTransaction;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\DeliveryFeedback;
use App\Models\DeliveryZone;
use App\Models\Faq;
use App\Models\FeaturedSection;
use App\Models\GiftCard;
use App\Models\PaymentGatewayConfig;
use App\Models\PaymentIntent;
use App\Models\ProductFaq;
use App\Models\Promo;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\SellerWithdrawalRequest;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AdvertisingService;
use App\Services\AuditService;
use App\Services\DeliveryCashFeedbackService;
use App\Services\NotificationInboxService;
use App\Services\PaymentGatewayManagementService;
use App\Services\ReferralService;
use App\Services\ReviewService;
use App\Services\SellerFinanceService;
use App\Services\SupportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminEngagementFinanceService
{
    public const LIVE_MODULES = [
        'banners',
        'featured-sections',
        'promotions',
        'advertisements',
        'subscriptions',
        'gift-cards-referrals',
        'seller-statements',
        'seller-withdrawals',
        'rider-cash',
        'payment-gateways',
        'payment-intents',
        'support',
        'reviews',
        'notifications',
        'faqs',
    ];

    public function __construct(
        private readonly AdvertisingService $advertising,
        private readonly AuditService $audit,
        private readonly DeliveryCashFeedbackService $cash,
        private readonly NotificationInboxService $notifications,
        private readonly PaymentGatewayManagementService $gateways,
        private readonly ReferralService $referrals,
        private readonly ReviewService $reviews,
        private readonly SellerFinanceService $finance,
        private readonly SupportService $support
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(string $module, Request $request): array
    {
        $view = $this->resolveView($module, $request);
        $definition = $this->definition($module, $view);
        $query = ($definition['query'])();

        if ($request->filled('search')) {
            ($definition['search'])(
                $query,
                $request->string('search')->trim()->toString()
            );
        }

        foreach ($definition['filters'] as $filter) {
            $key = $filter['key'];

            if ($request->filled($key)) {
                ($filter['apply'])($query, $request->input($key));
            }
        }

        $records = $query
            ->paginate($this->perPage($request))
            ->withQueryString();

        $records->through($definition['row']);

        $tabs = $this->tabs($module);

        return [
            'module' => $module,
            'view' => $view,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'columns' => $definition['columns'],
            'records' => $records,
            'filters' => collect($definition['filters'])
                ->map(fn (array $filter) => collect($filter)
                    ->except('apply')
                    ->all())
                ->all(),
            'stats' => ($definition['stats'])(),
            'tabs' => $tabs,
            'canCreate' => in_array(
                $module,
                ['notifications', 'faqs'],
                true
            ) && in_array($view, ['default', 'general'], true),
            'canSync' => in_array(
                $module,
                ['seller-statements', 'gift-cards-referrals'],
                true
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(
        string $module,
        int $id,
        Request $request
    ): array {
        $view = $this->resolveView($module, $request);

        return match ($module) {
            'banners' => $this->bannerDetail($id),
            'featured-sections' => $this->featuredSectionDetail($id),
            'promotions' => $this->promotionDetail($id),
            'advertisements' => $this->advertisementDetail($id),
            'subscriptions' => $this->subscriptionDetail($id, $view),
            'gift-cards-referrals' => $this->giftReferralDetail($id, $view),
            'seller-statements' => $this->statementDetail($id),
            'seller-withdrawals' => $this->sellerWithdrawalDetail($id),
            'rider-cash' => $this->riderCashDetail($id, $view),
            'payment-gateways' => $this->gatewayDetail($id),
            'payment-intents' => $this->paymentDetail($id, $view),
            'support' => $this->supportDetail($id),
            'reviews' => $this->reviewDetail($id, $view),
            'notifications' => $this->notificationDetail($id),
            'faqs' => $this->faqDetail($id, $view),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function form(
        string $module,
        Request $request,
        ?int $id = null
    ): array {
        $view = $this->resolveView($module, $request);

        if ($module === 'notifications' && $id === null) {
            return [
                'module' => $module,
                'view' => $view,
                'formType' => 'notification',
                'title' => 'Create Notification Campaign',
                'model' => new AppNotification(),
                'isEdit' => false,
                'zones' => DeliveryZone::query()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'users' => User::query()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->limit(500)
                    ->get(['id', 'name', 'email']),
            ];
        }

        if ($module === 'faqs' && $view === 'general') {
            $model = $id
                ? Faq::query()->findOrFail($id)
                : new Faq();

            return [
                'module' => $module,
                'view' => $view,
                'formType' => 'faq',
                'title' => $id ? 'Edit FAQ' : 'Create FAQ',
                'model' => $model,
                'isEdit' => $id !== null,
                'zones' => collect(),
                'users' => collect(),
            ];
        }

        if ($module === 'payment-gateways' && $id !== null) {
            return [
                'module' => $module,
                'view' => $view,
                'formType' => 'gateway',
                'title' => 'Edit Payment Gateway',
                'model' => PaymentGatewayConfig::query()->findOrFail($id),
                'isEdit' => true,
                'zones' => collect(),
                'users' => collect(),
            ];
        }

        abort(404);
    }

    public function save(
        string $module,
        Request $request,
        User $admin,
        ?int $id = null
    ): Model {
        $view = $this->resolveView($module, $request);

        return match (true) {
            $module === 'notifications' && $id === null =>
                $this->saveNotification($request, $admin),
            $module === 'faqs' && $view === 'general' =>
                $this->saveFaq($request, $admin, $id),
            $module === 'payment-gateways' && $id !== null =>
                $this->saveGateway($request, $admin, $id),
            default => abort(404),
        };
    }

    public function delete(
        string $module,
        int $id,
        Request $request,
        User $admin
    ): void {
        $view = $this->resolveView($module, $request);

        if ($module !== 'faqs' || $view !== 'general') {
            throw ValidationException::withMessages([
                'delete' => 'This record cannot be deleted from this screen.',
            ]);
        }

        $faq = Faq::query()->findOrFail($id);
        $before = $faq->toArray();
        $faq->delete();

        $this->audit->record(
            $admin,
            'admin.faq.deleted',
            Faq::class,
            $id,
            $before,
            null,
            $request
        );
    }

    public function action(
        string $module,
        int $id,
        Request $request,
        User $admin
    ): void {
        $action = $request->string('action')->toString();
        $view = $this->resolveView($module, $request);

        match ($module) {
            'banners' => $this->toggleState(
                Banner::query()->findOrFail($id),
                'visibility_status',
                ['published', 'draft'],
                $request,
                $admin,
                'banner'
            ),
            'featured-sections' => $this->toggleState(
                FeaturedSection::query()->findOrFail($id),
                'status',
                ['active', 'inactive'],
                $request,
                $admin,
                'featured_section'
            ),
            'promotions' => $this->toggleState(
                Promo::query()->findOrFail($id),
                'status',
                ['active', 'inactive'],
                $request,
                $admin,
                'promotion'
            ),
            'advertisements' => $this->advertisementAction(
                $id,
                $action,
                $request,
                $admin
            ),
            'subscriptions' => $this->subscriptionAction(
                $id,
                $view,
                $request,
                $admin
            ),
            'gift-cards-referrals' => $this->giftCardAction(
                $id,
                $view,
                $request,
                $admin
            ),
            'seller-statements' => $this->statementAction(
                $id,
                $action,
                $admin
            ),
            'seller-withdrawals' => $this->sellerWithdrawalAction(
                $id,
                $action,
                $request,
                $admin
            ),
            'rider-cash' => $this->riderCashAction(
                $id,
                $view,
                $action,
                $request,
                $admin
            ),
            'support' => $this->supportAction(
                $id,
                $action,
                $request,
                $admin
            ),
            'reviews' => $this->reviewAction(
                $id,
                $view,
                $action,
                $request,
                $admin
            ),
            'faqs' => $this->faqAction(
                $id,
                $view,
                $request,
                $admin
            ),
            default => throw ValidationException::withMessages([
                'action' => 'This module does not support that action.',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function pageAction(
        string $module,
        Request $request
    ): array {
        $action = $request->string('action')->toString();

        if ($module === 'seller-statements' && $action === 'sync') {
            return $this->finance->sync();
        }

        if (
            $module === 'gift-cards-referrals'
            && $action === 'sync-referrals'
        ) {
            return $this->referrals->syncDeliveredOrders();
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported page action.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $module, string $view): array
    {
        return match ($module) {
            'banners' => $this->bannerDefinition(),
            'featured-sections' => $this->featuredDefinition(),
            'promotions' => $this->promotionDefinition(),
            'advertisements' => $this->advertisementDefinition(),
            'subscriptions' => $this->subscriptionDefinition($view),
            'gift-cards-referrals' => $this->giftReferralDefinition($view),
            'seller-statements' => $this->statementDefinition(),
            'seller-withdrawals' => $this->sellerWithdrawalDefinition(),
            'rider-cash' => $this->riderCashDefinition($view),
            'payment-gateways' => $this->gatewayDefinition(),
            'payment-intents' => $this->paymentDefinition($view),
            'support' => $this->supportDefinition(),
            'reviews' => $this->reviewDefinition($view),
            'notifications' => $this->notificationDefinition(),
            'faqs' => $this->faqDefinition($view),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function bannerDefinition(): array
    {
        return [
            'title' => 'Banners',
            'description' => 'Review campaign placement, scheduling, and zone coverage.',
            'query' => fn () => Banner::query()
                ->with(['zones', 'product', 'category', 'brand'])
                ->orderBy('display_order')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['published', 'draft'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('visibility_status', $value)
                ),
                $this->filterDefinition(
                    'type',
                    'Type',
                    ['custom', 'product', 'category', 'brand'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('type', $value)
                ),
                $this->filterDefinition(
                    'zone_id',
                    'Zone',
                    DeliveryZone::query()
                        ->where('status', 'active')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all(),
                    fn (Builder $query, mixed $value) =>
                        $query->whereHas(
                            'zones',
                            fn (Builder $zones) => $zones->whereKey($value)
                        ),
                    true
                ),
            ],
            'columns' => [
                ['key' => 'title', 'label' => 'Banner'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'position', 'label' => 'Position'],
                ['key' => 'zones', 'label' => 'Zones'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'starts', 'label' => 'Starts', 'type' => 'date'],
            ],
            'row' => fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => Str::headline($banner->type),
                'position' => Str::headline($banner->position),
                'zones' => $banner->zones->pluck('name')->implode(', ')
                    ?: 'All zones',
                'status' => $banner->visibility_status,
                'starts' => $banner->starts_at,
            ],
            'stats' => fn () => [
                'All banners' => Banner::query()->count(),
                'Published' => Banner::query()
                    ->where('visibility_status', 'published')
                    ->count(),
                'Draft' => Banner::query()
                    ->where('visibility_status', 'draft')
                    ->count(),
                'Scheduled' => Banner::query()
                    ->where('starts_at', '>', now())
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function featuredDefinition(): array
    {
        return [
            'title' => 'Featured Sections',
            'description' => 'Review homepage collections and product placement.',
            'query' => fn () => FeaturedSection::query()
                ->with(['zones', 'products'])
                ->orderBy('sort_order')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['active', 'inactive'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'section_type',
                    'Type',
                    ['manual', 'newly_added', 'top_rated', 'featured'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('section_type', $value)
                ),
            ],
            'columns' => [
                ['key' => 'title', 'label' => 'Section'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'style', 'label' => 'Style'],
                ['key' => 'products', 'label' => 'Products'],
                ['key' => 'zones', 'label' => 'Zones'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ],
            'row' => fn (FeaturedSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'type' => Str::headline($section->section_type),
                'style' => Str::headline($section->style),
                'products' => $section->products->count(),
                'zones' => $section->zones->pluck('name')->implode(', ')
                    ?: 'All zones',
                'status' => $section->status,
            ],
            'stats' => fn () => [
                'All sections' => FeaturedSection::query()->count(),
                'Active' => FeaturedSection::query()
                    ->where('status', 'active')
                    ->count(),
                'Manual' => FeaturedSection::query()
                    ->where('section_type', 'manual')
                    ->count(),
                'Scheduled' => FeaturedSection::query()
                    ->where('starts_at', '>', now())
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function promotionDefinition(): array
    {
        return [
            'title' => 'Promotions',
            'description' => 'Review promo codes, limits, and active periods.',
            'query' => fn () => Promo::query()->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['active', 'inactive'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'discount_type',
                    'Discount type',
                    ['fixed', 'percentage'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('discount_type', $value)
                ),
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'discount', 'label' => 'Discount'],
                ['key' => 'mode', 'label' => 'Mode'],
                ['key' => 'usage', 'label' => 'Usage'],
                ['key' => 'minimum', 'label' => 'Minimum', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'ends', 'label' => 'Ends', 'type' => 'date'],
            ],
            'row' => fn (Promo $promo) => [
                'id' => $promo->id,
                'code' => $promo->code,
                'discount' => $promo->discount_type === 'percentage'
                    ? number_format((float) $promo->discount_amount, 2).'%'
                    : '৳'.number_format((float) $promo->discount_amount, 2),
                'mode' => Str::headline($promo->promo_mode),
                'usage' => $promo->usage_count.'/'.($promo->max_total_usage ?? '∞'),
                'minimum' => (float) $promo->min_order_total,
                'status' => $promo->status,
                'ends' => $promo->end_date,
            ],
            'stats' => fn () => [
                'All promotions' => Promo::query()->count(),
                'Active' => Promo::query()->where('status', 'active')->count(),
                'Expired' => Promo::query()
                    ->where('end_date', '<', now())
                    ->count(),
                'Total uses' => (int) Promo::query()->sum('usage_count'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function advertisementDefinition(): array
    {
        return [
            'title' => 'Advertisements',
            'description' => 'Approve seller campaigns and monitor advertising spend.',
            'query' => fn () => AdCampaign::query()
                ->with(['seller', 'store', 'product'])
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    $this->distinctValues(AdCampaign::class, 'status'),
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'placement',
                    'Placement',
                    $this->distinctValues(AdCampaign::class, 'placement'),
                    fn (Builder $query, mixed $value) =>
                        $query->where('placement', $value)
                ),
            ],
            'columns' => [
                ['key' => 'campaign', 'label' => 'Campaign'],
                ['key' => 'seller', 'label' => 'Seller'],
                ['key' => 'placement', 'label' => 'Placement'],
                ['key' => 'budget', 'label' => 'Budget', 'type' => 'money'],
                ['key' => 'spent', 'label' => 'Spent', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'starts', 'label' => 'Starts', 'type' => 'date'],
            ],
            'row' => fn (AdCampaign $campaign) => [
                'id' => $campaign->id,
                'campaign' => $campaign->title,
                'seller' => $campaign->seller?->business_name ?? '—',
                'placement' => Str::headline($campaign->placement),
                'budget' => (float) $campaign->budget,
                'spent' => (float) $campaign->spent_amount,
                'status' => $campaign->status,
                'starts' => $campaign->starts_at,
            ],
            'stats' => fn () => [
                'All campaigns' => AdCampaign::query()->count(),
                'Pending approval' => AdCampaign::query()
                    ->where('status', 'pending_approval')
                    ->count(),
                'Active' => AdCampaign::query()
                    ->whereIn('status', ['approved', 'active'])
                    ->count(),
                'Total spent' => (float) AdCampaign::query()
                    ->sum('spent_amount'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionDefinition(string $view): array
    {
        if ($view === 'subscribers') {
            return [
                'title' => 'Seller Subscriptions',
                'description' => 'Review seller plan assignments and renewal state.',
                'query' => fn () => SellerSubscription::query()
                    ->with(['seller', 'plan'])
                    ->latest('starts_at'),
                'search' => fn (Builder $query, string $search) => $query
                    ->whereHas(
                        'seller',
                        fn (Builder $seller) => $seller->where(
                            'business_name',
                            'like',
                            '%'.$search.'%'
                        )
                    ),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        ['trial', 'active', 'expired', 'cancelled'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'seller', 'label' => 'Seller'],
                    ['key' => 'plan', 'label' => 'Plan'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'starts', 'label' => 'Starts', 'type' => 'date'],
                    ['key' => 'ends', 'label' => 'Ends', 'type' => 'date'],
                    ['key' => 'renew', 'label' => 'Auto renew'],
                ],
                'row' => fn (SellerSubscription $subscription) => [
                    'id' => $subscription->id,
                    'seller' => $subscription->seller?->business_name ?? '—',
                    'plan' => $subscription->plan?->title ?? '—',
                    'status' => $subscription->status,
                    'starts' => $subscription->starts_at,
                    'ends' => $subscription->ends_at,
                    'renew' => $subscription->auto_renew ? 'Yes' : 'No',
                ],
                'stats' => fn () => [
                    'All subscribers' => SellerSubscription::query()->count(),
                    'Active' => SellerSubscription::query()
                        ->where('status', 'active')
                        ->count(),
                    'Trial' => SellerSubscription::query()
                        ->where('status', 'trial')
                        ->count(),
                    'Expired' => SellerSubscription::query()
                        ->where('status', 'expired')
                        ->count(),
                ],
            ];
        }

        return [
            'title' => 'Subscription Plans',
            'description' => 'Review seller plan pricing and feature limits.',
            'query' => fn () => SubscriptionPlan::query()
                ->withCount('limits')
                ->orderBy('sort_order'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['active', 'inactive'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
            ],
            'columns' => [
                ['key' => 'plan', 'label' => 'Plan'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'money'],
                ['key' => 'duration', 'label' => 'Duration'],
                ['key' => 'trial', 'label' => 'Trial'],
                ['key' => 'limits', 'label' => 'Limits'],
                ['key' => 'featured', 'label' => 'Featured'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ],
            'row' => fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'plan' => $plan->title,
                'price' => (float) $plan->price,
                'duration' => $plan->duration_days.' days',
                'trial' => $plan->trial_days.' days',
                'limits' => $plan->limits_count,
                'featured' => $plan->is_featured ? 'Yes' : 'No',
                'status' => $plan->status,
            ],
            'stats' => fn () => [
                'All plans' => SubscriptionPlan::query()->count(),
                'Active' => SubscriptionPlan::query()
                    ->where('status', 'active')
                    ->count(),
                'Featured' => SubscriptionPlan::query()
                    ->where('is_featured', true)
                    ->count(),
                'Subscribers' => SellerSubscription::query()->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function giftReferralDefinition(string $view): array
    {
        if ($view === 'referrals') {
            return [
                'title' => 'Referrals',
                'description' => 'Track referral completion and reward settlement.',
                'query' => fn () => Referral::query()
                    ->with(['referrer', 'referred'])
                    ->withCount('earnings')
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->where('referral_code', 'like', '%'.$search.'%'),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        $this->distinctValues(Referral::class, 'status'),
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'referrer', 'label' => 'Referrer'],
                    ['key' => 'referred', 'label' => 'Referred user'],
                    ['key' => 'earnings', 'label' => 'Earnings'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'completed', 'label' => 'Completed', 'type' => 'date'],
                ],
                'row' => fn (Referral $referral) => [
                    'id' => $referral->id,
                    'code' => $referral->referral_code,
                    'referrer' => $referral->referrer?->name ?? '—',
                    'referred' => $referral->referred?->name ?? '—',
                    'earnings' => $referral->earnings_count,
                    'status' => $referral->status,
                    'completed' => $referral->completed_at,
                ],
                'stats' => fn () => [
                    'All referrals' => Referral::query()->count(),
                    'Pending' => Referral::query()
                        ->where('status', 'pending')
                        ->count(),
                    'Completed' => Referral::query()
                        ->where('status', 'completed')
                        ->count(),
                    'Rewarded' => Referral::query()
                        ->whereNotNull('rewarded_at')
                        ->count(),
                ],
            ];
        }

        if ($view === 'earnings') {
            return [
                'title' => 'Referral Earnings',
                'description' => 'Audit referral bonuses and settlement status.',
                'query' => fn () => ReferralEarning::query()
                    ->with(['beneficiary', 'order'])
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->whereHas(
                        'beneficiary',
                        fn (Builder $user) => $user->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                    ),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        $this->distinctValues(ReferralEarning::class, 'status'),
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                    $this->filterDefinition(
                        'beneficiary_type',
                        'Beneficiary',
                        ['referrer', 'referred'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('beneficiary_type', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'earning', 'label' => 'Earning'],
                    ['key' => 'beneficiary', 'label' => 'Beneficiary'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'order', 'label' => 'Order'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'settled', 'label' => 'Settled', 'type' => 'date'],
                ],
                'row' => fn (ReferralEarning $earning) => [
                    'id' => $earning->id,
                    'earning' => '#'.$earning->id,
                    'beneficiary' => $earning->beneficiary?->name ?? '—',
                    'type' => Str::headline($earning->beneficiary_type),
                    'order' => $earning->order?->slug ?? '—',
                    'amount' => (float) $earning->earned_amount,
                    'status' => $earning->status,
                    'settled' => $earning->settled_at,
                ],
                'stats' => fn () => [
                    'All earnings' => ReferralEarning::query()->count(),
                    'Settled amount' => (float) ReferralEarning::query()
                        ->where('status', 'settled')
                        ->sum('earned_amount'),
                    'Pending amount' => (float) ReferralEarning::query()
                        ->where('status', 'pending')
                        ->sum('earned_amount'),
                    'Beneficiaries' => ReferralEarning::query()
                        ->distinct('beneficiary_id')
                        ->count('beneficiary_id'),
                ],
            ];
        }

        return [
            'title' => 'Gift Cards',
            'description' => 'Review wallet-credit gift cards and redemption.',
            'query' => fn () => GiftCard::query()
                ->withCount('redemptions')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['active', 'inactive'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'redemptions', 'label' => 'Redemptions'],
                ['key' => 'limit', 'label' => 'Limit'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'ends', 'label' => 'Ends', 'type' => 'date'],
            ],
            'row' => fn (GiftCard $card) => [
                'id' => $card->id,
                'code' => $card->code,
                'title' => $card->title,
                'amount' => (float) $card->amount,
                'redemptions' => $card->redemptions_count,
                'limit' => $card->max_redemptions ?? '∞',
                'status' => $card->status,
                'ends' => $card->ends_at,
            ],
            'stats' => fn () => [
                'All gift cards' => GiftCard::query()->count(),
                'Active' => GiftCard::query()->where('status', 'active')->count(),
                'Redemptions' => (int) DB::table('gift_card_redemptions')->count(),
                'Redeemed value' => (float) DB::table('gift_card_redemptions')
                    ->sum('amount'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function statementDefinition(): array
    {
        return [
            'title' => 'Seller Statements',
            'description' => 'Settle seller earning, commission, refund, and adjustment entries.',
            'query' => fn () => SellerStatement::query()
                ->with(['seller', 'order'])
                ->latest('posted_at'),
            'search' => fn (Builder $query, string $search) => $query
                ->whereHas(
                    'seller',
                    fn (Builder $seller) => $seller->where(
                        'business_name',
                        'like',
                        '%'.$search.'%'
                    )
                ),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Settlement',
                    ['unsettled', 'settled'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('settlement_status', $value)
                ),
                $this->filterDefinition(
                    'direction',
                    'Direction',
                    ['credit', 'debit'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('direction', $value)
                ),
                $this->filterDefinition(
                    'seller_id',
                    'Seller',
                    Seller::query()
                        ->orderBy('business_name')
                        ->pluck('business_name', 'id')
                        ->all(),
                    fn (Builder $query, mixed $value) =>
                        $query->where('seller_id', $value),
                    true
                ),
            ],
            'columns' => [
                ['key' => 'statement', 'label' => 'Statement'],
                ['key' => 'seller', 'label' => 'Seller'],
                ['key' => 'entry', 'label' => 'Entry'],
                ['key' => 'direction', 'label' => 'Direction', 'type' => 'status'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Settlement', 'type' => 'status'],
                ['key' => 'posted', 'label' => 'Posted', 'type' => 'date'],
            ],
            'row' => fn (SellerStatement $statement) => [
                'id' => $statement->id,
                'statement' => '#'.$statement->id,
                'seller' => $statement->seller?->business_name ?? '—',
                'entry' => Str::headline($statement->entry_type),
                'direction' => $statement->direction,
                'amount' => (float) $statement->amount,
                'status' => $statement->settlement_status,
                'posted' => $statement->posted_at,
            ],
            'stats' => fn () => [
                'All statements' => SellerStatement::query()->count(),
                'Unsettled credit' => (float) SellerStatement::query()
                    ->where('settlement_status', 'unsettled')
                    ->where('direction', 'credit')
                    ->sum('amount'),
                'Unsettled debit' => (float) SellerStatement::query()
                    ->where('settlement_status', 'unsettled')
                    ->where('direction', 'debit')
                    ->sum('amount'),
                'Settled' => SellerStatement::query()
                    ->where('settlement_status', 'settled')
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function sellerWithdrawalDefinition(): array
    {
        return [
            'title' => 'Seller Withdrawals',
            'description' => 'Approve or reject blocked seller wallet withdrawals.',
            'query' => fn () => SellerWithdrawalRequest::query()
                ->with(['seller.owner', 'processedBy'])
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->whereHas(
                    'seller',
                    fn (Builder $seller) => $seller->where(
                        'business_name',
                        'like',
                        '%'.$search.'%'
                    )
                ),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['pending', 'approved', 'rejected'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
            ],
            'columns' => [
                ['key' => 'request', 'label' => 'Request'],
                ['key' => 'seller', 'label' => 'Seller'],
                ['key' => 'owner', 'label' => 'Owner'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'processed', 'label' => 'Processed', 'type' => 'date'],
                ['key' => 'created', 'label' => 'Requested', 'type' => 'date'],
            ],
            'row' => fn (SellerWithdrawalRequest $withdrawal) => [
                'id' => $withdrawal->id,
                'request' => '#'.$withdrawal->id,
                'seller' => $withdrawal->seller?->business_name ?? '—',
                'owner' => $withdrawal->seller?->owner?->name
                    ?? $withdrawal->user?->name
                    ?? '—',
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'processed' => $withdrawal->processed_at,
                'created' => $withdrawal->created_at,
            ],
            'stats' => fn () => [
                'All requests' => SellerWithdrawalRequest::query()->count(),
                'Pending amount' => (float) SellerWithdrawalRequest::query()
                    ->where('status', 'pending')
                    ->sum('amount'),
                'Approved amount' => (float) SellerWithdrawalRequest::query()
                    ->where('status', 'approved')
                    ->sum('amount'),
                'Rejected' => SellerWithdrawalRequest::query()
                    ->where('status', 'rejected')
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function riderCashDefinition(string $view): array
    {
        if ($view === 'withdrawals') {
            return [
                'title' => 'Delivery Partner Withdrawals',
                'description' => 'Process delivery wallet withdrawal requests.',
                'query' => fn () => DeliveryBoyWithdrawalRequest::query()
                    ->with(['deliveryBoy.user', 'processedBy'])
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->whereHas(
                        'deliveryBoy.user',
                        fn (Builder $user) => $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('mobile', 'like', '%'.$search.'%')
                    ),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        ['pending', 'approved', 'rejected'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'request', 'label' => 'Request'],
                    ['key' => 'partner', 'label' => 'Delivery partner'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'processed', 'label' => 'Processed', 'type' => 'date'],
                    ['key' => 'created', 'label' => 'Requested', 'type' => 'date'],
                ],
                'row' => fn (DeliveryBoyWithdrawalRequest $withdrawal) => [
                    'id' => $withdrawal->id,
                    'request' => '#'.$withdrawal->id,
                    'partner' => $withdrawal->deliveryBoy?->user?->name ?? '—',
                    'amount' => (float) $withdrawal->amount,
                    'status' => $withdrawal->status,
                    'processed' => $withdrawal->processed_at,
                    'created' => $withdrawal->created_at,
                ],
                'stats' => fn () => [
                    'All requests' => DeliveryBoyWithdrawalRequest::query()->count(),
                    'Pending amount' => (float) DeliveryBoyWithdrawalRequest::query()
                        ->where('status', 'pending')
                        ->sum('amount'),
                    'Approved amount' => (float) DeliveryBoyWithdrawalRequest::query()
                        ->where('status', 'approved')
                        ->sum('amount'),
                    'Rejected' => DeliveryBoyWithdrawalRequest::query()
                        ->where('status', 'rejected')
                        ->count(),
                ],
            ];
        }

        return [
            'title' => 'Rider Cash Settlement',
            'description' => 'Review COD collection and remittance transactions.',
            'query' => fn () => DeliveryBoyCashTransaction::query()
                ->with(['deliveryBoy.user', 'order'])
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas(
                        'deliveryBoy.user',
                        fn (Builder $user) => $user->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                    )),
            'filters' => [
                $this->filterDefinition(
                    'type',
                    'Type',
                    $this->distinctValues(
                        DeliveryBoyCashTransaction::class,
                        'type'
                    ),
                    fn (Builder $query, mixed $value) =>
                        $query->where('type', $value)
                ),
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['pending', 'approved', 'rejected'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
            ],
            'columns' => [
                ['key' => 'transaction', 'label' => 'Transaction'],
                ['key' => 'partner', 'label' => 'Delivery partner'],
                ['key' => 'order', 'label' => 'Order'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            'row' => fn (DeliveryBoyCashTransaction $transaction) => [
                'id' => $transaction->id,
                'transaction' => '#'.$transaction->id,
                'partner' => $transaction->deliveryBoy?->user?->name ?? '—',
                'order' => $transaction->order?->slug ?? '—',
                'type' => Str::headline($transaction->type),
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created' => $transaction->created_at,
            ],
            'stats' => fn () => [
                'All transactions' => DeliveryBoyCashTransaction::query()->count(),
                'Pending amount' => (float) DeliveryBoyCashTransaction::query()
                    ->where('status', 'pending')
                    ->sum('amount'),
                'Approved remittance' => (float) DeliveryBoyCashTransaction::query()
                    ->where('type', 'remittance')
                    ->where('status', 'approved')
                    ->sum('amount'),
                'Collected cash' => (float) DeliveryBoyCashTransaction::query()
                    ->where('type', 'collection')
                    ->sum('amount'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function gatewayDefinition(): array
    {
        return [
            'title' => 'Payment Gateways',
            'description' => 'Manage public settings and encrypted credentials.',
            'query' => fn () => PaymentGatewayConfig::query()->orderBy('sort_order'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('display_name', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'enabled',
                    'Enabled',
                    [1 => 'Enabled', 0 => 'Disabled'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('enabled', (bool) $value),
                    true
                ),
            ],
            'columns' => [
                ['key' => 'gateway', 'label' => 'Gateway'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'status'],
                ['key' => 'mode', 'label' => 'Mode'],
                ['key' => 'currencies', 'label' => 'Currencies'],
                ['key' => 'secret', 'label' => 'Secret configured'],
                ['key' => 'order', 'label' => 'Order'],
            ],
            'row' => fn (PaymentGatewayConfig $gateway) => [
                'id' => $gateway->id,
                'gateway' => $gateway->display_name,
                'code' => $gateway->code,
                'enabled' => $gateway->enabled ? 'active' : 'inactive',
                'mode' => $gateway->test_mode ? 'Test' : 'Live',
                'currencies' => collect($gateway->supported_currencies ?? [])
                    ->implode(', ') ?: '—',
                'secret' => ! empty($gateway->secret_config) ? 'Yes' : 'No',
                'order' => $gateway->sort_order,
            ],
            'stats' => fn () => [
                'All gateways' => PaymentGatewayConfig::query()->count(),
                'Enabled' => PaymentGatewayConfig::query()
                    ->where('enabled', true)
                    ->count(),
                'Test mode' => PaymentGatewayConfig::query()
                    ->where('test_mode', true)
                    ->count(),
                'Live mode' => PaymentGatewayConfig::query()
                    ->where('test_mode', false)
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function paymentDefinition(string $view): array
    {
        if ($view === 'webhooks') {
            return [
                'title' => 'Webhook Events',
                'description' => 'Inspect provider callbacks and processing state.',
                'query' => fn () => WebhookEvent::query()->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->where(fn (Builder $nested) => $nested
                        ->where('event_id', 'like', '%'.$search.'%')
                        ->orWhere('event_type', 'like', '%'.$search.'%')
                        ->orWhere('provider', 'like', '%'.$search.'%')),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        $this->distinctValues(WebhookEvent::class, 'status'),
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                    $this->filterDefinition(
                        'provider',
                        'Provider',
                        $this->distinctValues(WebhookEvent::class, 'provider'),
                        fn (Builder $query, mixed $value) =>
                            $query->where('provider', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'event', 'label' => 'Event'],
                    ['key' => 'provider', 'label' => 'Provider'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'signature', 'label' => 'Signature'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'processed', 'label' => 'Processed', 'type' => 'date'],
                    ['key' => 'created', 'label' => 'Received', 'type' => 'date'],
                ],
                'row' => fn (WebhookEvent $event) => [
                    'id' => $event->id,
                    'event' => $event->event_id,
                    'provider' => $event->provider,
                    'type' => $event->event_type ?: '—',
                    'signature' => $event->signature_valid ? 'Valid' : 'Invalid',
                    'status' => $event->status,
                    'processed' => $event->processed_at,
                    'created' => $event->created_at,
                ],
                'stats' => fn () => [
                    'All events' => WebhookEvent::query()->count(),
                    'Processed' => WebhookEvent::query()
                        ->where('status', 'processed')
                        ->count(),
                    'Failed' => WebhookEvent::query()
                        ->where('status', 'failed')
                        ->count(),
                    'Invalid signatures' => WebhookEvent::query()
                        ->where('signature_valid', false)
                        ->count(),
                ],
            ];
        }

        return [
            'title' => 'Payment Intents',
            'description' => 'Track checkout intents and provider completion state.',
            'query' => fn () => PaymentIntent::query()
                ->with('order')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhere('external_id', 'like', '%'.$search.'%')
                    ->orWhere('provider', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    $this->distinctValues(PaymentIntent::class, 'status'),
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'provider',
                    'Provider',
                    $this->distinctValues(PaymentIntent::class, 'provider'),
                    fn (Builder $query, mixed $value) =>
                        $query->where('provider', $value)
                ),
            ],
            'columns' => [
                ['key' => 'intent', 'label' => 'Intent'],
                ['key' => 'provider', 'label' => 'Provider'],
                ['key' => 'purpose', 'label' => 'Purpose'],
                ['key' => 'order', 'label' => 'Order'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'expires', 'label' => 'Expires', 'type' => 'date'],
            ],
            'row' => fn (PaymentIntent $intent) => [
                'id' => $intent->id,
                'intent' => Str::upper(Str::substr($intent->uuid, 0, 12)),
                'provider' => $intent->provider,
                'purpose' => Str::headline($intent->purpose),
                'order' => $intent->order?->slug ?? '—',
                'amount' => (float) $intent->amount,
                'status' => $intent->status,
                'expires' => $intent->expires_at,
            ],
            'stats' => fn () => [
                'All intents' => PaymentIntent::query()->count(),
                'Pending' => PaymentIntent::query()
                    ->where('status', 'pending')
                    ->count(),
                'Completed' => PaymentIntent::query()
                    ->where('status', 'completed')
                    ->count(),
                'Failed' => PaymentIntent::query()
                    ->where('status', 'failed')
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function supportDefinition(): array
    {
        return [
            'title' => 'Support Tickets',
            'description' => 'Reply, assign, and resolve customer conversations.',
            'query' => fn () => SupportTicket::query()
                ->with(['type', 'user', 'assignee'])
                ->latest('last_replied_at')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas(
                        'user',
                        fn (Builder $user) => $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                    )),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    [
                        'open',
                        'in_progress',
                        'reopen',
                        'pending_review',
                        'resolved',
                        'closed',
                    ],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'priority',
                    'Priority',
                    ['low', 'normal', 'high', 'urgent'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('priority', $value)
                ),
                $this->filterDefinition(
                    'type_id',
                    'Ticket type',
                    SupportTicketType::query()
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->pluck('title', 'id')
                        ->all(),
                    fn (Builder $query, mixed $value) =>
                        $query->where('ticket_type_id', $value),
                    true
                ),
            ],
            'columns' => [
                ['key' => 'ticket', 'label' => 'Ticket'],
                ['key' => 'subject', 'label' => 'Subject'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'priority', 'label' => 'Priority', 'type' => 'status'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'reply', 'label' => 'Last reply', 'type' => 'date'],
            ],
            'row' => fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'ticket' => Str::upper(Str::substr($ticket->uuid, 0, 10)),
                'subject' => $ticket->subject,
                'customer' => $ticket->user?->name
                    ?? $ticket->email
                    ?? 'Guest',
                'type' => $ticket->type?->title ?? '—',
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'reply' => $ticket->last_replied_at ?? $ticket->created_at,
            ],
            'stats' => fn () => [
                'All tickets' => SupportTicket::query()->count(),
                'Open' => SupportTicket::query()
                    ->whereIn('status', ['open', 'reopen'])
                    ->count(),
                'In progress' => SupportTicket::query()
                    ->where('status', 'in_progress')
                    ->count(),
                'Resolved' => SupportTicket::query()
                    ->whereIn('status', ['resolved', 'closed'])
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function reviewDefinition(string $view): array
    {
        if ($view === 'seller') {
            return [
                'title' => 'Seller Feedback',
                'description' => 'Moderate seller ratings and comments.',
                'query' => fn () => SellerFeedback::query()
                    ->with('seller')
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->where('comment', 'like', '%'.$search.'%'),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        ['published', 'hidden'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                    $this->filterDefinition(
                        'rating',
                        'Rating',
                        [1, 2, 3, 4, 5],
                        fn (Builder $query, mixed $value) =>
                            $query->where('rating', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'feedback', 'label' => 'Feedback'],
                    ['key' => 'seller', 'label' => 'Seller'],
                    ['key' => 'customer', 'label' => 'Customer ID'],
                    ['key' => 'rating', 'label' => 'Rating'],
                    ['key' => 'comment', 'label' => 'Comment'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
                ],
                'row' => fn (SellerFeedback $feedback) => [
                    'id' => $feedback->id,
                    'feedback' => '#'.$feedback->id,
                    'seller' => $feedback->seller?->business_name ?? '—',
                    'customer' => $feedback->user_id,
                    'rating' => $feedback->rating.'/5',
                    'comment' => Str::limit($feedback->comment ?: '—', 70),
                    'status' => $feedback->status,
                    'created' => $feedback->created_at,
                ],
                'stats' => fn () => [
                    'All feedback' => SellerFeedback::query()->count(),
                    'Published' => SellerFeedback::query()
                        ->where('status', 'published')
                        ->count(),
                    'Hidden' => SellerFeedback::query()
                        ->where('status', 'hidden')
                        ->count(),
                    'Average rating' => round(
                        (float) SellerFeedback::query()->avg('rating'),
                        2
                    ),
                ],
            ];
        }

        if ($view === 'delivery') {
            return [
                'title' => 'Delivery Feedback',
                'description' => 'Moderate delivery-partner ratings and comments.',
                'query' => fn () => DeliveryFeedback::query()
                    ->with('deliveryBoy.user')
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->where('comment', 'like', '%'.$search.'%'),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        ['published', 'hidden'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                    $this->filterDefinition(
                        'rating',
                        'Rating',
                        [1, 2, 3, 4, 5],
                        fn (Builder $query, mixed $value) =>
                            $query->where('rating', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'feedback', 'label' => 'Feedback'],
                    ['key' => 'partner', 'label' => 'Delivery partner'],
                    ['key' => 'customer', 'label' => 'Customer ID'],
                    ['key' => 'rating', 'label' => 'Rating'],
                    ['key' => 'comment', 'label' => 'Comment'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
                ],
                'row' => fn (DeliveryFeedback $feedback) => [
                    'id' => $feedback->id,
                    'feedback' => '#'.$feedback->id,
                    'partner' => $feedback->deliveryBoy?->user?->name ?? '—',
                    'customer' => $feedback->user_id,
                    'rating' => $feedback->rating.'/5',
                    'comment' => Str::limit($feedback->comment ?: '—', 70),
                    'status' => $feedback->status,
                    'created' => $feedback->created_at,
                ],
                'stats' => fn () => [
                    'All feedback' => DeliveryFeedback::query()->count(),
                    'Published' => DeliveryFeedback::query()
                        ->where('status', 'published')
                        ->count(),
                    'Hidden' => DeliveryFeedback::query()
                        ->where('status', 'hidden')
                        ->count(),
                    'Average rating' => round(
                        (float) DeliveryFeedback::query()->avg('rating'),
                        2
                    ),
                ],
            ];
        }

        return [
            'title' => 'Product Reviews',
            'description' => 'Moderate product reviews and seller replies.',
            'query' => fn () => Review::query()
                ->with(['user', 'product', 'store'])
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('comment', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['published', 'hidden', 'rejected'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'rating',
                    'Rating',
                    [1, 2, 3, 4, 5],
                    fn (Builder $query, mixed $value) =>
                        $query->where('rating', $value)
                ),
            ],
            'columns' => [
                ['key' => 'review', 'label' => 'Review'],
                ['key' => 'product', 'label' => 'Product'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'rating', 'label' => 'Rating'],
                ['key' => 'comment', 'label' => 'Comment'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            'row' => fn (Review $review) => [
                'id' => $review->id,
                'review' => '#'.$review->id,
                'product' => $review->product?->title ?? '—',
                'customer' => $review->user?->name ?? '—',
                'rating' => $review->rating.'/5',
                'comment' => Str::limit($review->comment ?: '—', 70),
                'status' => $review->status,
                'created' => $review->created_at,
            ],
            'stats' => fn () => [
                'All reviews' => Review::query()->count(),
                'Published' => Review::query()
                    ->where('status', 'published')
                    ->count(),
                'Hidden' => Review::query()
                    ->where('status', 'hidden')
                    ->count(),
                'Average rating' => round(
                    (float) Review::query()->avg('rating'),
                    2
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function notificationDefinition(): array
    {
        return [
            'title' => 'Notifications',
            'description' => 'Broadcast or schedule targeted inbox notifications.',
            'query' => fn () => AppNotification::query()
                ->with(['creator', 'zones'])
                ->withCount('users')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['draft', 'scheduled', 'sent', 'failed'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'audience_type',
                    'Audience',
                    ['all', 'customer', 'seller', 'delivery_boy', 'users'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('audience_type', $value)
                ),
            ],
            'columns' => [
                ['key' => 'campaign', 'label' => 'Campaign'],
                ['key' => 'audience', 'label' => 'Audience'],
                ['key' => 'recipients', 'label' => 'Recipients'],
                ['key' => 'zones', 'label' => 'Zones'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'scheduled', 'label' => 'Scheduled', 'type' => 'date'],
                ['key' => 'sent', 'label' => 'Sent', 'type' => 'date'],
            ],
            'row' => fn (AppNotification $notification) => [
                'id' => $notification->id,
                'campaign' => $notification->title,
                'audience' => Str::headline($notification->audience_type),
                'recipients' => $notification->users_count,
                'zones' => $notification->zones->pluck('name')->implode(', ')
                    ?: 'All zones',
                'status' => $notification->status,
                'scheduled' => $notification->scheduled_at,
                'sent' => $notification->sent_at,
            ],
            'stats' => fn () => [
                'All campaigns' => AppNotification::query()->count(),
                'Sent' => AppNotification::query()
                    ->where('status', 'sent')
                    ->count(),
                'Scheduled' => AppNotification::query()
                    ->where('status', 'scheduled')
                    ->count(),
                'Recipients' => (int) DB::table('app_notification_user_map')
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function faqDefinition(string $view): array
    {
        if ($view === 'product') {
            return [
                'title' => 'Product FAQs',
                'description' => 'Moderate customer questions and product answers.',
                'query' => fn () => ProductFaq::query()
                    ->with(['product', 'asker', 'answerer'])
                    ->latest('id'),
                'search' => fn (Builder $query, string $search) => $query
                    ->where(fn (Builder $nested) => $nested
                        ->where('question', 'like', '%'.$search.'%')
                        ->orWhere('answer', 'like', '%'.$search.'%')),
                'filters' => [
                    $this->filterDefinition(
                        'status',
                        'Status',
                        ['pending', 'active', 'inactive', 'rejected'],
                        fn (Builder $query, mixed $value) =>
                            $query->where('status', $value)
                    ),
                ],
                'columns' => [
                    ['key' => 'faq', 'label' => 'FAQ'],
                    ['key' => 'product', 'label' => 'Product'],
                    ['key' => 'question', 'label' => 'Question'],
                    ['key' => 'asked', 'label' => 'Asked by'],
                    ['key' => 'answered', 'label' => 'Answered by'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
                ],
                'row' => fn (ProductFaq $faq) => [
                    'id' => $faq->id,
                    'faq' => '#'.$faq->id,
                    'product' => $faq->product?->title ?? '—',
                    'question' => Str::limit($faq->question, 80),
                    'asked' => $faq->asker?->name ?? '—',
                    'answered' => $faq->answerer?->name ?? 'Unanswered',
                    'status' => $faq->status,
                    'created' => $faq->created_at,
                ],
                'stats' => fn () => [
                    'All product FAQs' => ProductFaq::query()->count(),
                    'Pending' => ProductFaq::query()
                        ->where('status', 'pending')
                        ->count(),
                    'Answered' => ProductFaq::query()
                        ->whereNotNull('answer')
                        ->count(),
                    'Active' => ProductFaq::query()
                        ->where('status', 'active')
                        ->count(),
                ],
            ];
        }

        return [
            'title' => 'General FAQs',
            'description' => 'Manage public support and marketplace FAQs.',
            'query' => fn () => Faq::query()
                ->orderBy('sort_order')
                ->latest('id'),
            'search' => fn (Builder $query, string $search) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('category', 'like', '%'.$search.'%')
                    ->orWhere('question', 'like', '%'.$search.'%')
                    ->orWhere('answer', 'like', '%'.$search.'%')),
            'filters' => [
                $this->filterDefinition(
                    'status',
                    'Status',
                    ['active', 'inactive'],
                    fn (Builder $query, mixed $value) =>
                        $query->where('status', $value)
                ),
                $this->filterDefinition(
                    'category',
                    'Category',
                    $this->distinctValues(Faq::class, 'category'),
                    fn (Builder $query, mixed $value) =>
                        $query->where('category', $value)
                ),
            ],
            'columns' => [
                ['key' => 'faq', 'label' => 'FAQ'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'question', 'label' => 'Question'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'order', 'label' => 'Order'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            'row' => fn (Faq $faq) => [
                'id' => $faq->id,
                'faq' => '#'.$faq->id,
                'category' => $faq->category ?: 'General',
                'question' => Str::limit($faq->question, 90),
                'status' => $faq->status,
                'order' => $faq->sort_order,
                'created' => $faq->created_at,
            ],
            'stats' => fn () => [
                'All FAQs' => Faq::query()->count(),
                'Active' => Faq::query()->where('status', 'active')->count(),
                'Inactive' => Faq::query()->where('status', 'inactive')->count(),
                'Categories' => Faq::query()
                    ->distinct('category')
                    ->count('category'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function bannerDetail(int $id): array
    {
        $banner = Banner::query()
            ->with(['zones', 'product', 'category', 'brand'])
            ->findOrFail($id);

        return $this->detail(
            'banners',
            'Banner Details',
            $banner->title,
            $banner->id,
            [
                $this->section('Banner', [
                    $this->item('Title', $banner->title),
                    $this->item('Type', $banner->type),
                    $this->item('Position', $banner->position),
                    $this->item('Status', $banner->visibility_status, 'status'),
                    $this->item(
                        'Target',
                        $banner->product?->title
                            ?? $banner->category?->title
                            ?? $banner->brand?->title
                            ?? $banner->custom_url
                            ?? '—'
                    ),
                    $this->item(
                        'Zones',
                        $banner->zones->pluck('name')->implode(', ')
                            ?: 'All zones'
                    ),
                    $this->item('Starts', $banner->starts_at, 'date'),
                    $this->item('Ends', $banner->ends_at, 'date'),
                ]),
            ],
            actions: [[
                'action' => 'state',
                'label' => 'Update visibility',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'value',
                        'Visibility',
                        [
                            'published' => 'Published',
                            'draft' => 'Draft',
                        ],
                        $banner->visibility_status
                    ),
                ],
            ]]
        );
    }

    /** @return array<string, mixed> */
    private function featuredSectionDetail(int $id): array
    {
        $section = FeaturedSection::query()
            ->with(['zones', 'products'])
            ->findOrFail($id);

        return $this->detail(
            'featured-sections',
            'Featured Section Details',
            $section->title,
            $section->id,
            [
                $this->section('Section', [
                    $this->item('Title', $section->title),
                    $this->item('Type', Str::headline($section->section_type)),
                    $this->item('Style', Str::headline($section->style)),
                    $this->item('Status', $section->status, 'status'),
                    $this->item('Product limit', $section->product_limit),
                    $this->item('Products', $section->products->count()),
                    $this->item(
                        'Zones',
                        $section->zones->pluck('name')->implode(', ')
                            ?: 'All zones'
                    ),
                    $this->item('Starts', $section->starts_at, 'date'),
                    $this->item('Ends', $section->ends_at, 'date'),
                ]),
            ],
            tables: [[
                'title' => 'Products',
                'columns' => ['Product', 'Status'],
                'rows' => $section->products->map(fn (Product $product) => [
                    $product->title,
                    Str::headline($product->status),
                ])->all(),
            ]],
            actions: [[
                'action' => 'state',
                'label' => 'Update status',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'value',
                        'Status',
                        ['active' => 'Active', 'inactive' => 'Inactive'],
                        $section->status
                    ),
                ],
            ]]
        );
    }

    /** @return array<string, mixed> */
    private function promotionDetail(int $id): array
    {
        $promo = Promo::query()->withCount('userUsages')->findOrFail($id);

        return $this->detail(
            'promotions',
            'Promotion Details',
            $promo->code,
            $promo->id,
            [
                $this->section('Promotion', [
                    $this->item('Code', $promo->code),
                    $this->item('Description', $promo->description ?: '—'),
                    $this->item('Discount type', $promo->discount_type),
                    $this->item(
                        'Discount',
                        $promo->discount_type === 'percentage'
                            ? number_format((float) $promo->discount_amount, 2).'%'
                            : '৳'.number_format((float) $promo->discount_amount, 2)
                    ),
                    $this->item('Mode', $promo->promo_mode),
                    $this->item('Minimum order', (float) $promo->min_order_total, 'money'),
                    $this->item(
                        'Usage',
                        $promo->usage_count.'/'.($promo->max_total_usage ?? '∞')
                    ),
                    $this->item('Status', $promo->status, 'status'),
                    $this->item('Starts', $promo->start_date, 'date'),
                    $this->item('Ends', $promo->end_date, 'date'),
                ]),
            ],
            actions: [[
                'action' => 'state',
                'label' => 'Update status',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'value',
                        'Status',
                        ['active' => 'Active', 'inactive' => 'Inactive'],
                        $promo->status
                    ),
                ],
            ]]
        );
    }

    /** @return array<string, mixed> */
    private function advertisementDetail(int $id): array
    {
        $campaign = AdCampaign::query()
            ->with(['seller', 'store', 'product', 'stats'])
            ->findOrFail($id);

        $actions = [];

        if ($campaign->status === 'pending_approval') {
            $actions[] = [
                'action' => 'approve',
                'label' => 'Approve campaign',
                'tone' => 'success',
                'confirm' => true,
                'fields' => [],
            ];
            $actions[] = [
                'action' => 'reject',
                'label' => 'Reject campaign',
                'tone' => 'danger',
                'fields' => [
                    $this->textareaField(
                        'reason',
                        'Rejection reason',
                        true
                    ),
                ],
            ];
        }

        return $this->detail(
            'advertisements',
            'Advertisement Details',
            $campaign->title,
            $campaign->id,
            [
                $this->section('Campaign', [
                    $this->item('UUID', $campaign->uuid),
                    $this->item(
                        'Seller',
                        $campaign->seller?->business_name ?? '—'
                    ),
                    $this->item('Store', $campaign->store?->name ?? '—'),
                    $this->item('Product', $campaign->product?->title ?? '—'),
                    $this->item('Placement', $campaign->placement),
                    $this->item('Type', $campaign->ad_type),
                    $this->item('Budget', (float) $campaign->budget, 'money'),
                    $this->item('Spent', (float) $campaign->spent_amount, 'money'),
                    $this->item('Status', $campaign->status, 'status'),
                    $this->item('Starts', $campaign->starts_at, 'date'),
                    $this->item('Ends', $campaign->ends_at, 'date'),
                    $this->item(
                        'Rejection reason',
                        $campaign->rejection_reason ?: '—'
                    ),
                ]),
            ],
            actions: $actions
        );
    }

    /** @return array<string, mixed> */
    private function subscriptionDetail(int $id, string $view): array
    {
        if ($view === 'subscribers') {
            $subscription = SellerSubscription::query()
                ->with(['seller', 'plan', 'usages'])
                ->findOrFail($id);

            return $this->detail(
                'subscriptions',
                'Seller Subscription',
                $subscription->seller?->business_name ?? '#'.$id,
                $subscription->id,
                [
                    $this->section('Subscription', [
                        $this->item(
                            'Seller',
                            $subscription->seller?->business_name ?? '—'
                        ),
                        $this->item('Plan', $subscription->plan?->title ?? '—'),
                        $this->item('Status', $subscription->status, 'status'),
                        $this->item('Starts', $subscription->starts_at, 'date'),
                        $this->item('Ends', $subscription->ends_at, 'date'),
                        $this->item(
                            'Auto renew',
                            $subscription->auto_renew ? 'Yes' : 'No'
                        ),
                    ]),
                ],
                tables: [[
                    'title' => 'Feature Usage',
                    'columns' => ['Feature', 'Used', 'Period starts', 'Period ends'],
                    'rows' => $subscription->usages->map(fn ($usage) => [
                        Str::headline($usage->feature_key),
                        $usage->used_count,
                        $usage->period_starts_at?->format('d M Y') ?? '—',
                        $usage->period_ends_at?->format('d M Y') ?? '—',
                    ])->all(),
                ]],
                query: ['view' => 'subscribers']
            );
        }

        $plan = SubscriptionPlan::query()->with('limits')->findOrFail($id);

        return $this->detail(
            'subscriptions',
            'Subscription Plan',
            $plan->title,
            $plan->id,
            [
                $this->section('Plan', [
                    $this->item('Title', $plan->title),
                    $this->item('Slug', $plan->slug),
                    $this->item('Price', (float) $plan->price, 'money'),
                    $this->item('Duration', $plan->duration_days.' days'),
                    $this->item('Trial', $plan->trial_days.' days'),
                    $this->item('Featured', $plan->is_featured ? 'Yes' : 'No'),
                    $this->item('Status', $plan->status, 'status'),
                ]),
            ],
            tables: [[
                'title' => 'Feature Limits',
                'columns' => ['Feature', 'Limit', 'Unlimited'],
                'rows' => $plan->limits->map(fn ($limit) => [
                    Str::headline($limit->feature_key),
                    $limit->limit_value ?? '—',
                    $limit->is_unlimited ? 'Yes' : 'No',
                ])->all(),
            ]],
            actions: [[
                'action' => 'state',
                'label' => 'Update status',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'value',
                        'Status',
                        ['active' => 'Active', 'inactive' => 'Inactive'],
                        $plan->status
                    ),
                ],
            ]],
            query: ['view' => 'plans']
        );
    }

    /** @return array<string, mixed> */
    private function giftReferralDetail(int $id, string $view): array
    {
        if ($view === 'referrals') {
            $referral = Referral::query()
                ->with(['referrer', 'referred', 'earnings.beneficiary'])
                ->findOrFail($id);

            return $this->detail(
                'gift-cards-referrals',
                'Referral Details',
                $referral->referral_code,
                $referral->id,
                [
                    $this->section('Referral', [
                        $this->item('Referrer', $referral->referrer?->name ?? '—'),
                        $this->item('Referred user', $referral->referred?->name ?? '—'),
                        $this->item('Status', $referral->status, 'status'),
                        $this->item('Completed', $referral->completed_at, 'date'),
                        $this->item('Rewarded', $referral->rewarded_at, 'date'),
                    ]),
                ],
                tables: [[
                    'title' => 'Earnings',
                    'columns' => ['Beneficiary', 'Type', 'Amount', 'Status'],
                    'rows' => $referral->earnings->map(fn ($earning) => [
                        $earning->beneficiary?->name ?? '—',
                        Str::headline($earning->beneficiary_type),
                        '৳'.number_format((float) $earning->earned_amount, 2),
                        Str::headline($earning->status),
                    ])->all(),
                ]],
                query: ['view' => 'referrals']
            );
        }

        if ($view === 'earnings') {
            $earning = ReferralEarning::query()
                ->with(['beneficiary', 'order', 'referral'])
                ->findOrFail($id);

            return $this->detail(
                'gift-cards-referrals',
                'Referral Earning',
                '#'.$earning->id,
                $earning->id,
                [
                    $this->section('Earning', [
                        $this->item('Beneficiary', $earning->beneficiary?->name ?? '—'),
                        $this->item('Type', $earning->beneficiary_type),
                        $this->item('Order', $earning->order?->slug ?? '—'),
                        $this->item('Order amount', (float) $earning->order_amount, 'money'),
                        $this->item('Earned', (float) $earning->earned_amount, 'money'),
                        $this->item('Status', $earning->status, 'status'),
                        $this->item('Settled', $earning->settled_at, 'date'),
                    ]),
                ],
                query: ['view' => 'earnings']
            );
        }

        $card = GiftCard::query()
            ->with(['creator', 'redemptions.user'])
            ->findOrFail($id);

        return $this->detail(
            'gift-cards-referrals',
            'Gift Card',
            $card->code,
            $card->id,
            [
                $this->section('Gift Card', [
                    $this->item('Code', $card->code),
                    $this->item('Title', $card->title),
                    $this->item('Amount', (float) $card->amount, 'money'),
                    $this->item(
                        'Redemptions',
                        $card->redemption_count.'/'.($card->max_redemptions ?? '∞')
                    ),
                    $this->item('Status', $card->status, 'status'),
                    $this->item('Starts', $card->starts_at, 'date'),
                    $this->item('Ends', $card->ends_at, 'date'),
                ]),
            ],
            tables: [[
                'title' => 'Redemptions',
                'columns' => ['Customer', 'Amount', 'Redeemed'],
                'rows' => $card->redemptions->map(fn ($redemption) => [
                    $redemption->user?->name ?? '—',
                    '৳'.number_format((float) $redemption->amount, 2),
                    $redemption->redeemed_at?->format('d M Y H:i') ?? '—',
                ])->all(),
            ]],
            actions: [[
                'action' => 'state',
                'label' => 'Update status',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'value',
                        'Status',
                        ['active' => 'Active', 'inactive' => 'Inactive'],
                        $card->status
                    ),
                ],
            ]],
            query: ['view' => 'gift-cards']
        );
    }

    /** @return array<string, mixed> */
    private function statementDetail(int $id): array
    {
        $statement = SellerStatement::query()
            ->with(['seller', 'order', 'settledBy'])
            ->findOrFail($id);

        $actions = $statement->settlement_status === 'unsettled'
            ? [[
                'action' => 'settle',
                'label' => 'Settle statement',
                'tone' => 'success',
                'confirm' => true,
                'fields' => [],
            ]]
            : [];

        return $this->detail(
            'seller-statements',
            'Seller Statement',
            '#'.$statement->id,
            $statement->id,
            [
                $this->section('Statement', [
                    $this->item('Seller', $statement->seller?->business_name ?? '—'),
                    $this->item('Order', $statement->order?->slug ?? '—'),
                    $this->item('Entry type', Str::headline($statement->entry_type)),
                    $this->item('Direction', $statement->direction, 'status'),
                    $this->item('Amount', (float) $statement->amount, 'money'),
                    $this->item('Settlement', $statement->settlement_status, 'status'),
                    $this->item('Posted', $statement->posted_at, 'date'),
                    $this->item('Settled', $statement->settled_at, 'date'),
                    $this->item('Reference', $statement->settlement_reference ?: '—'),
                    $this->item('Description', $statement->description ?: '—'),
                ]),
            ],
            actions: $actions
        );
    }

    /** @return array<string, mixed> */
    private function sellerWithdrawalDetail(int $id): array
    {
        $withdrawal = SellerWithdrawalRequest::query()
            ->with(['seller.owner', 'user', 'processedBy'])
            ->findOrFail($id);

        return $this->detail(
            'seller-withdrawals',
            'Seller Withdrawal',
            '#'.$withdrawal->id,
            $withdrawal->id,
            [
                $this->section('Withdrawal', [
                    $this->item('Seller', $withdrawal->seller?->business_name ?? '—'),
                    $this->item(
                        'Owner',
                        $withdrawal->seller?->owner?->name
                            ?? $withdrawal->user?->name
                            ?? '—'
                    ),
                    $this->item('Amount', (float) $withdrawal->amount, 'money'),
                    $this->item('Status', $withdrawal->status, 'status'),
                    $this->item('Request note', $withdrawal->request_note ?: '—'),
                    $this->item('Admin remark', $withdrawal->admin_remark ?: '—'),
                    $this->item(
                        'External transaction',
                        $withdrawal->external_transaction_id ?: '—'
                    ),
                    $this->item('Processed', $withdrawal->processed_at, 'date'),
                ]),
            ],
            actions: $this->withdrawalActions(
                $withdrawal->status,
                'process'
            )
        );
    }

    /** @return array<string, mixed> */
    private function riderCashDetail(int $id, string $view): array
    {
        if ($view === 'withdrawals') {
            $withdrawal = DeliveryBoyWithdrawalRequest::query()
                ->with(['deliveryBoy.user', 'user', 'processedBy'])
                ->findOrFail($id);

            return $this->detail(
                'rider-cash',
                'Delivery Partner Withdrawal',
                '#'.$withdrawal->id,
                $withdrawal->id,
                [
                    $this->section('Withdrawal', [
                        $this->item(
                            'Delivery partner',
                            $withdrawal->deliveryBoy?->user?->name
                                ?? $withdrawal->user?->name
                                ?? '—'
                        ),
                        $this->item('Amount', (float) $withdrawal->amount, 'money'),
                        $this->item('Status', $withdrawal->status, 'status'),
                        $this->item('Request note', $withdrawal->request_note ?: '—'),
                        $this->item('Admin remark', $withdrawal->admin_remark ?: '—'),
                        $this->item(
                            'External transaction',
                            $withdrawal->external_transaction_id ?: '—'
                        ),
                        $this->item('Processed', $withdrawal->processed_at, 'date'),
                    ]),
                ],
                actions: $this->withdrawalActions(
                    $withdrawal->status,
                    'process-withdrawal'
                ),
                query: ['view' => 'withdrawals']
            );
        }

        $transaction = DeliveryBoyCashTransaction::query()
            ->with(['deliveryBoy.user', 'order'])
            ->findOrFail($id);

        $actions = $transaction->status === 'pending'
            ? [[
                'action' => 'process-cash',
                'label' => 'Process transaction',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'status',
                        'Decision',
                        [
                            'approved' => 'Approve',
                            'rejected' => 'Reject',
                        ]
                    ),
                    $this->textareaField('note', 'Admin note'),
                ],
            ]]
            : [];

        return $this->detail(
            'rider-cash',
            'Cash Transaction',
            '#'.$transaction->id,
            $transaction->id,
            [
                $this->section('Transaction', [
                    $this->item(
                        'Delivery partner',
                        $transaction->deliveryBoy?->user?->name ?? '—'
                    ),
                    $this->item('Order', $transaction->order?->slug ?? '—'),
                    $this->item('Type', Str::headline($transaction->type)),
                    $this->item('Amount', (float) $transaction->amount, 'money'),
                    $this->item('Status', $transaction->status, 'status'),
                    $this->item('Reference', $transaction->reference ?: '—'),
                    $this->item('Note', $transaction->note ?: '—'),
                    $this->item('Processed', $transaction->processed_at, 'date'),
                ]),
            ],
            actions: $actions,
            query: ['view' => 'cash']
        );
    }

    /** @return array<string, mixed> */
    private function gatewayDetail(int $id): array
    {
        $gateway = PaymentGatewayConfig::query()->findOrFail($id);

        return $this->detail(
            'payment-gateways',
            'Payment Gateway',
            $gateway->display_name,
            $gateway->id,
            [
                $this->section('Gateway', [
                    $this->item('Code', $gateway->code),
                    $this->item('Name', $gateway->display_name),
                    $this->item(
                        'Enabled',
                        $gateway->enabled ? 'active' : 'inactive',
                        'status'
                    ),
                    $this->item('Mode', $gateway->test_mode ? 'Test' : 'Live'),
                    $this->item(
                        'Currencies',
                        collect($gateway->supported_currencies ?? [])->implode(', ')
                            ?: '—'
                    ),
                    $this->item(
                        'Secret configured',
                        ! empty($gateway->secret_config) ? 'Yes' : 'No'
                    ),
                    $this->item(
                        'Public config',
                        json_encode(
                            $gateway->public_config ?? [],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                        )
                    ),
                ]),
            ],
            editable: true
        );
    }

    /** @return array<string, mixed> */
    private function paymentDetail(int $id, string $view): array
    {
        if ($view === 'webhooks') {
            $event = WebhookEvent::query()->findOrFail($id);

            return $this->detail(
                'payment-intents',
                'Webhook Event',
                $event->event_id,
                $event->id,
                [
                    $this->section('Webhook', [
                        $this->item('Provider', $event->provider),
                        $this->item('Event type', $event->event_type ?: '—'),
                        $this->item('Status', $event->status, 'status'),
                        $this->item(
                            'Signature valid',
                            $event->signature_valid ? 'Yes' : 'No'
                        ),
                        $this->item('Processed', $event->processed_at, 'date'),
                        $this->item('Error', $event->error ?: '—'),
                        $this->item(
                            'Payload',
                            json_encode(
                                $event->payload ?? [],
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                            )
                        ),
                    ]),
                ],
                query: ['view' => 'webhooks']
            );
        }

        $intent = PaymentIntent::query()->with('order')->findOrFail($id);

        return $this->detail(
            'payment-intents',
            'Payment Intent',
            $intent->uuid,
            $intent->id,
            [
                $this->section('Intent', [
                    $this->item('Provider', $intent->provider),
                    $this->item('Purpose', Str::headline($intent->purpose)),
                    $this->item('Amount', (float) $intent->amount, 'money'),
                    $this->item('Currency', $intent->currency),
                    $this->item('Status', $intent->status, 'status'),
                    $this->item('Order', $intent->order?->slug ?? '—'),
                    $this->item('External ID', $intent->external_id ?: '—'),
                    $this->item('Expires', $intent->expires_at, 'date'),
                    $this->item(
                        'Metadata',
                        json_encode(
                            $intent->metadata ?? [],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                        )
                    ),
                ]),
            ],
            query: ['view' => 'intents']
        );
    }

    /** @return array<string, mixed> */
    private function supportDetail(int $id): array
    {
        $ticket = SupportTicket::query()
            ->with(['type', 'user', 'order', 'assignee', 'messages.sender'])
            ->findOrFail($id);

        $admins = User::query()
            ->where('access_panel', 'admin')
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $this->detail(
            'support',
            'Support Ticket',
            $ticket->subject,
            $ticket->id,
            [
                $this->section('Ticket', [
                    $this->item('UUID', $ticket->uuid),
                    $this->item(
                        'Customer',
                        $ticket->user?->name ?? $ticket->email ?? 'Guest'
                    ),
                    $this->item('Type', $ticket->type?->title ?? '—'),
                    $this->item('Order', $ticket->order?->slug ?? '—'),
                    $this->item('Priority', $ticket->priority, 'status'),
                    $this->item('Status', $ticket->status, 'status'),
                    $this->item('Assigned to', $ticket->assignee?->name ?? 'Unassigned'),
                    $this->item('Description', $ticket->description),
                ]),
            ],
            tables: [[
                'title' => 'Conversation',
                'columns' => ['Sender', 'Role', 'Message', 'Internal', 'Sent'],
                'rows' => $ticket->messages->map(fn ($message) => [
                    $message->sender?->name ?? 'System',
                    Str::headline($message->sender_role),
                    $message->message,
                    $message->is_internal ? 'Yes' : 'No',
                    $message->created_at?->format('d M Y H:i') ?? '—',
                ])->all(),
            ]],
            actions: [
                [
                    'action' => 'reply',
                    'label' => 'Add reply',
                    'tone' => 'primary',
                    'fields' => [
                        $this->textareaField('message', 'Message', true),
                        [
                            'name' => 'is_internal',
                            'label' => 'Internal note only',
                            'type' => 'checkbox',
                        ],
                    ],
                ],
                [
                    'action' => 'status',
                    'label' => 'Update ticket',
                    'tone' => 'secondary',
                    'fields' => [
                        $this->selectField(
                            'status',
                            'Status',
                            [
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'reopen' => 'Reopen',
                                'pending_review' => 'Pending Review',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ],
                            $ticket->status
                        ),
                        $this->selectField(
                            'assigned_to',
                            'Assign admin',
                            $admins,
                            $ticket->assigned_to,
                            false,
                            true
                        ),
                    ],
                ],
            ]
        );
    }

    /** @return array<string, mixed> */
    private function reviewDetail(int $id, string $view): array
    {
        if ($view === 'seller') {
            $feedback = SellerFeedback::query()
                ->with('seller')
                ->findOrFail($id);

            return $this->detail(
                'reviews',
                'Seller Feedback',
                '#'.$feedback->id,
                $feedback->id,
                [
                    $this->section('Feedback', [
                        $this->item('Seller', $feedback->seller?->business_name ?? '—'),
                        $this->item('Customer ID', $feedback->user_id),
                        $this->item('Rating', $feedback->rating.'/5'),
                        $this->item('Status', $feedback->status, 'status'),
                        $this->item('Comment', $feedback->comment ?: '—'),
                        $this->item('Seller reply', $feedback->seller_reply ?: '—'),
                    ]),
                ],
                actions: [[
                    'action' => 'moderate-feedback',
                    'label' => 'Moderate feedback',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'queue',
                            'type' => 'hidden',
                            'value' => 'seller',
                        ],
                        $this->selectField(
                            'status',
                            'Status',
                            [
                                'published' => 'Published',
                                'hidden' => 'Hidden',
                            ],
                            $feedback->status
                        ),
                    ],
                ]],
                query: ['view' => 'seller']
            );
        }

        if ($view === 'delivery') {
            $feedback = DeliveryFeedback::query()
                ->with('deliveryBoy.user')
                ->findOrFail($id);

            return $this->detail(
                'reviews',
                'Delivery Feedback',
                '#'.$feedback->id,
                $feedback->id,
                [
                    $this->section('Feedback', [
                        $this->item(
                            'Delivery partner',
                            $feedback->deliveryBoy?->user?->name ?? '—'
                        ),
                        $this->item('Customer ID', $feedback->user_id),
                        $this->item('Rating', $feedback->rating.'/5'),
                        $this->item('Status', $feedback->status, 'status'),
                        $this->item('Comment', $feedback->comment ?: '—'),
                    ]),
                ],
                actions: [[
                    'action' => 'moderate-feedback',
                    'label' => 'Moderate feedback',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'queue',
                            'type' => 'hidden',
                            'value' => 'delivery',
                        ],
                        $this->selectField(
                            'status',
                            'Status',
                            [
                                'published' => 'Published',
                                'hidden' => 'Hidden',
                            ],
                            $feedback->status
                        ),
                    ],
                ]],
                query: ['view' => 'delivery']
            );
        }

        $review = Review::query()
            ->with(['user', 'product', 'store', 'moderator'])
            ->findOrFail($id);

        return $this->detail(
            'reviews',
            'Product Review',
            '#'.$review->id,
            $review->id,
            [
                $this->section('Review', [
                    $this->item('Product', $review->product?->title ?? '—'),
                    $this->item('Store', $review->store?->name ?? '—'),
                    $this->item('Customer', $review->user?->name ?? '—'),
                    $this->item('Rating', $review->rating.'/5'),
                    $this->item('Status', $review->status, 'status'),
                    $this->item('Title', $review->title ?: '—'),
                    $this->item('Comment', $review->comment ?: '—'),
                    $this->item('Seller reply', $review->seller_reply ?: '—'),
                    $this->item('Moderation note', $review->moderation_note ?: '—'),
                ]),
            ],
            actions: [[
                'action' => 'moderate-product',
                'label' => 'Moderate review',
                'tone' => 'primary',
                'fields' => [
                    $this->selectField(
                        'status',
                        'Status',
                        [
                            'published' => 'Published',
                            'hidden' => 'Hidden',
                            'rejected' => 'Rejected',
                        ],
                        $review->status
                    ),
                    $this->textareaField(
                        'note',
                        'Moderation note',
                        false,
                        $review->moderation_note
                    ),
                ],
            ]],
            query: ['view' => 'product']
        );
    }

    /** @return array<string, mixed> */
    private function notificationDetail(int $id): array
    {
        $notification = AppNotification::query()
            ->with(['creator', 'zones', 'users'])
            ->findOrFail($id);

        return $this->detail(
            'notifications',
            'Notification Campaign',
            $notification->title,
            $notification->id,
            [
                $this->section('Campaign', [
                    $this->item('Audience', Str::headline($notification->audience_type)),
                    $this->item('Status', $notification->status, 'status'),
                    $this->item('Message', $notification->message),
                    $this->item(
                        'Zones',
                        $notification->zones->pluck('name')->implode(', ')
                            ?: 'All zones'
                    ),
                    $this->item('Recipients', $notification->users->count()),
                    $this->item('Scheduled', $notification->scheduled_at, 'date'),
                    $this->item('Sent', $notification->sent_at, 'date'),
                    $this->item('Created by', $notification->creator?->name ?? '—'),
                ]),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function faqDetail(int $id, string $view): array
    {
        if ($view === 'product') {
            $faq = ProductFaq::query()
                ->with(['product', 'asker', 'answerer'])
                ->findOrFail($id);

            return $this->detail(
                'faqs',
                'Product FAQ',
                '#'.$faq->id,
                $faq->id,
                [
                    $this->section('Product FAQ', [
                        $this->item('Product', $faq->product?->title ?? '—'),
                        $this->item('Asked by', $faq->asker?->name ?? '—'),
                        $this->item('Question', $faq->question),
                        $this->item('Answer', $faq->answer ?: 'Unanswered'),
                        $this->item('Answered by', $faq->answerer?->name ?? '—'),
                        $this->item('Status', $faq->status, 'status'),
                    ]),
                ],
                actions: [[
                    'action' => 'moderate-product-faq',
                    'label' => 'Moderate FAQ',
                    'tone' => 'primary',
                    'fields' => [
                        $this->selectField(
                            'status',
                            'Status',
                            [
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'rejected' => 'Rejected',
                            ],
                            $faq->status
                        ),
                        $this->textareaField(
                            'answer',
                            'Answer',
                            false,
                            $faq->answer
                        ),
                    ],
                ]],
                query: ['view' => 'product']
            );
        }

        $faq = Faq::query()->findOrFail($id);

        return $this->detail(
            'faqs',
            'FAQ Details',
            '#'.$faq->id,
            $faq->id,
            [
                $this->section('FAQ', [
                    $this->item('Category', $faq->category ?: 'General'),
                    $this->item('Question', $faq->question),
                    $this->item('Answer', $faq->answer),
                    $this->item('Status', $faq->status, 'status'),
                    $this->item('Sort order', $faq->sort_order),
                ]),
            ],
            editable: true,
            deletable: true,
            query: ['view' => 'general']
        );
    }

    private function saveNotification(
        Request $request,
        User $admin
    ): AppNotification {
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
        ]);

        $notification = $this->notifications->broadcast($admin, $data);

        $this->audit->record(
            $admin,
            'admin.notification.broadcast',
            AppNotification::class,
            $notification->id,
            null,
            $notification->toArray(),
            $request,
            ['recipient_count' => $notification->users->count()]
        );

        return $notification;
    }

    private function saveFaq(
        Request $request,
        User $admin,
        ?int $id
    ): Faq {
        $faq = $id
            ? Faq::query()->findOrFail($id)
            : new Faq();

        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $before = $faq->exists ? $faq->toArray() : null;
        $faq->fill($data)->save();

        $this->audit->record(
            $admin,
            'admin.faq.saved',
            Faq::class,
            $faq->id,
            $before,
            $faq->fresh()->toArray(),
            $request
        );

        return $faq->fresh();
    }

    private function saveGateway(
        Request $request,
        User $admin,
        int $id
    ): PaymentGatewayConfig {
        $gateway = PaymentGatewayConfig::query()->findOrFail($id);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'test_mode' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'public_config_json' => ['nullable', 'json'],
            'secret_config_json' => ['nullable', 'json'],
            'supported_currencies' => ['nullable', 'string', 'max:255'],
        ]);

        $update = [
            'display_name' => $data['display_name'],
            'enabled' => $request->boolean('enabled'),
            'test_mode' => $request->boolean('test_mode'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'public_config' => $this->decodeJson(
                $data['public_config_json'] ?? null
            ),
            'supported_currencies' => collect(
                explode(',', $data['supported_currencies'] ?? '')
            )->map(fn (string $currency) => Str::upper(trim($currency)))
                ->filter()
                ->values()
                ->all(),
        ];

        if (
            isset($data['secret_config_json'])
            && trim($data['secret_config_json']) !== ''
        ) {
            $update['secret_config'] = $this->decodeJson(
                $data['secret_config_json']
            );
        }

        $before = $gateway->toArray();
        $fresh = $this->gateways->update($gateway->code, $update);

        $this->audit->record(
            $admin,
            'admin.payment_gateway.updated',
            PaymentGatewayConfig::class,
            $fresh->id,
            $before,
            $fresh->toArray(),
            $request
        );

        return $fresh;
    }

    private function toggleState(
        Model $model,
        string $field,
        array $allowed,
        Request $request,
        User $admin,
        string $entity
    ): void {
        $data = $request->validate([
            'value' => ['required', Rule::in($allowed)],
        ]);

        $before = $model->toArray();
        $model->update([$field => $data['value']]);

        $this->audit->record(
            $admin,
            'admin.'.$entity.'.state_updated',
            $model::class,
            (int) $model->getKey(),
            $before,
            $model->fresh()->toArray(),
            $request
        );
    }

    private function advertisementAction(
        int $id,
        string $action,
        Request $request,
        User $admin
    ): void {
        $campaign = AdCampaign::query()->findOrFail($id);
        $before = $campaign->toArray();

        if ($action === 'approve') {
            $fresh = $this->advertising->approve($campaign, $admin);
        } elseif ($action === 'reject') {
            $data = $request->validate([
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            $fresh = $this->advertising->reject(
                $campaign,
                $admin,
                $data['reason']
            );
        } else {
            throw ValidationException::withMessages([
                'action' => 'Unsupported advertising action.',
            ]);
        }

        $this->audit->record(
            $admin,
            'admin.advertisement.'.$action,
            AdCampaign::class,
            $fresh->id,
            $before,
            $fresh->toArray(),
            $request
        );
    }

    private function subscriptionAction(
        int $id,
        string $view,
        Request $request,
        User $admin
    ): void {
        if ($view !== 'plans') {
            throw ValidationException::withMessages([
                'action' => 'Subscriber records are read-only here.',
            ]);
        }

        $this->toggleState(
            SubscriptionPlan::query()->findOrFail($id),
            'status',
            ['active', 'inactive'],
            $request,
            $admin,
            'subscription_plan'
        );
    }

    private function giftCardAction(
        int $id,
        string $view,
        Request $request,
        User $admin
    ): void {
        if ($view !== 'gift-cards') {
            throw ValidationException::withMessages([
                'action' => 'Referral records are read-only here.',
            ]);
        }

        $this->toggleState(
            GiftCard::query()->findOrFail($id),
            'status',
            ['active', 'inactive'],
            $request,
            $admin,
            'gift_card'
        );
    }

    private function statementAction(
        int $id,
        string $action,
        User $admin
    ): void {
        if ($action !== 'settle') {
            throw ValidationException::withMessages([
                'action' => 'Unsupported statement action.',
            ]);
        }

        $this->finance->settleStatement($admin, $id);
    }

    private function sellerWithdrawalAction(
        int $id,
        string $action,
        Request $request,
        User $admin
    ): void {
        if ($action !== 'process') {
            throw ValidationException::withMessages([
                'action' => 'Unsupported withdrawal action.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'remark' => ['nullable', 'string', 'max:1000'],
            'external_transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        $this->finance->processSellerWithdrawal(
            $admin,
            $id,
            $data['status'],
            $data['remark'] ?? null,
            $data['external_transaction_id'] ?? null
        );
    }

    private function riderCashAction(
        int $id,
        string $view,
        string $action,
        Request $request,
        User $admin
    ): void {
        if ($view === 'withdrawals' && $action === 'process-withdrawal') {
            $data = $request->validate([
                'status' => ['required', Rule::in(['approved', 'rejected'])],
                'remark' => ['nullable', 'string', 'max:1000'],
                'external_transaction_id' => ['nullable', 'string', 'max:255'],
            ]);

            $this->finance->processRiderWithdrawal(
                $admin,
                $id,
                $data['status'],
                $data['remark'] ?? null,
                $data['external_transaction_id'] ?? null
            );

            return;
        }

        if ($view === 'cash' && $action === 'process-cash') {
            $data = $request->validate([
                'status' => ['required', Rule::in(['approved', 'rejected'])],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $transaction = DeliveryBoyCashTransaction::query()
                ->findOrFail($id);
            $before = $transaction->toArray();
            $fresh = $this->cash->processCashTransaction(
                $transaction,
                $admin,
                $data['status'],
                $data['note'] ?? null
            );

            $this->audit->record(
                $admin,
                'admin.delivery_cash.processed',
                DeliveryBoyCashTransaction::class,
                $fresh->id,
                $before,
                $fresh->toArray(),
                $request
            );

            return;
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported rider cash action.',
        ]);
    }

    private function supportAction(
        int $id,
        string $action,
        Request $request,
        User $admin
    ): void {
        if ($action === 'reply') {
            $data = $request->validate([
                'message' => ['required', 'string', 'max:5000'],
                'is_internal' => ['nullable', 'boolean'],
            ]);

            $before = SupportTicket::query()->findOrFail($id)->toArray();
            $fresh = $this->support->adminReply(
                $admin,
                $id,
                $data['message'],
                $request->boolean('is_internal')
            );

            $this->audit->record(
                $admin,
                'admin.support.replied',
                SupportTicket::class,
                $fresh->id,
                $before,
                $fresh->toArray(),
                $request,
                ['internal' => $request->boolean('is_internal')]
            );

            return;
        }

        if ($action === 'status') {
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
            $fresh = $this->support->updateStatus(
                $admin,
                $id,
                $data['status'],
                $data['assigned_to'] ?? null
            );

            $this->audit->record(
                $admin,
                'admin.support.status_updated',
                SupportTicket::class,
                $fresh->id,
                $before,
                $fresh->toArray(),
                $request
            );

            return;
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported support action.',
        ]);
    }

    private function reviewAction(
        int $id,
        string $view,
        string $action,
        Request $request,
        User $admin
    ): void {
        if ($view === 'product' && $action === 'moderate-product') {
            $data = $request->validate([
                'status' => [
                    'required',
                    Rule::in(['published', 'hidden', 'rejected']),
                ],
                'note' => ['nullable', 'string', 'max:3000'],
            ]);

            $before = Review::query()->findOrFail($id)->toArray();
            $fresh = $this->reviews->moderate(
                $admin,
                $id,
                $data['status'],
                $data['note'] ?? null
            );

            $this->audit->record(
                $admin,
                'admin.review.moderated',
                Review::class,
                $fresh->id,
                $before,
                $fresh->toArray(),
                $request
            );

            return;
        }

        if (
            in_array($view, ['seller', 'delivery'], true)
            && $action === 'moderate-feedback'
        ) {
            $data = $request->validate([
                'queue' => ['required', Rule::in(['seller', 'delivery'])],
                'status' => ['required', Rule::in(['published', 'hidden'])],
            ]);

            if ($data['queue'] !== $view) {
                throw ValidationException::withMessages([
                    'queue' => 'The feedback queue does not match this page.',
                ]);
            }

            $model = $view === 'seller'
                ? SellerFeedback::query()->findOrFail($id)
                : DeliveryFeedback::query()->findOrFail($id);

            $before = $model->toArray();
            $model->update(['status' => $data['status']]);

            $this->audit->record(
                $admin,
                'admin.'.$view.'_feedback.moderated',
                $model::class,
                $model->id,
                $before,
                $model->fresh()->toArray(),
                $request
            );

            return;
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported review action.',
        ]);
    }

    private function faqAction(
        int $id,
        string $view,
        Request $request,
        User $admin
    ): void {
        if ($view !== 'product') {
            throw ValidationException::withMessages([
                'action' => 'General FAQs use the edit screen.',
            ]);
        }

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
            $updates += [
                'answer' => $data['answer'],
                'answered_by' => $admin->id,
                'answered_at' => now(),
            ];
        }

        $faq->update($updates);

        $this->audit->record(
            $admin,
            'admin.product_faq.moderated',
            ProductFaq::class,
            $faq->id,
            $before,
            $faq->fresh()->toArray(),
            $request
        );
    }

    /** @return array<string, mixed> */
    private function detail(
        string $module,
        string $title,
        string $subtitle,
        int $recordId,
        array $sections,
        array $tables = [],
        array $actions = [],
        bool $editable = false,
        bool $deletable = false,
        array $query = []
    ): array {
        return compact(
            'module',
            'title',
            'subtitle',
            'recordId',
            'sections',
            'tables',
            'actions',
            'editable',
            'deletable',
            'query'
        );
    }

    /** @return array<string, mixed> */
    private function section(string $title, array $items): array
    {
        return compact('title', 'items');
    }

    /** @return array<string, mixed> */
    private function item(
        string $label,
        mixed $value,
        string $type = 'text'
    ): array {
        return compact('label', 'value', 'type');
    }

    /** @return array<string, mixed> */
    private function selectField(
        string $name,
        string $label,
        array $options,
        mixed $value = null,
        bool $required = true,
        bool $nullable = false
    ): array {
        return compact(
            'name',
            'label',
            'options',
            'value',
            'required',
            'nullable'
        ) + ['type' => 'select'];
    }

    /** @return array<string, mixed> */
    private function textareaField(
        string $name,
        string $label,
        bool $required = false,
        ?string $value = null
    ): array {
        return compact('name', 'label', 'required', 'value')
            + ['type' => 'textarea'];
    }

    /** @return array<int, array<string, mixed>> */
    private function withdrawalActions(
        string $status,
        string $action
    ): array {
        if ($status !== 'pending') {
            return [];
        }

        return [[
            'action' => $action,
            'label' => 'Process withdrawal',
            'tone' => 'primary',
            'fields' => [
                $this->selectField(
                    'status',
                    'Decision',
                    [
                        'approved' => 'Approve',
                        'rejected' => 'Reject',
                    ]
                ),
                [
                    'name' => 'external_transaction_id',
                    'label' => 'External transaction ID',
                    'type' => 'text',
                    'required' => false,
                    'value' => null,
                ],
                $this->textareaField('remark', 'Admin remark'),
            ],
        ]];
    }

    /** @return array<string, mixed> */
    private function filterDefinition(
        string $key,
        string $label,
        array $options,
        callable $apply,
        bool $mapped = false
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'options' => $options,
            'type' => $mapped ? 'select-map' : 'select',
            'apply' => $apply,
        ];
    }

    /** @return array<string, string> */
    private function tabs(string $module): array
    {
        return match ($module) {
            'subscriptions' => [
                'plans' => 'Plans',
                'subscribers' => 'Subscribers',
            ],
            'gift-cards-referrals' => [
                'gift-cards' => 'Gift Cards',
                'referrals' => 'Referrals',
                'earnings' => 'Earnings',
            ],
            'rider-cash' => [
                'cash' => 'Cash Settlements',
                'withdrawals' => 'Withdrawals',
            ],
            'payment-intents' => [
                'intents' => 'Payment Intents',
                'webhooks' => 'Webhook Events',
            ],
            'reviews' => [
                'product' => 'Product Reviews',
                'seller' => 'Seller Feedback',
                'delivery' => 'Delivery Feedback',
            ],
            'faqs' => [
                'general' => 'General FAQs',
                'product' => 'Product FAQs',
            ],
            default => [],
        };
    }

    private function resolveView(string $module, Request $request): string
    {
        $allowed = $this->tabs($module);

        if ($allowed === []) {
            return 'default';
        }

        $view = $request->string('view')->trim()->toString();
        $view = $view !== '' ? $view : array_key_first($allowed);

        abort_unless(array_key_exists($view, $allowed), 404);

        return $view;
    }

    /** @return array<int, string> */
    private function distinctValues(string $model, string $column): array
    {
        return $model::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->input('per_page', 20)));
    }

    /** @return array<string, mixed> */
    private function decodeJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
