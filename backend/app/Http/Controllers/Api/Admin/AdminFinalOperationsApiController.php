<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\CommandRunLog;
use App\Models\DeliveryBoyCashTransaction;
use App\Models\DeliveryFeedback;
use App\Models\PaymentIntent;
use App\Models\ProductCollection;
use App\Models\SellerFeedback;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanLimit;
use App\Models\SystemRelease;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\WebhookEvent;
use App\Services\AdvertisingService;
use App\Services\AuditService;
use App\Services\DeliveryCashFeedbackService;
use App\Services\SystemOperationsService;
use App\Services\TaxCollectionAddonService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminFinalOperationsApiController extends Controller
{
    public function __construct(
        protected TaxCollectionAddonService $catalogue,
        protected AdvertisingService $advertising,
        protected DeliveryCashFeedbackService $cash,
        protected SystemOperationsService $operations,
        protected AuditService $audit
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Final operations dashboard fetched.',
            [
                'pos' => [
                    'orders_today' => DB::table('orders')
                        ->where('source', 'pos')
                        ->whereDate('created_at', today())
                        ->count(),
                    'sales_today' => (float) DB::table('orders')
                        ->where('source', 'pos')
                        ->where('payment_status', 'completed')
                        ->whereDate('created_at', today())
                        ->sum('final_total'),
                    'refunds_today' => (float) DB::table('pos_refunds')
                        ->where('status', 'completed')
                        ->whereDate('created_at', today())
                        ->sum('amount'),
                ],
                'subscriptions' => [
                    'active' => SellerSubscription::query()
                        ->whereIn('status', ['trial', 'active'])
                        ->count(),
                    'expiring_in_7_days' => SellerSubscription::query()
                        ->whereIn('status', ['trial', 'active'])
                        ->whereBetween('ends_at', [
                            now(),
                            now()->addDays(7),
                        ])
                        ->count(),
                ],
                'advertising' => [
                    'pending_approval' => AdCampaign::query()
                        ->where('status', 'pending_approval')
                        ->count(),
                    'active' => AdCampaign::query()
                        ->whereIn('status', ['approved', 'active'])
                        ->count(),
                    'spend_today' => (float) DB::table('ad_campaign_stats')
                        ->whereDate('stat_date', today())
                        ->sum('spent_amount'),
                ],
                'bulk_uploads' => [
                    'pending' => DB::table('bulk_upload_jobs')
                        ->where('status', 'pending')
                        ->count(),
                    'processing' => DB::table('bulk_upload_jobs')
                        ->where('status', 'processing')
                        ->count(),
                    'failed_today' => DB::table('bulk_upload_jobs')
                        ->where('status', 'failed')
                        ->whereDate('updated_at', today())
                        ->count(),
                ],
                'cash' => [
                    'pending_remittances' =>
                        DeliveryBoyCashTransaction::query()
                            ->where('type', 'remittance')
                            ->where('status', 'pending')
                            ->count(),
                    'pending_amount' => (float)
                        DeliveryBoyCashTransaction::query()
                            ->where('type', 'remittance')
                            ->where('status', 'pending')
                            ->sum('amount'),
                ],
                'system' => $this->operations->health(),
            ]
        );
    }

    public function taxClasses(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax classes fetched.',
            TaxClass::query()
                ->with('rates')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
        );
    }

    public function saveTaxClass(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:tax_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'rate_ids' => ['nullable', 'array'],
            'rate_ids.*' => ['integer', 'exists:tax_rates,id'],
        ]);

        $class = DB::transaction(function () use (
            $data,
            $request
        ): TaxClass {
            $before = null;
            $rateIds = $data['rate_ids'] ?? null;
            unset($data['rate_ids']);

            if (! empty($data['id'])) {
                $class = TaxClass::query()->findOrFail($data['id']);
                $before = $class->toArray();
                unset($data['id']);
                $class->update($data);
            } else {
                unset($data['id']);
                $data['slug'] = $data['slug']
                    ?? Str::slug($data['name']);
                $class = TaxClass::query()->create($data);
            }

            if ($class->is_default) {
                TaxClass::query()
                    ->where('id', '!=', $class->id)
                    ->update(['is_default' => false]);
            }

            if ($rateIds !== null) {
                $class->rates()->sync($rateIds);
            }

            $this->audit->record(
                $request->user(),
                'tax_class.saved',
                TaxClass::class,
                $class->id,
                $before,
                $class->fresh('rates')->toArray(),
                $request
            );

            return $class->fresh('rates');
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax class saved.',
            $class,
            empty($data['id']) ? 201 : 200
        );
    }

    public function deleteTaxClass(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $class = TaxClass::query()->findOrFail($id);

        if ($class->is_default) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Default tax class cannot be deleted.',
                [],
                422
            );
        }

        $before = $class->toArray();
        $class->delete();

        $this->audit->record(
            $request->user(),
            'tax_class.deleted',
            TaxClass::class,
            $id,
            $before,
            null,
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax class deleted.',
            []
        );
    }

    public function taxRates(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax rates fetched.',
            TaxRate::query()
                ->with('classes')
                ->orderBy('priority')
                ->orderBy('name')
                ->get()
        );
    }

    public function saveTaxRate(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'compound' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'tax_class_ids' => ['nullable', 'array'],
            'tax_class_ids.*' => [
                'integer',
                'exists:tax_classes,id',
            ],
        ]);

        $classIds = $data['tax_class_ids'] ?? null;
        unset($data['tax_class_ids']);

        if (! empty($data['id'])) {
            $rate = TaxRate::query()->findOrFail($data['id']);
            $before = $rate->toArray();
            unset($data['id']);
            $rate->update($data);
        } else {
            unset($data['id']);
            $before = null;
            $rate = TaxRate::query()->create($data);
        }

        if ($classIds !== null) {
            $rate->classes()->sync($classIds);
        }

        $this->audit->record(
            $request->user(),
            'tax_rate.saved',
            TaxRate::class,
            $rate->id,
            $before,
            $rate->fresh('classes')->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax rate saved.',
            $rate->fresh('classes'),
            $before === null ? 201 : 200
        );
    }

    public function deleteTaxRate(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $rate = TaxRate::query()->findOrFail($id);
        $before = $rate->toArray();
        $rate->delete();

        $this->audit->record(
            $request->user(),
            'tax_rate.deleted',
            TaxRate::class,
            $id,
            $before,
            null,
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax rate deleted.',
            []
        );
    }

    public function collections(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Collections fetched.',
            ProductCollection::query()
                ->with('products')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function saveCollection(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:collections,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $collection = ! empty($data['id'])
            ? ProductCollection::query()->findOrFail($data['id'])
            : null;

        unset($data['id']);

        $saved = $this->catalogue->saveCollection(
            $collection,
            $data
        );

        $this->audit->record(
            $request->user(),
            'collection.saved',
            ProductCollection::class,
            $saved->id,
            $collection?->toArray(),
            $saved->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Collection saved.',
            $saved,
            $collection ? 200 : 201
        );
    }

    public function deleteCollection(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $collection = ProductCollection::query()
            ->findOrFail($id);

        $before = $collection->toArray();
        $collection->delete();

        $this->audit->record(
            $request->user(),
            'collection.deleted',
            ProductCollection::class,
            $id,
            $before,
            null,
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Collection deleted.',
            []
        );
    }

    public function subscriptionPlans(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription plans fetched.',
            SubscriptionPlan::query()
                ->with('limits')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function saveSubscriptionPlan(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'id' => [
                'nullable',
                'integer',
                'exists:subscription_plans,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'limits' => ['required', 'array', 'min:1'],
            'limits.*.feature_key' => [
                'required',
                'string',
                'max:80',
            ],
            'limits.*.limit_value' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'limits.*.is_unlimited' => ['nullable', 'boolean'],
            'limits.*.metadata' => ['nullable', 'array'],
        ]);

        $plan = DB::transaction(function () use (
            $data,
            $request
        ): SubscriptionPlan {
            $limits = $data['limits'];
            unset($data['limits']);

            if (! empty($data['id'])) {
                $plan = SubscriptionPlan::query()
                    ->findOrFail($data['id']);
                $before = $plan->load('limits')->toArray();
                unset($data['id']);
                $plan->update($data);
            } else {
                unset($data['id']);
                $before = null;
                $data['slug'] = $data['slug']
                    ?? Str::slug($data['title']);
                $plan = SubscriptionPlan::query()->create($data);
            }

            $keys = [];

            foreach ($limits as $limit) {
                $keys[] = $limit['feature_key'];

                SubscriptionPlanLimit::query()->updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'feature_key' => $limit['feature_key'],
                    ],
                    [
                        'limit_value' =>
                            $limit['limit_value'] ?? null,
                        'is_unlimited' =>
                            $limit['is_unlimited'] ?? false,
                        'metadata' =>
                            $limit['metadata'] ?? null,
                    ]
                );
            }

            SubscriptionPlanLimit::query()
                ->where('subscription_plan_id', $plan->id)
                ->whereNotIn('feature_key', $keys)
                ->delete();

            $saved = $plan->fresh('limits');

            $this->audit->record(
                $request->user(),
                'subscription_plan.saved',
                SubscriptionPlan::class,
                $saved->id,
                $before,
                $saved->toArray(),
                $request
            );

            return $saved;
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Subscription plan saved.',
            $plan,
            201
        );
    }

    public function sellerSubscriptions(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller subscriptions fetched.',
            SellerSubscription::query()
                ->when(
                    $request->filled('status'),
                    fn ($query) =>
                        $query->where(
                            'status',
                            $request->string('status')->toString()
                        )
                )
                ->with(['seller.owner', 'plan', 'usages'])
                ->latest('starts_at')
                ->paginate(
                    min(
                        100,
                        max(
                            1,
                            (int) $request->input('per_page', 15)
                        )
                    )
                )
        );
    }

    public function adCampaigns(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaigns fetched.',
            AdCampaign::query()
                ->when(
                    $request->filled('status'),
                    fn ($query) =>
                        $query->where(
                            'status',
                            $request->string('status')->toString()
                        )
                )
                ->with(['seller.owner', 'store', 'product', 'stats'])
                ->latest()
                ->paginate(
                    min(
                        100,
                        max(
                            1,
                            (int) $request->input('per_page', 15)
                        )
                    )
                )
        );
    }

    public function approveAd(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $campaign = $this->advertising->approve(
            AdCampaign::query()->findOrFail($id),
            $request->user()
        );

        $this->audit->record(
            $request->user(),
            'ad_campaign.approved',
            AdCampaign::class,
            $campaign->id,
            null,
            $campaign->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign approved.',
            $campaign
        );
    }

    public function rejectAd(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $campaign = $this->advertising->reject(
            AdCampaign::query()->findOrFail($id),
            $request->user(),
            $data['reason']
        );

        $this->audit->record(
            $request->user(),
            'ad_campaign.rejected',
            AdCampaign::class,
            $campaign->id,
            null,
            $campaign->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertising campaign rejected.',
            $campaign
        );
    }

    public function cashTransactions(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery cash transactions fetched.',
            DeliveryBoyCashTransaction::query()
                ->when(
                    $request->filled('type'),
                    fn ($query) =>
                        $query->where(
                            'type',
                            $request->string('type')->toString()
                        )
                )
                ->when(
                    $request->filled('status'),
                    fn ($query) =>
                        $query->where(
                            'status',
                            $request->string('status')->toString()
                        )
                )
                ->with(['deliveryBoy.user', 'order'])
                ->latest()
                ->paginate(
                    min(
                        100,
                        max(
                            1,
                            (int) $request->input('per_page', 15)
                        )
                    )
                )
        );
    }

    public function processCashTransaction(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['approved', 'rejected']),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction = $this->cash->processCashTransaction(
            DeliveryBoyCashTransaction::query()->findOrFail($id),
            $request->user(),
            $data['status'],
            $data['note'] ?? null
        );

        $this->audit->record(
            $request->user(),
            'delivery_cash.processed',
            DeliveryBoyCashTransaction::class,
            $transaction->id,
            null,
            $transaction->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery cash transaction processed.',
            $transaction
        );
    }

    public function feedback(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Feedback moderation queue fetched.',
            [
                'seller_feedback' => SellerFeedback::query()
                    ->with('seller')
                    ->latest()
                    ->paginate(
                        min(
                            100,
                            max(
                                1,
                                (int) $request->input(
                                    'per_page',
                                    15
                                )
                            )
                        ),
                        ['*'],
                        'seller_page'
                    ),
                'delivery_feedback' => DeliveryFeedback::query()
                    ->with('deliveryBoy.user')
                    ->latest()
                    ->paginate(
                        min(
                            100,
                            max(
                                1,
                                (int) $request->input(
                                    'per_page',
                                    15
                                )
                            )
                        ),
                        ['*'],
                        'delivery_page'
                    ),
            ]
        );
    }

    public function moderateSellerFeedback(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['published', 'hidden']),
            ],
        ]);

        $feedback = SellerFeedback::query()->findOrFail($id);
        $before = $feedback->toArray();
        $feedback->update(['status' => $data['status']]);

        $this->audit->record(
            $request->user(),
            'seller_feedback.moderated',
            SellerFeedback::class,
            $feedback->id,
            $before,
            $feedback->fresh()->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback moderated.',
            $feedback->fresh()
        );
    }

    public function moderateDeliveryFeedback(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['published', 'hidden']),
            ],
        ]);

        $feedback = DeliveryFeedback::query()->findOrFail($id);
        $before = $feedback->toArray();
        $feedback->update(['status' => $data['status']]);

        $this->audit->record(
            $request->user(),
            'delivery_feedback.moderated',
            DeliveryFeedback::class,
            $feedback->id,
            $before,
            $feedback->fresh()->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery feedback moderated.',
            $feedback->fresh()
        );
    }

    public function posDashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $rangeStart = $request->date('from')
            ?? now()->subDays(30)->startOfDay();

        $rangeEnd = $request->date('to')
            ?? now()->endOfDay();

        return ApiResponseType::sendJsonResponse(
            true,
            'POS dashboard fetched.',
            [
                'from' => $rangeStart->toDateString(),
                'to' => $rangeEnd->toDateString(),
                'orders' => DB::table('orders')
                    ->where('source', 'pos')
                    ->whereBetween('created_at', [
                        $rangeStart,
                        $rangeEnd,
                    ])
                    ->count(),
                'gross_sales' => (float) DB::table('orders')
                    ->where('source', 'pos')
                    ->where('payment_status', 'completed')
                    ->whereBetween('created_at', [
                        $rangeStart,
                        $rangeEnd,
                    ])
                    ->sum('final_total'),
                'refunds' => (float) DB::table('pos_refunds')
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [
                        $rangeStart,
                        $rangeEnd,
                    ])
                    ->sum('amount'),
                'payment_methods' => DB::table(
                    'order_payment_transactions as payments'
                )
                    ->join(
                        'orders',
                        'orders.id',
                        '=',
                        'payments.order_id'
                    )
                    ->where('orders.source', 'pos')
                    ->whereBetween('payments.created_at', [
                        $rangeStart,
                        $rangeEnd,
                    ])
                    ->groupBy('payments.payment_method')
                    ->selectRaw(
                        'payments.payment_method, count(*) as transactions, sum(payments.amount) as amount'
                    )
                    ->get(),
            ]
        );
    }

    public function paymentOperations(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Payment operations fetched.',
            [
                'intents' => PaymentIntent::query()
                    ->with('order')
                    ->latest()
                    ->paginate(
                        min(
                            100,
                            max(
                                1,
                                (int) $request->input(
                                    'per_page',
                                    15
                                )
                            )
                        )
                    ),
                'webhooks' => WebhookEvent::query()
                    ->latest()
                    ->limit(100)
                    ->get(),
            ]
        );
    }

    public function commandLogs(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Command run logs fetched.',
            CommandRunLog::query()
                ->when(
                    $request->filled('command'),
                    fn ($query) =>
                        $query->where(
                            'command',
                            $request->string('command')->toString()
                        )
                )
                ->latest('started_at')
                ->paginate(
                    min(
                        100,
                        max(
                            1,
                            (int) $request->input('per_page', 15)
                        )
                    )
                )
        );
    }

    public function runCommand(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'command' => [
                'required',
                Rule::in([
                    'bulk-uploads:run',
                    'bulk-uploads:prune',
                    'subscriptions:expire',
                    'subscriptions:sync-usage',
                    'ads:settle-expired',
                    'ads:prune-dedup',
                    'delivery-cash:sync',
                    'orders:check-stuck',
                    'system:health-check',
                    'openapi:export',
                ]),
            ],
        ]);

        $result = $this->operations->runLogged(
            $data['command'],
            'admin_api',
            function () use ($data): array {
                $exitCode = Artisan::call($data['command']);

                return [
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ];
            }
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Command completed.',
            $result
        );
    }

    public function releases(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'System releases fetched.',
            SystemRelease::query()->latest()->get()
        );
    }

    public function saveRelease(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:100'],
            'status' => [
                'required',
                Rule::in([
                    'planned',
                    'testing',
                    'ready',
                    'deployed',
                    'rolled_back',
                ]),
            ],
            'checksum' => ['nullable', 'string', 'max:255'],
            'release_notes' => ['nullable', 'string'],
        ]);

        $release = $this->operations->recordRelease(
            $data['version'],
            $data['status'],
            $data['checksum'] ?? null,
            $data['release_notes'] ?? null,
            $request->user()->id
        );

        $this->audit->record(
            $request->user(),
            'system_release.saved',
            SystemRelease::class,
            $release->id,
            null,
            $release->toArray(),
            $request
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'System release saved.',
            $release,
            201
        );
    }

    private function ensureAdmin(Request $request): void
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless(
            $panel === GuardNameEnum::ADMIN->value,
            403
        );
    }
}
