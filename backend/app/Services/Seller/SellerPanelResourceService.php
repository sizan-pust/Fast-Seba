<?php

namespace App\Services\Seller;

use App\Enums\GuardNameEnum;
use App\Models\AdCampaign;
use App\Models\AddonGroup;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Models\Brand;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdvertisingService;
use App\Services\BulkUploadService;
use App\Services\OrderService;
use App\Services\PosService;
use App\Services\ReturnRefundService;
use App\Services\SellerCatalogueManagementService;
use App\Services\SellerFinanceService;
use App\Services\SubscriptionService;
use App\Services\TaxCollectionAddonService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerPanelResourceService
{
    public const LIVE_MODULES = [
        'stores',
        'products',
        'attributes',
        'addons',
        'inventory',
        'orders',
        'returns',
        'pos',
        'wallet',
        'statements',
        'withdrawals',
        'advertisements',
        'subscriptions',
        'team',
        'reviews',
        'feedback',
        'prescriptions',
        'product-faqs',
        'notifications',
        'bulk-uploads',
    ];

    public function __construct(
        private readonly SellerCatalogueManagementService $catalogue,
        private readonly TaxCollectionAddonService $addons,
        private readonly OrderService $orders,
        private readonly ReturnRefundService $returns,
        private readonly SellerFinanceService $finance,
        private readonly SubscriptionService $subscriptions,
        private readonly AdvertisingService $advertising,
        private readonly BulkUploadService $bulk,
        private readonly PosService $pos,
        private readonly SellerPanelContext $context
    ) {
    }

    public function index(
        string $module,
        Request $request,
        User $user,
        Seller $seller
    ): array {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, 'view');

        return match ($module) {
            'stores' => $this->storesIndex($request, $seller),
            'products' => $this->productsIndex($request, $seller),
            'attributes' => $this->attributesIndex($request, $seller),
            'addons' => $this->addonsIndex($request, $seller),
            'inventory' => $this->inventoryIndex($request, $seller),
            'orders' => $this->ordersIndex($request, $seller),
            'returns' => $this->returnsIndex($request, $seller),
            'pos' => $this->posIndex($request, $seller),
            'wallet' => $this->walletIndex($request, $seller),
            'statements' => $this->statementsIndex($request, $seller),
            'withdrawals' => $this->withdrawalsIndex($request, $seller),
            'advertisements' => $this->advertisementsIndex($request, $seller),
            'subscriptions' => $this->subscriptionsIndex($request, $seller),
            'team' => $this->teamIndex($request, $seller),
            'reviews' => $this->reviewsIndex($request, $seller),
            'feedback' => $this->feedbackIndex($request, $seller),
            'prescriptions' => $this->prescriptionsIndex($request, $seller),
            'product-faqs' => $this->faqsIndex($request, $seller),
            'notifications' => $this->notificationsIndex($request, $user),
            'bulk-uploads' => $this->bulkUploadsIndex($request, $seller),
        };
    }

    public function show(
        string $module,
        string $id,
        Request $request,
        User $user,
        Seller $seller
    ): array {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, 'view');

        return match ($module) {
            'stores' => $this->showStore((int) $id, $seller),
            'products' => $this->showProduct((int) $id, $seller),
            'attributes' => $this->showAttribute((int) $id, $seller),
            'addons' => $this->showAddon((int) $id, $seller),
            'inventory' => $this->showInventory((int) $id, $seller),
            'orders' => $this->showOrder((int) $id, $seller),
            'returns' => $this->showReturn((int) $id, $seller),
            'pos' => $this->showPosOrder((int) $id, $seller),
            'wallet' => $this->showWalletTransaction((int) $id, $seller),
            'statements' => $this->showStatement((int) $id, $seller),
            'withdrawals' => $this->showWithdrawal((int) $id, $seller),
            'advertisements' => $this->showAdvertisement((int) $id, $seller),
            'subscriptions' => $this->showSubscription((int) $id, $request, $seller),
            'team' => $this->showTeamRecord($id, $request, $seller),
            'reviews' => $this->showReview((int) $id, $seller),
            'feedback' => $this->showFeedback((int) $id, $seller),
            'prescriptions' => $this->showPrescription((int) $id, $seller),
            'product-faqs' => $this->showFaq((int) $id, $seller),
            'notifications' => $this->showNotification((int) $id, $user),
            'bulk-uploads' => $this->showBulkUpload($id, $seller),
        };
    }

    public function form(
        string $module,
        Request $request,
        User $user,
        Seller $seller,
        ?string $id = null
    ): array {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, $id ? 'update' : 'create');

        return match ($module) {
            'stores' => $this->storeForm($seller, $id ? (int) $id : null),
            'products' => $this->productForm($seller, $id ? (int) $id : null),
            'attributes' => $this->attributeForm($seller, $id ? (int) $id : null),
            'addons' => $this->addonForm($seller, $id ? (int) $id : null),
            'pos' => $this->posForm($seller),
            'withdrawals' => $this->withdrawalForm(),
            'advertisements' => $this->advertisementForm($seller, $id ? (int) $id : null),
            'team' => $this->teamForm($request, $seller, $id),
            'product-faqs' => $this->faqForm($seller, $id ? (int) $id : null),
            default => abort(404),
        };
    }

    public function save(
        string $module,
        Request $request,
        User $user,
        Seller $seller,
        ?string $id = null
    ): Model {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, $id ? 'update' : 'create');

        return match ($module) {
            'stores' => $this->saveStore($request, $seller, $id ? (int) $id : null),
            'products' => $this->saveProduct($request, $user, $seller, $id ? (int) $id : null),
            'attributes' => $this->saveAttribute($request, $seller, $id ? (int) $id : null),
            'addons' => $this->saveAddon($request, $seller, $id ? (int) $id : null),
            'pos' => $this->savePosOrder($request, $user, $seller),
            'withdrawals' => $this->saveWithdrawal($request, $seller),
            'advertisements' => $this->saveAdvertisement($request, $user, $seller, $id ? (int) $id : null),
            'team' => $this->saveTeamRecord($request, $seller, $id),
            'product-faqs' => $this->saveFaq($request, $user, $seller, $id ? (int) $id : null),
            default => abort(404),
        };
    }

    public function destroy(
        string $module,
        string $id,
        Request $request,
        User $user,
        Seller $seller
    ): void {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, 'delete');

        match ($module) {
            'stores' => $this->deleteStore((int) $id, $seller),
            'products' => $this->deleteProduct((int) $id, $seller),
            'attributes' => $this->deleteAttribute((int) $id, $seller),
            'addons' => $this->deleteAddon((int) $id, $seller),
            'advertisements' => $this->deleteAdvertisement((int) $id, $seller),
            'team' => $this->deleteTeamRecord($id, $request, $seller, $user),
            'product-faqs' => $this->deleteFaq((int) $id, $seller),
            default => abort(404),
        };
    }

    public function action(
        string $module,
        string $id,
        Request $request,
        User $user,
        Seller $seller
    ): string {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, 'action');

        return match ($module) {
            'stores' => $this->storeAction((int) $id, $request, $seller),
            'products' => $this->productAction((int) $id, $request, $seller),
            'inventory' => $this->inventoryAction((int) $id, $request, $user, $seller),
            'orders' => $this->orderAction((int) $id, $request, $user, $seller),
            'returns' => $this->returnAction((int) $id, $request, $seller),
            'advertisements' => $this->advertisementAction((int) $id, $request, $seller),
            'subscriptions' => $this->subscriptionAction((int) $id, $request, $user, $seller),
            'reviews' => $this->reviewAction((int) $id, $request, $user, $seller),
            'feedback' => $this->feedbackAction((int) $id, $request, $seller),
            'prescriptions' => $this->prescriptionAction((int) $id, $request, $seller),
            'product-faqs' => $this->faqAction((int) $id, $request, $user, $seller),
            'notifications' => $this->notificationAction((int) $id, $request, $user),
            default => abort(404),
        };
    }

    public function pageAction(
        string $module,
        Request $request,
        User $user,
        Seller $seller
    ): string|StreamedResponse {
        $this->guardModule($module);
        $this->authorize($module, $user, $seller, 'action');

        return match ($module) {
            'notifications' => $this->notificationsPageAction($request, $user),
            'bulk-uploads' => $this->bulkUploadPageAction($request, $user, $seller),
            default => abort(404),
        };
    }

    private function guardModule(string $module): void
    {
        abort_unless(in_array($module, self::LIVE_MODULES, true), 404);
    }

    private function authorize(string $module, User $user, Seller $seller, string $ability): void
    {
        $permission = match ($module) {
            'stores' => 'seller.stores.manage',
            'products' => 'seller.products.manage',
            'attributes' => 'seller.attributes.manage',
            'addons' => 'seller.addons.manage',
            'inventory' => 'seller.inventory.manage',
            'orders' => 'seller.orders.view',
            'returns' => 'seller.returns.manage',
            'pos' => 'seller.pos.manage',
            'wallet', 'statements' => 'seller.finance.view',
            'withdrawals' => 'seller.withdrawals.manage',
            'advertisements' => 'seller.ads.manage',
            'subscriptions' => 'seller.subscriptions.manage',
            'team' => 'seller.team.manage',
            'reviews' => 'seller.reviews.manage',
            'feedback' => 'seller.feedback.manage',
            'prescriptions' => 'seller.prescriptions.manage',
            'product-faqs' => 'seller.faqs.manage',
            'notifications' => 'seller.notifications.view',
            'bulk-uploads' => 'seller.bulk_uploads.manage',
        };

        abort_unless($this->context->can($user, $seller, $permission), 403);
    }

    private function baseIndex(
        string $module,
        string $title,
        string $description,
        array $columns,
        array $rows,
        LengthAwarePaginator $paginator,
        array $stats = [],
        array $filters = [],
        bool $canCreate = false,
        array $pageActions = [],
        array $tabs = [],
        string $view = 'default'
    ): array {
        return compact(
            'module',
            'title',
            'description',
            'columns',
            'rows',
            'paginator',
            'stats',
            'filters',
            'canCreate',
            'pageActions',
            'tabs',
            'view'
        );
    }

    private function baseForm(
        string $module,
        string $title,
        array $fields,
        bool $isEdit = false,
        int|string|null $recordId = null,
        array $query = []
    ): array {
        return compact('module', 'title', 'fields', 'isEdit', 'recordId', 'query');
    }

    private function baseShow(
        string $module,
        string $title,
        int|string $recordId,
        array $sections,
        array $actions = [],
        array $tables = [],
        bool $editable = false,
        bool $deletable = false,
        array $query = []
    ): array {
        return compact(
            'module',
            'title',
            'recordId',
            'sections',
            'actions',
            'tables',
            'editable',
            'deletable',
            'query'
        );
    }

    private function row(int|string $id, array $cells, bool $editable = false): array
    {
        return compact('id', 'cells', 'editable');
    }

    private function detail(string $label, mixed $value, string $format = 'text'): array
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $format = 'pre';
        }

        return compact('label', 'value', 'format');
    }

    private function searchFilter(string $placeholder): array
    {
        return ['name' => 'search', 'label' => 'Search', 'type' => 'text', 'placeholder' => $placeholder];
    }

    private function selectFilter(string $name, string $label, array $options): array
    {
        return compact('name', 'label', 'options') + ['type' => 'select'];
    }

    private function optionList(iterable $items, string $labelField = 'name'): array
    {
        $options = [];

        foreach ($items as $item) {
            $options[(string) $item->getKey()] = (string) data_get($item, $labelField);
        }

        return $options;
    }

    private function money(mixed $value, string $currency = '৳'): string
    {
        return $currency.number_format((float) $value, 2);
    }

    private function date(mixed $value): string
    {
        return $value?->format('d M Y H:i') ?? '—';
    }

    private function storesIndex(Request $request, Seller $seller): array
    {
        $items = Store::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $sub) => $sub
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('contact_email', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->withCount('storeProductVariants')
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (Store $store) => $this->row(
            $store->id,
            [
                '#'.$store->id,
                $store->name,
                $store->city ?: '—',
                $store->store_product_variants_count,
                $store->status,
                $store->verification_status,
                $this->date($store->created_at),
            ],
            true
        ))->all();

        return $this->baseIndex(
            'stores',
            'Stores',
            'Manage seller outlets, delivery settings and POS availability.',
            ['ID', 'Store', 'City', 'Inventory items', 'Status', 'Verification', 'Created'],
            $rows,
            $items,
            [
                'Total stores' => Store::query()->where('seller_id', $seller->id)->count(),
                'Online' => Store::query()->where('seller_id', $seller->id)->where('status', 'online')->count(),
                'Pending verification' => Store::query()->where('seller_id', $seller->id)->where('verification_status', 'pending')->count(),
            ],
            [
                $this->searchFilter('Store name, city or email'),
                $this->selectFilter('status', 'Status', ['online' => 'Online', 'offline' => 'Offline']),
            ],
            true
        );
    }

    private function storeForm(Seller $seller, ?int $id): array
    {
        $store = $id
            ? Store::query()->where('seller_id', $seller->id)->with('zones')->findOrFail($id)
            : new Store([
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'currency_code' => 'BDT',
                'status' => 'offline',
                'allows_pickup' => true,
            ]);

        $zones = \App\Models\DeliveryZone::query()->where('status', 'active')->orderBy('name')->get();

        return $this->baseForm('stores', $id ? 'Edit Store' : 'Create Store', [
            ['name' => 'name', 'label' => 'Store name', 'type' => 'text', 'value' => $store->name, 'required' => true],
            ['name' => 'contact_email', 'label' => 'Contact email', 'type' => 'email', 'value' => $store->contact_email],
            ['name' => 'contact_number', 'label' => 'Contact number', 'type' => 'text', 'value' => $store->contact_number],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'value' => $store->address],
            ['name' => 'city', 'label' => 'City', 'type' => 'text', 'value' => $store->city],
            ['name' => 'landmark', 'label' => 'Landmark', 'type' => 'text', 'value' => $store->landmark],
            ['name' => 'state', 'label' => 'State / Division', 'type' => 'text', 'value' => $store->state],
            ['name' => 'zipcode', 'label' => 'Postal code', 'type' => 'text', 'value' => $store->zipcode],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => '0.00000001', 'value' => $store->latitude],
            ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => '0.00000001', 'value' => $store->longitude],
            ['name' => 'max_delivery_distance', 'label' => 'Maximum delivery distance (km)', 'type' => 'number', 'step' => '0.01', 'value' => $store->max_delivery_distance],
            ['name' => 'order_preparation_time', 'label' => 'Preparation time (minutes)', 'type' => 'number', 'value' => $store->order_preparation_time],
            ['name' => 'zone_ids', 'label' => 'Delivery zones', 'type' => 'multiselect', 'options' => $this->optionList($zones), 'value' => $store->zones?->pluck('id')->map(fn ($value) => (string) $value)->all() ?? []],
            ['name' => 'allows_pickup', 'label' => 'Allow customer pickup', 'type' => 'checkbox', 'value' => (bool) $store->allows_pickup],
            ['name' => 'pos_enabled', 'label' => 'Enable point of sale', 'type' => 'checkbox', 'value' => (bool) $store->pos_enabled],
            ['name' => 'pickup_instructions', 'label' => 'Pickup instructions', 'type' => 'textarea', 'value' => $store->pickup_instructions],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'value' => $store->description],
        ], $id !== null, $id);
    }

    private function saveStore(Request $request, Seller $seller, ?int $id): Store
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_number' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_delivery_distance' => ['nullable', 'numeric', 'min:0'],
            'order_preparation_time' => ['nullable', 'integer', 'min:0'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:delivery_zones,id'],
            'allows_pickup' => ['nullable', 'boolean'],
            'pos_enabled' => ['nullable', 'boolean'],
            'pickup_instructions' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
        ]);

        $data['allows_pickup'] = $request->boolean('allows_pickup');
        $data['pos_enabled'] = $request->boolean('pos_enabled');
        $data['country'] = 'Bangladesh';
        $data['country_code'] = '+880';
        $data['currency_code'] = 'BDT';

        return $id
            ? $this->catalogue->updateStore($seller, $id, $data)
            : $this->catalogue->store($seller, $data);
    }

    private function showStore(int $id, Seller $seller): array
    {
        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->with(['zones', 'storeProductVariants.productVariant.product'])
            ->findOrFail($id);

        return $this->baseShow('stores', $store->name, $store->id, [[
            'title' => 'Store details',
            'items' => [
                $this->detail('Name', $store->name),
                $this->detail('Slug', $store->slug),
                $this->detail('Contact email', $store->contact_email),
                $this->detail('Contact number', $store->contact_number),
                $this->detail('Address', $store->address),
                $this->detail('City', $store->city),
                $this->detail('Coordinates', trim(($store->latitude ?? '').', '.($store->longitude ?? ''), ', ')),
                $this->detail('Status', $store->status, 'status'),
                $this->detail('Verification', $store->verification_status, 'status'),
                $this->detail('Visibility', $store->visibility_status, 'status'),
                $this->detail('Pickup enabled', $store->allows_pickup ? 'Yes' : 'No'),
                $this->detail('Delivery zones', $store->zones->pluck('name')->implode(', ') ?: '—'),
                $this->detail('Created', $this->date($store->created_at)),
            ],
        ]], [
            [
                'label' => $store->status === 'online' ? 'Take store offline' : 'Put store online',
                'action' => 'status',
                'tone' => $store->status === 'online' ? 'warning' : 'success',
                'fields' => [[
                    'name' => 'status',
                    'label' => 'Status',
                    'type' => 'hidden',
                    'value' => $store->status === 'online' ? 'offline' : 'online',
                ]],
            ],
        ], [[
            'title' => 'Inventory',
            'columns' => ['Product', 'Variant', 'SKU', 'Stock', 'Price'],
            'rows' => $store->storeProductVariants->map(fn (StoreProductVariant $inventory) => [
                $inventory->productVariant?->product?->title ?? '—',
                $inventory->productVariant?->title ?? '—',
                $inventory->sku,
                $inventory->stock,
                $this->money($inventory->price),
            ])->all(),
        ]], true, ! $store->storeProductVariants()->exists());
    }

    private function storeAction(int $id, Request $request, Seller $seller): string
    {
        $data = $request->validate(['status' => ['required', Rule::in(['online', 'offline'])]]);
        $store = Store::query()->where('seller_id', $seller->id)->findOrFail($id);
        $store->update(['status' => $data['status']]);

        return 'Store status updated.';
    }

    private function deleteStore(int $id, Seller $seller): void
    {
        $store = Store::query()->where('seller_id', $seller->id)->findOrFail($id);

        if ($store->storeProductVariants()->exists()) {
            throw ValidationException::withMessages(['store' => 'Store with inventory cannot be deleted.']);
        }

        $store->delete();
    }

    private function productsIndex(Request $request, Seller $seller): array
    {
        $items = Product::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $sub) => $sub
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('verification_status'), fn (Builder $query) => $query->where('verification_status', $request->string('verification_status')->toString()))
            ->with(['category', 'brand', 'variants.storeProductVariants'])
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (Product $product) => $this->row(
            $product->id,
            [
                '#'.$product->id,
                $product->title,
                $product->category?->title ?? '—',
                $product->brand?->title ?? '—',
                $product->variants->count(),
                $product->variants->flatMap->storeProductVariants->sum('stock'),
                $product->status,
                $product->verification_status,
            ],
            true
        ))->all();

        return $this->baseIndex(
            'products',
            'Products',
            'Create and maintain the seller catalogue, variants and store pricing.',
            ['ID', 'Product', 'Category', 'Brand', 'Variants', 'Stock', 'Status', 'Verification'],
            $rows,
            $items,
            [
                'Total products' => Product::query()->where('seller_id', $seller->id)->count(),
                'Active' => Product::query()->where('seller_id', $seller->id)->where('status', 'active')->count(),
                'Pending approval' => Product::query()->where('seller_id', $seller->id)->where('verification_status', 'pending')->count(),
                'Rejected' => Product::query()->where('seller_id', $seller->id)->where('verification_status', 'rejected')->count(),
            ],
            [
                $this->searchFilter('Product title or slug'),
                $this->selectFilter('status', 'Status', ['active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive']),
                $this->selectFilter('verification_status', 'Verification', ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ],
            true
        );
    }

    private function productForm(Seller $seller, ?int $id): array
    {
        $product = $id
            ? $this->catalogue->product($seller, $id)
            : new Product(['status' => 'active', 'minimum_order_quantity' => 1, 'quantity_step_size' => 1]);
        $variant = $product->exists ? $product->variants->first() : null;
        $inventory = $variant?->storeProductVariants?->first();

        return $this->baseForm('products', $id ? 'Edit Product' : 'Create Product', [
            ['name' => 'title', 'label' => 'Product title', 'type' => 'text', 'value' => $product->title, 'required' => true],
            ['name' => 'category_id', 'label' => 'Primary category', 'type' => 'select', 'options' => $this->optionList(Category::query()->where('status', 'active')->orderBy('title')->get(), 'title'), 'value' => $product->category_id, 'required' => true],
            ['name' => 'brand_id', 'label' => 'Brand', 'type' => 'select', 'options' => $this->optionList(Brand::query()->where('status', 'active')->orderBy('title')->get(), 'title'), 'value' => $product->brand_id, 'nullable' => true],
            ['name' => 'short_description', 'label' => 'Short description', 'type' => 'textarea', 'value' => $product->short_description],
            ['name' => 'description', 'label' => 'Full description', 'type' => 'textarea', 'value' => $product->description],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive'], 'value' => $product->status ?: 'active', 'required' => true],
            ['name' => 'minimum_order_quantity', 'label' => 'Minimum order quantity', 'type' => 'number', 'value' => $product->minimum_order_quantity ?: 1],
            ['name' => 'quantity_step_size', 'label' => 'Quantity step size', 'type' => 'number', 'value' => $product->quantity_step_size ?: 1],
            ['name' => 'total_allowed_quantity', 'label' => 'Maximum allowed quantity', 'type' => 'number', 'value' => $product->total_allowed_quantity],
            ['name' => 'is_returnable', 'label' => 'Returnable', 'type' => 'checkbox', 'value' => (bool) $product->is_returnable],
            ['name' => 'returnable_days', 'label' => 'Return window (days)', 'type' => 'number', 'value' => $product->returnable_days],
            ['name' => 'is_cancelable', 'label' => 'Cancelable', 'type' => 'checkbox', 'value' => (bool) $product->is_cancelable],
            ['name' => 'requires_otp', 'label' => 'Require delivery OTP', 'type' => 'checkbox', 'value' => (bool) $product->requires_otp],
            ['name' => 'featured', 'label' => 'Featured product', 'type' => 'checkbox', 'value' => (bool) $product->featured],
            ['name' => 'variant_title', 'label' => 'Variant title', 'type' => 'text', 'value' => $variant?->title ?? 'Default', 'required' => true],
            ['name' => 'barcode', 'label' => 'Barcode', 'type' => 'text', 'value' => $variant?->barcode],
            ['name' => 'store_id', 'label' => 'Store', 'type' => 'select', 'options' => $this->optionList(Store::query()->where('seller_id', $seller->id)->orderBy('name')->get()), 'value' => $inventory?->store_id, 'required' => true],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text', 'value' => $inventory?->sku, 'required' => true],
            ['name' => 'price', 'label' => 'Price (BDT)', 'type' => 'number', 'step' => '0.01', 'value' => $inventory?->price, 'required' => true],
            ['name' => 'special_price', 'label' => 'Special price (BDT)', 'type' => 'number', 'step' => '0.01', 'value' => $inventory?->special_price],
            ['name' => 'cost', 'label' => 'Cost (BDT)', 'type' => 'number', 'step' => '0.01', 'value' => $inventory?->cost],
            ['name' => 'stock', 'label' => 'Stock', 'type' => 'number', 'value' => $inventory?->stock ?? 0, 'required' => true],
            ['name' => 'low_stock_threshold', 'label' => 'Low-stock threshold', 'type' => 'number', 'value' => $inventory?->low_stock_threshold ?? 5],
        ], $id !== null, $id);
    }

    private function saveProduct(Request $request, User $user, Seller $seller, ?int $id): Product
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'draft', 'inactive'])],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_step_size' => ['nullable', 'integer', 'min:1'],
            'total_allowed_quantity' => ['nullable', 'integer', 'min:1'],
            'is_returnable' => ['nullable', 'boolean'],
            'returnable_days' => ['nullable', 'integer', 'min:1'],
            'is_cancelable' => ['nullable', 'boolean'],
            'requires_otp' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'variant_title' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'sku' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        Store::query()->where('seller_id', $seller->id)->findOrFail((int) $data['store_id']);
        $existing = $id ? $this->catalogue->product($seller, $id) : null;
        $variantId = $existing?->variants?->first()?->id;

        $payload = Arr::only($data, [
            'title', 'category_id', 'brand_id', 'short_description', 'description',
            'status', 'minimum_order_quantity', 'quantity_step_size',
            'total_allowed_quantity', 'returnable_days',
        ]);
        $payload['is_returnable'] = $request->boolean('is_returnable');
        $payload['is_cancelable'] = $request->boolean('is_cancelable');
        $payload['requires_otp'] = $request->boolean('requires_otp');
        $payload['featured'] = $request->boolean('featured');
        $payload['type'] = 'simple';
        $payload['variants'] = [[
            'id' => $variantId,
            'title' => $data['variant_title'],
            'barcode' => $data['barcode'] ?? null,
            'availability' => true,
            'visibility' => 'published',
            'stores' => [[
                'store_id' => (int) $data['store_id'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'special_price' => $data['special_price'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'stock' => (int) $data['stock'],
                'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 5),
                'status' => 'active',
            ]],
        ]];

        return $id
            ? $this->catalogue->updateProduct($seller, $user, $id, $payload)
            : $this->catalogue->createProduct($seller, $user, $payload);
    }

    private function showProduct(int $id, Seller $seller): array
    {
        $product = $this->catalogue->product($seller, $id);

        return $this->baseShow('products', $product->title, $product->id, [[
            'title' => 'Product details',
            'items' => [
                $this->detail('Title', $product->title),
                $this->detail('Slug', $product->slug),
                $this->detail('Category', $product->category?->title),
                $this->detail('Brand', $product->brand?->title),
                $this->detail('Type', $product->type),
                $this->detail('Status', $product->status, 'status'),
                $this->detail('Verification', $product->verification_status, 'status'),
                $this->detail('Rejection reason', $product->rejection_reason),
                $this->detail('Short description', $product->short_description),
                $this->detail('Description', $product->description, 'pre'),
                $this->detail('Returnable', $product->is_returnable ? 'Yes' : 'No'),
                $this->detail('Featured', $product->featured ? 'Yes' : 'No'),
            ],
        ]], [
            [
                'label' => $product->status === 'active' ? 'Move to draft' : 'Activate product',
                'action' => 'status',
                'tone' => $product->status === 'active' ? 'warning' : 'success',
                'fields' => [[
                    'name' => 'status',
                    'type' => 'hidden',
                    'label' => 'Status',
                    'value' => $product->status === 'active' ? 'draft' : 'active',
                ]],
            ],
        ], [[
            'title' => 'Variants and inventory',
            'columns' => ['Variant', 'Barcode', 'Store', 'SKU', 'Price', 'Special', 'Stock'],
            'rows' => $product->variants->flatMap(function (ProductVariant $variant) {
                return $variant->storeProductVariants->map(fn (StoreProductVariant $inventory) => [
                    $variant->title,
                    $variant->barcode ?: '—',
                    $inventory->store?->name ?: '—',
                    $inventory->sku,
                    $this->money($inventory->price),
                    $inventory->special_price ? $this->money($inventory->special_price) : '—',
                    $inventory->stock,
                ]);
            })->values()->all(),
        ]], true, true);
    }

    private function productAction(int $id, Request $request, Seller $seller): string
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'draft', 'inactive'])]]);
        Product::query()->where('seller_id', $seller->id)->findOrFail($id)->update(['status' => $data['status']]);

        return 'Product status updated.';
    }

    private function deleteProduct(int $id, Seller $seller): void
    {
        Product::query()->where('seller_id', $seller->id)->findOrFail($id)->delete();
    }

    private function attributesIndex(Request $request, Seller $seller): array
    {
        $items = GlobalProductAttribute::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('search'), fn (Builder $query) => $query->where('title', 'like', '%'.$request->string('search')->toString().'%'))
            ->withCount('values')
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (GlobalProductAttribute $attribute) => $this->row($attribute->id, [
            '#'.$attribute->id,
            $attribute->title,
            $attribute->label,
            $attribute->swatche_type,
            $attribute->values_count,
            $this->date($attribute->created_at),
        ], true))->all();

        return $this->baseIndex(
            'attributes',
            'Product Attributes',
            'Create seller-owned attributes and swatch values for product variants.',
            ['ID', 'Title', 'Label', 'Swatch type', 'Values', 'Created'],
            $rows,
            $items,
            ['Total attributes' => GlobalProductAttribute::query()->where('seller_id', $seller->id)->count()],
            [$this->searchFilter('Attribute title')],
            true
        );
    }

    private function attributeForm(Seller $seller, ?int $id): array
    {
        $attribute = $id
            ? GlobalProductAttribute::query()->where('seller_id', $seller->id)->with('values')->findOrFail($id)
            : new GlobalProductAttribute(['swatche_type' => 'text']);
        $values = $attribute->exists
            ? $attribute->values->map(fn (GlobalProductAttributeValue $value) => $value->title.'|'.($value->swatche_value ?? ''))->implode("\n")
            : '';

        return $this->baseForm('attributes', $id ? 'Edit Attribute' : 'Create Attribute', [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'value' => $attribute->title, 'required' => true],
            ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'value' => $attribute->label],
            ['name' => 'swatche_type', 'label' => 'Swatch type', 'type' => 'select', 'options' => ['text' => 'Text', 'color' => 'Color', 'image' => 'Image'], 'value' => $attribute->swatche_type ?: 'text'],
            ['name' => 'values_text', 'label' => 'Values', 'type' => 'textarea', 'value' => $values, 'help' => 'One value per line. Format: Title|Swatch value'],
        ], $id !== null, $id);
    }

    private function saveAttribute(Request $request, Seller $seller, ?int $id): GlobalProductAttribute
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'swatche_type' => ['required', Rule::in(['text', 'color', 'image'])],
            'values_text' => ['nullable', 'string'],
        ]);

        $attribute = $id
            ? GlobalProductAttribute::query()->where('seller_id', $seller->id)->findOrFail($id)
            : new GlobalProductAttribute(['seller_id' => $seller->id]);
        $attribute->fill(Arr::only($data, ['title', 'label', 'swatche_type']));
        $attribute->seller_id = $seller->id;
        $attribute->save();

        if ($request->has('values_text')) {
            $attribute->values()->delete();

            foreach (preg_split('/\r\n|\r|\n/', trim((string) $data['values_text'])) as $line) {
                if (trim($line) === '') {
                    continue;
                }

                [$title, $swatch] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                $attribute->values()->create([
                    'title' => $title,
                    'swatche_value' => $swatch ?: $title,
                ]);
            }
        }

        return $attribute->fresh('values');
    }

    private function showAttribute(int $id, Seller $seller): array
    {
        $attribute = GlobalProductAttribute::query()->where('seller_id', $seller->id)->with('values')->findOrFail($id);

        return $this->baseShow('attributes', $attribute->title, $attribute->id, [[
            'title' => 'Attribute details',
            'items' => [
                $this->detail('Title', $attribute->title),
                $this->detail('Label', $attribute->label),
                $this->detail('Slug', $attribute->slug),
                $this->detail('Swatch type', $attribute->swatche_type),
            ],
        ]], [], [[
            'title' => 'Values',
            'columns' => ['ID', 'Title', 'Swatch'],
            'rows' => $attribute->values->map(fn (GlobalProductAttributeValue $value) => [
                $value->id,
                $value->title,
                $value->swatche_value,
            ])->all(),
        ]], true, ! ProductVariant::query()->whereHas('attributes', fn ($query) => $query->where('global_attribute_id', $attribute->id))->exists());
    }

    private function deleteAttribute(int $id, Seller $seller): void
    {
        $attribute = GlobalProductAttribute::query()->where('seller_id', $seller->id)->findOrFail($id);

        if (\App\Models\ProductVariantAttribute::query()->where('global_attribute_id', $attribute->id)->exists()) {
            throw ValidationException::withMessages(['attribute' => 'Attribute used by products cannot be deleted.']);
        }

        $attribute->delete();
    }

    private function addonsIndex(Request $request, Seller $seller): array
    {
        $items = AddonGroup::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('search'), fn (Builder $query) => $query->where('title', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->withCount('items')
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (AddonGroup $group) => $this->row($group->id, [
            '#'.$group->id,
            $group->title,
            $group->selection_type,
            $group->minimum_selection.' - '.$group->maximum_selection,
            $group->is_required ? 'Required' : 'Optional',
            $group->items_count,
            $group->status,
        ], true))->all();

        return $this->baseIndex(
            'addons',
            'Add-on Groups',
            'Configure optional or required add-ons for products and store inventory.',
            ['ID', 'Group', 'Selection', 'Limits', 'Requirement', 'Items', 'Status'],
            $rows,
            $items,
            ['Total groups' => AddonGroup::query()->where('seller_id', $seller->id)->count()],
            [
                $this->searchFilter('Add-on group title'),
                $this->selectFilter('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive']),
            ],
            true
        );
    }

    private function addonForm(Seller $seller, ?int $id): array
    {
        $group = $id
            ? AddonGroup::query()->where('seller_id', $seller->id)->with('items')->findOrFail($id)
            : new AddonGroup(['selection_type' => 'single', 'minimum_selection' => 0, 'maximum_selection' => 1, 'status' => 'active']);
        $items = $group->exists
            ? $group->items->map(fn ($item) => implode('|', [$item->title, $item->default_price, $item->default_cost, $item->status]))->implode("\n")
            : '';

        return $this->baseForm('addons', $id ? 'Edit Add-on Group' : 'Create Add-on Group', [
            ['name' => 'title', 'label' => 'Group title', 'type' => 'text', 'value' => $group->title, 'required' => true],
            ['name' => 'selection_type', 'label' => 'Selection type', 'type' => 'select', 'options' => ['single' => 'Single', 'multiple' => 'Multiple'], 'value' => $group->selection_type],
            ['name' => 'minimum_selection', 'label' => 'Minimum selection', 'type' => 'number', 'value' => $group->minimum_selection],
            ['name' => 'maximum_selection', 'label' => 'Maximum selection', 'type' => 'number', 'value' => $group->maximum_selection],
            ['name' => 'is_required', 'label' => 'Required', 'type' => 'checkbox', 'value' => (bool) $group->is_required],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => $group->status],
            ['name' => 'items_text', 'label' => 'Add-on items', 'type' => 'textarea', 'value' => $items, 'help' => 'One item per line. Format: Title|Price|Cost|Status'],
        ], $id !== null, $id);
    }

    private function saveAddon(Request $request, Seller $seller, ?int $id): AddonGroup
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'minimum_selection' => ['nullable', 'integer', 'min:0'],
            'maximum_selection' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'items_text' => ['nullable', 'string'],
        ]);

        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', trim((string) ($data['items_text'] ?? ''))) as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$title, $price, $cost, $status] = array_pad(array_map('trim', explode('|', $line, 4)), 4, null);
            $items[] = [
                'title' => $title,
                'default_price' => is_numeric($price) ? $price : 0,
                'default_cost' => is_numeric($cost) ? $cost : 0,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            ];
        }

        $payload = Arr::only($data, ['title', 'selection_type', 'minimum_selection', 'maximum_selection', 'status']);
        $payload['is_required'] = $request->boolean('is_required');
        $payload['items'] = $items;
        $group = $id ? AddonGroup::query()->where('seller_id', $seller->id)->findOrFail($id) : null;

        if ($group && $request->has('items_text')) {
            $group->items()->delete();
        }

        return $this->addons->saveGroup($seller, $group, $payload);
    }

    private function showAddon(int $id, Seller $seller): array
    {
        $group = AddonGroup::query()->where('seller_id', $seller->id)->with('items')->findOrFail($id);

        return $this->baseShow('addons', $group->title, $group->id, [[
            'title' => 'Add-on group',
            'items' => [
                $this->detail('Title', $group->title),
                $this->detail('Selection type', $group->selection_type),
                $this->detail('Minimum selection', $group->minimum_selection),
                $this->detail('Maximum selection', $group->maximum_selection),
                $this->detail('Required', $group->is_required ? 'Yes' : 'No'),
                $this->detail('Status', $group->status, 'status'),
            ],
        ]], [], [[
            'title' => 'Items',
            'columns' => ['Item', 'Price', 'Cost', 'Status'],
            'rows' => $group->items->map(fn ($item) => [
                $item->title,
                $this->money($item->default_price),
                $this->money($item->default_cost),
                $item->status,
            ])->all(),
        ]], true, true);
    }

    private function deleteAddon(int $id, Seller $seller): void
    {
        $group = AddonGroup::query()->where('seller_id', $seller->id)->findOrFail($id);
        $used = DB::table('store_product_variant_addons')->where('addon_group_id', $group->id)->exists();

        if ($used) {
            throw ValidationException::withMessages(['addon' => 'Attached add-on group cannot be deleted.']);
        }

        $group->delete();
    }

    private function inventoryIndex(Request $request, Seller $seller): array
    {
        $items = StoreProductVariant::query()
            ->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $sub) => $sub
                    ->where('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('productVariant.product', fn (Builder $product) => $product->where('title', 'like', '%'.$search.'%')));
            })
            ->when($request->filled('store_id'), fn (Builder $query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->with(['store', 'productVariant.product'])
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (StoreProductVariant $inventory) => $this->row($inventory->id, [
            '#'.$inventory->id,
            $inventory->productVariant?->product?->title ?? '—',
            $inventory->productVariant?->title ?? '—',
            $inventory->store?->name ?? '—',
            $inventory->sku,
            $this->money($inventory->price),
            $inventory->stock,
            $inventory->low_stock_threshold,
            $inventory->status,
        ]))->all();

        return $this->baseIndex(
            'inventory',
            'Inventory',
            'Monitor stock, prices and movement history across seller stores.',
            ['ID', 'Product', 'Variant', 'Store', 'SKU', 'Price', 'Stock', 'Low at', 'Status'],
            $rows,
            $items,
            [
                'Inventory items' => StoreProductVariant::query()->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id))->count(),
                'Units in stock' => StoreProductVariant::query()->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id))->sum('stock'),
                'Low stock' => StoreProductVariant::query()->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id))->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            ],
            [
                $this->searchFilter('Product title or SKU'),
                $this->selectFilter('store_id', 'Store', $this->optionList(Store::query()->where('seller_id', $seller->id)->orderBy('name')->get())),
                ['name' => 'low_stock', 'label' => 'Low stock only', 'type' => 'checkbox'],
            ]
        );
    }

    private function showInventory(int $id, Seller $seller): array
    {
        $inventory = StoreProductVariant::query()
            ->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->with(['store', 'productVariant.product'])
            ->findOrFail($id);
        $logs = StoreInventoryLog::query()
            ->where('store_product_variant_id', $inventory->id)
            ->with('creator')
            ->latest()
            ->limit(100)
            ->get();

        return $this->baseShow('inventory', $inventory->productVariant?->product?->title ?? 'Inventory item', $inventory->id, [[
            'title' => 'Inventory details',
            'items' => [
                $this->detail('Product', $inventory->productVariant?->product?->title),
                $this->detail('Variant', $inventory->productVariant?->title),
                $this->detail('Store', $inventory->store?->name),
                $this->detail('SKU', $inventory->sku),
                $this->detail('Price', $this->money($inventory->price)),
                $this->detail('Special price', $inventory->special_price ? $this->money($inventory->special_price) : '—'),
                $this->detail('Cost', $this->money($inventory->cost)),
                $this->detail('Stock', $inventory->stock),
                $this->detail('Low-stock threshold', $inventory->low_stock_threshold),
                $this->detail('Status', $inventory->status, 'status'),
            ],
        ]], [[
            'label' => 'Adjust stock',
            'action' => 'adjust',
            'tone' => 'primary',
            'fields' => [
                ['name' => 'change_type', 'label' => 'Change type', 'type' => 'select', 'options' => ['add' => 'Add', 'remove' => 'Remove', 'adjust' => 'Set exact stock'], 'required' => true],
                ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'required' => true],
                ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea'],
            ],
        ]], [[
            'title' => 'Stock movement history',
            'columns' => ['Type', 'Quantity', 'Previous', 'New', 'Reason', 'By', 'Date'],
            'rows' => $logs->map(fn (StoreInventoryLog $log) => [
                $log->change_type,
                $log->quantity,
                $log->previous_stock,
                $log->new_stock,
                $log->reason,
                $log->creator?->name ?? 'System',
                $this->date($log->created_at),
            ])->all(),
        ]]);
    }

    private function inventoryAction(int $id, Request $request, User $user, Seller $seller): string
    {
        $data = $request->validate([
            'change_type' => ['required', Rule::in(['add', 'remove', 'adjust'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->catalogue->adjustInventory(
            $seller,
            $user,
            $id,
            $data['change_type'],
            (int) $data['quantity'],
            $data['reason'] ?? null
        );

        return 'Inventory updated successfully.';
    }

    private function ordersIndex(Request $request, Seller $seller): array
    {
        $items = SellerOrder::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('order', fn (Builder $order) => $order
                    ->where('slug', 'like', '%'.$search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('billing_name', 'like', '%'.$search.'%')
                    ->orWhere('billing_phone', 'like', '%'.$search.'%'));
            })
            ->with(['order.user', 'store'])
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (SellerOrder $sellerOrder) => $this->row($sellerOrder->id, [
            '#'.$sellerOrder->id,
            $sellerOrder->order?->slug ?? '—',
            $sellerOrder->store?->name ?? '—',
            $sellerOrder->order?->billing_name ?? $sellerOrder->order?->user?->name ?? 'Guest',
            $sellerOrder->status,
            $sellerOrder->delivery_type,
            $this->money($sellerOrder->subtotal),
            $this->money($sellerOrder->seller_earnings),
            $this->date($sellerOrder->created_at),
        ]))->all();

        return $this->baseIndex(
            'orders',
            'Orders',
            'Accept orders and move individual items through preparation and pickup.',
            ['ID', 'Order', 'Store', 'Customer', 'Status', 'Delivery', 'Subtotal', 'Earnings', 'Created'],
            $rows,
            $items,
            [
                'All orders' => SellerOrder::query()->where('seller_id', $seller->id)->count(),
                'Awaiting response' => SellerOrder::query()->where('seller_id', $seller->id)->where('status', 'awaiting_store_response')->count(),
                'Preparing' => SellerOrder::query()->where('seller_id', $seller->id)->where('status', 'preparing')->count(),
                'Gross sales' => $this->money(SellerOrder::query()->where('seller_id', $seller->id)->sum('subtotal')),
            ],
            [
                $this->searchFilter('Order number, customer or phone'),
                $this->selectFilter('status', 'Status', [
                    'awaiting_store_response' => 'Awaiting response',
                    'accepted_by_seller' => 'Accepted',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for pickup',
                    'rejected_by_seller' => 'Rejected',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ]),
            ]
        );
    }

    private function showOrder(int $id, Seller $seller): array
    {
        $sellerOrder = $this->orders->sellerOrder($seller, $id);
        $order = $sellerOrder->order;

        $actions = [];
        foreach ($sellerOrder->items as $item) {
            $options = match ($item->status) {
                'awaiting_store_response' => [
                    'accepted_by_seller' => 'Accept item',
                    'rejected_by_seller' => 'Reject item',
                ],
                'accepted_by_seller' => [
                    'preparing' => 'Start preparing',
                    'rejected_by_seller' => 'Reject item',
                ],
                'preparing' => ['ready_for_pickup' => 'Mark ready for pickup'],
                default => [],
            };

            if ($options !== []) {
                $actions[] = [
                    'label' => 'Update: '.$item->product_title,
                    'action' => 'item-status',
                    'tone' => 'primary',
                    'fields' => [
                        ['name' => 'item_id', 'label' => 'Item ID', 'type' => 'hidden', 'value' => $item->id],
                        ['name' => 'status', 'label' => 'New status', 'type' => 'select', 'options' => $options, 'required' => true],
                        ['name' => 'reason', 'label' => 'Reason / note', 'type' => 'textarea'],
                    ],
                ];
            }
        }

        return $this->baseShow('orders', 'Order '.($order?->slug ?? '#'.$sellerOrder->id), $sellerOrder->id, [
            [
                'title' => 'Order summary',
                'items' => [
                    $this->detail('Order number', $order?->slug),
                    $this->detail('Invoice', $order?->invoice_number),
                    $this->detail('Store', $sellerOrder->store?->name),
                    $this->detail('Customer', $order?->billing_name ?? $order?->user?->name),
                    $this->detail('Phone', $order?->billing_phone ?? $order?->shipping_phone),
                    $this->detail('Source', $order?->source),
                    $this->detail('Delivery type', $sellerOrder->delivery_type),
                    $this->detail('Status', $sellerOrder->status, 'status'),
                    $this->detail('Payment', ($order?->payment_method ?? '—').' / '.($order?->payment_status ?? '—'), 'status'),
                    $this->detail('Subtotal', $this->money($sellerOrder->subtotal)),
                    $this->detail('Commission', $this->money($sellerOrder->commission_amount)),
                    $this->detail('Seller earnings', $this->money($sellerOrder->seller_earnings)),
                    $this->detail('Order note', $order?->order_note),
                    $this->detail('Created', $this->date($sellerOrder->created_at)),
                ],
            ],
        ], $actions, [[
            'title' => 'Order items',
            'columns' => ['ID', 'Product', 'Variant', 'SKU', 'Qty', 'Unit price', 'Subtotal', 'Status'],
            'rows' => $sellerOrder->items->map(fn (OrderItem $item) => [
                '#'.$item->id,
                $item->product_title,
                $item->variant_title ?: '—',
                $item->sku,
                $item->quantity,
                $this->money($item->special_price ?? $item->price),
                $this->money($item->subtotal),
                $item->status,
            ])->all(),
        ]]);
    }

    private function orderAction(int $id, Request $request, User $user, Seller $seller): string
    {
        SellerOrder::query()->where('seller_id', $seller->id)->findOrFail($id);
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['accepted_by_seller', 'preparing', 'ready_for_pickup', 'rejected_by_seller'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->orders->updateSellerItem($user, $seller, (int) $data['item_id'], $data['status'], $data['reason'] ?? null);

        return 'Order item status updated successfully.';
    }

    private function returnsIndex(Request $request, Seller $seller): array
    {
        $items = $this->returns->sellerReturns(
            $seller,
            $this->perPage($request),
            $request->filled('status') ? $request->string('status')->toString() : null
        );

        $rows = collect($items->items())->map(fn (OrderItemReturn $return) => $this->row($return->id, [
            '#'.$return->id,
            $return->order?->slug ?? '—',
            $return->orderItem?->product_title ?? '—',
            $return->user?->name ?? '—',
            $return->quantity,
            $this->money($return->refund_amount),
            $return->return_status,
            $return->pickup_status,
            $this->date($return->requested_at ?? $return->created_at),
        ]))->all();

        return $this->baseIndex(
            'returns',
            'Return Requests',
            'Review customer return requests before pickup and refund processing.',
            ['ID', 'Order', 'Product', 'Customer', 'Qty', 'Refund', 'Return status', 'Pickup', 'Requested'],
            $rows,
            $items,
            [
                'All requests' => OrderItemReturn::query()->where('seller_id', $seller->id)->count(),
                'Waiting decision' => OrderItemReturn::query()->where('seller_id', $seller->id)->where('return_status', 'requested')->count(),
                'Approved' => OrderItemReturn::query()->where('seller_id', $seller->id)->where('return_status', 'seller_approved')->count(),
            ],
            [$this->selectFilter('status', 'Status', [
                'requested' => 'Requested',
                'seller_approved' => 'Seller approved',
                'seller_rejected' => 'Seller rejected',
                'picked_up' => 'Picked up',
                'received' => 'Received',
                'refunded' => 'Refunded',
                'completed' => 'Completed',
            ])]
        );
    }

    private function showReturn(int $id, Seller $seller): array
    {
        $return = OrderItemReturn::query()
            ->where('seller_id', $seller->id)
            ->with(['orderItem', 'order', 'user', 'store', 'deliveryBoy'])
            ->findOrFail($id);

        $actions = [];
        if ($return->return_status === 'requested') {
            $actions[] = [
                'label' => 'Decide return request',
                'action' => 'decision',
                'tone' => 'primary',
                'fields' => [
                    ['name' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approve' => 'Approve', 'reject' => 'Reject'], 'required' => true],
                    ['name' => 'comment', 'label' => 'Seller comment', 'type' => 'textarea'],
                ],
            ];
        }

        return $this->baseShow('returns', 'Return #'.$return->id, $return->id, [[
            'title' => 'Return details',
            'items' => [
                $this->detail('Order', $return->order?->slug),
                $this->detail('Product', $return->orderItem?->product_title),
                $this->detail('Customer', $return->user?->name),
                $this->detail('Store', $return->store?->name),
                $this->detail('Quantity', $return->quantity),
                $this->detail('Reason', $return->reason),
                $this->detail('Details', $return->details),
                $this->detail('Refund amount', $this->money($return->refund_amount)),
                $this->detail('Refund method', $return->refund_method),
                $this->detail('Return status', $return->return_status, 'status'),
                $this->detail('Pickup status', $return->pickup_status, 'status'),
                $this->detail('Seller comment', $return->seller_comment),
                $this->detail('Requested', $this->date($return->requested_at)),
            ],
        ]], $actions);
    }

    private function returnAction(int $id, Request $request, Seller $seller): string
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->returns->sellerDecision($seller, $id, $data['decision'], $data['comment'] ?? null);

        return 'Return request updated successfully.';
    }

    private function posIndex(Request $request, Seller $seller): array
    {
        $items = Order::query()
            ->where('source', 'pos')
            ->whereHas('sellerOrders', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $sub) => $sub
                    ->where('slug', 'like', '%'.$search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('billing_name', 'like', '%'.$search.'%')
                    ->orWhere('pos_reference', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('payment_status'), fn (Builder $query) => $query->where('payment_status', $request->string('payment_status')->toString()))
            ->with(['user', 'posOperator', 'sellerOrders.store'])
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (Order $order) => $this->row($order->id, [
            '#'.$order->id,
            $order->invoice_number ?? $order->slug,
            $order->sellerOrders->first()?->store?->name ?? '—',
            $order->billing_name ?? $order->user?->name ?? '—',
            $order->posOperator?->name ?? '—',
            $order->payment_method,
            $order->payment_status,
            $this->money($order->final_total),
            $this->date($order->created_at),
        ]))->all();

        return $this->baseIndex(
            'pos',
            'Point of Sale',
            'Create counter sales and review POS receipts connected to seller inventory.',
            ['ID', 'Receipt', 'Store', 'Customer', 'Operator', 'Payment', 'Status', 'Total', 'Created'],
            $rows,
            $items,
            [
                'POS orders' => Order::query()->where('source', 'pos')->whereHas('sellerOrders', fn (Builder $query) => $query->where('seller_id', $seller->id))->count(),
                'POS sales' => $this->money(Order::query()->where('source', 'pos')->whereHas('sellerOrders', fn (Builder $query) => $query->where('seller_id', $seller->id))->sum('final_total')),
                'POS stores' => Store::query()->where('seller_id', $seller->id)->where('pos_enabled', true)->count(),
            ],
            [
                $this->searchFilter('Receipt, customer or reference'),
                $this->selectFilter('payment_status', 'Payment status', ['completed' => 'Completed', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded']),
            ],
            true
        );
    }

    private function posForm(Seller $seller): array
    {
        $stores = Store::query()->where('seller_id', $seller->id)->where('pos_enabled', true)->orderBy('name')->get();
        $inventories = StoreProductVariant::query()
            ->whereHas('store', fn (Builder $query) => $query->where('seller_id', $seller->id)->where('pos_enabled', true))
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->with(['store', 'productVariant.product'])
            ->get();
        $customers = User::query()->where('access_panel', GuardNameEnum::WEB->value)->where('status', 'active')->orderBy('name')->limit(500)->get();

        $inventoryOptions = [];
        foreach ($inventories as $inventory) {
            $inventoryOptions[(string) $inventory->id] = ($inventory->store?->name ?? 'Store').' — '.($inventory->productVariant?->product?->title ?? 'Product').' / '.($inventory->productVariant?->title ?? 'Variant').' ('.$inventory->stock.' in stock, '.$this->money($inventory->effectivePrice()).')';
        }

        return $this->baseForm('pos', 'Create POS Order', [
            ['name' => 'store_id', 'label' => 'POS store', 'type' => 'select', 'options' => $this->optionList($stores), 'required' => true],
            ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $this->optionList($customers), 'required' => true],
            ['name' => 'inventory_id', 'label' => 'Product / inventory', 'type' => 'select', 'options' => $inventoryOptions, 'required' => true],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'value' => 1, 'required' => true],
            ['name' => 'discount_amount', 'label' => 'Discount amount', 'type' => 'number', 'step' => '0.01', 'value' => 0],
            ['name' => 'discount_reason', 'label' => 'Discount reason', 'type' => 'text'],
            ['name' => 'payment_method', 'label' => 'Payment method', 'type' => 'select', 'options' => ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank', 'external' => 'External / mobile payment'], 'required' => true],
            ['name' => 'amount', 'label' => 'Tender amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'received_amount', 'label' => 'Cash received', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'transaction_id', 'label' => 'External transaction ID', 'type' => 'text'],
            ['name' => 'reference', 'label' => 'POS reference', 'type' => 'text'],
            ['name' => 'note', 'label' => 'Order note', 'type' => 'textarea'],
        ]);
    }

    private function savePosOrder(Request $request, User $user, Seller $seller): Order
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'inventory_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'bank', 'external'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Store::query()->where('seller_id', $seller->id)->where('pos_enabled', true)->findOrFail($data['store_id']);
        StoreProductVariant::query()->where('store_id', $data['store_id'])->findOrFail($data['inventory_id']);

        return $this->pos->createOrder($seller, $user, [
            'store_id' => (int) $data['store_id'],
            'customer_id' => (int) $data['customer_id'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'discount_reason' => $data['discount_reason'] ?? null,
            'items' => [[
                'inventory_id' => (int) $data['inventory_id'],
                'quantity' => (int) $data['quantity'],
                'addons' => [],
            ]],
            'tenders' => [[
                'method' => $data['payment_method'],
                'amount' => (float) $data['amount'],
                'received_amount' => isset($data['received_amount']) ? (float) $data['received_amount'] : null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'metadata' => [],
            ]],
        ]);
    }

    private function showPosOrder(int $id, Seller $seller): array
    {
        $order = Order::query()
            ->where('source', 'pos')
            ->whereHas('sellerOrders', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->with(['user', 'posOperator', 'sellerOrders.store', 'items'])
            ->findOrFail($id);

        return $this->baseShow('pos', 'POS Receipt '.($order->invoice_number ?? $order->slug), $order->id, [[
            'title' => 'Receipt details',
            'items' => [
                $this->detail('Order', $order->slug),
                $this->detail('Invoice', $order->invoice_number),
                $this->detail('Reference', $order->pos_reference),
                $this->detail('Store', $order->sellerOrders->first()?->store?->name),
                $this->detail('Customer', $order->billing_name ?? $order->user?->name),
                $this->detail('Operator', $order->posOperator?->name),
                $this->detail('Payment method', $order->payment_method),
                $this->detail('Payment status', $order->payment_status, 'status'),
                $this->detail('Subtotal', $this->money($order->subtotal)),
                $this->detail('Discount', $this->money($order->promo_discount)),
                $this->detail('Final total', $this->money($order->final_total)),
                $this->detail('Cash received', $this->money($order->cash_received)),
                $this->detail('Change returned', $this->money($order->change_returned)),
                $this->detail('Created', $this->date($order->created_at)),
            ],
        ]], [], [[
            'title' => 'Items',
            'columns' => ['Product', 'Variant', 'SKU', 'Qty', 'Unit price', 'Subtotal'],
            'rows' => $order->items->map(fn (OrderItem $item) => [
                $item->product_title,
                $item->variant_title ?: '—',
                $item->sku,
                $item->quantity,
                $this->money($item->special_price ?? $item->price),
                $this->money($item->subtotal),
            ])->all(),
        ]]);
    }

    private function sellerWallet(User $owner): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $owner->id, 'type' => 'seller'],
            ['balance' => 0, 'blocked_balance' => 0, 'currency_code' => 'BDT']
        );
    }

    private function walletIndex(Request $request, Seller $seller): array
    {
        $owner = $seller->owner()->firstOrFail();
        $wallet = $this->sellerWallet($owner);
        $items = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->latest()
            ->paginate($this->perPage($request));

        $rows = collect($items->items())->map(fn (WalletTransaction $transaction) => $this->row($transaction->id, [
            '#'.$transaction->id,
            $transaction->type,
            $this->money($transaction->amount),
            $this->money($transaction->opening_balance),
            $this->money($transaction->closing_balance),
            $transaction->status,
            $transaction->description,
            $this->date($transaction->created_at),
        ]))->all();

        return $this->baseIndex(
            'wallet',
            'Seller Wallet',
            'Track available earnings, blocked funds and every wallet transaction.',
            ['ID', 'Type', 'Amount', 'Opening', 'Closing', 'Status', 'Description', 'Date'],
            $rows,
            $items,
            [
                'Balance' => $this->money($wallet->balance),
                'Blocked' => $this->money($wallet->blocked_balance),
                'Available' => $this->money($wallet->availableBalance()),
            ],
            [$this->selectFilter('type', 'Transaction type', ['credit' => 'Credit', 'debit' => 'Debit', 'block' => 'Block', 'release' => 'Release'])]
        );
    }

    private function showWalletTransaction(int $id, Seller $seller): array
    {
        $wallet = Wallet::query()->where('user_id', $seller->user_id)->where('type', 'seller')->firstOrFail();
        $transaction = WalletTransaction::query()->where('wallet_id', $wallet->id)->findOrFail($id);

        return $this->baseShow('wallet', 'Wallet Transaction #'.$transaction->id, $transaction->id, [[
            'title' => 'Transaction details',
            'items' => [
                $this->detail('UUID', $transaction->uuid),
                $this->detail('Type', $transaction->type, 'status'),
                $this->detail('Amount', $this->money($transaction->amount)),
                $this->detail('Opening balance', $this->money($transaction->opening_balance)),
                $this->detail('Closing balance', $this->money($transaction->closing_balance)),
                $this->detail('Status', $transaction->status, 'status'),
                $this->detail('Reference', trim(($transaction->reference_type ?? '').' #'.($transaction->reference_id ?? ''), ' #')),
                $this->detail('Description', $transaction->description),
                $this->detail('Metadata', $transaction->metadata),
                $this->detail('Created', $this->date($transaction->created_at)),
            ],
        ]]);
    }

    private function statementsIndex(Request $request, Seller $seller): array
    {
        $items = $this->finance->sellerStatements(
            $seller,
            $this->perPage($request),
            $request->filled('status') ? $request->string('status')->toString() : null,
            $request->filled('direction') ? $request->string('direction')->toString() : null
        );
        $rows = collect($items->items())->map(fn (SellerStatement $statement) => $this->row($statement->id, [
            '#'.$statement->id,
            $statement->order?->slug ?? '—',
            $statement->entry_type,
            $statement->direction,
            $this->money($statement->amount),
            $statement->settlement_status,
            $statement->description,
            $this->date($statement->posted_at),
        ]))->all();

        return $this->baseIndex('statements', 'Statements', 'Review order earnings, commission, refunds and settlement status.', ['ID', 'Order', 'Entry', 'Direction', 'Amount', 'Settlement', 'Description', 'Posted'], $rows, $items, [
            'Unsettled credit' => $this->money(SellerStatement::query()->where('seller_id', $seller->id)->where('settlement_status', 'unsettled')->where('direction', 'credit')->sum('amount')),
            'Settled credit' => $this->money(SellerStatement::query()->where('seller_id', $seller->id)->where('settlement_status', 'settled')->where('direction', 'credit')->sum('amount')),
            'Debit entries' => $this->money(SellerStatement::query()->where('seller_id', $seller->id)->where('direction', 'debit')->sum('amount')),
        ], [
            $this->selectFilter('status', 'Settlement', ['unsettled' => 'Unsettled', 'settled' => 'Settled']),
            $this->selectFilter('direction', 'Direction', ['credit' => 'Credit', 'debit' => 'Debit']),
        ]);
    }

    private function showStatement(int $id, Seller $seller): array
    {
        $statement = SellerStatement::query()->where('seller_id', $seller->id)->with(['order', 'orderItem', 'orderReturn'])->findOrFail($id);

        return $this->baseShow('statements', 'Statement #'.$statement->id, $statement->id, [[
            'title' => 'Statement details',
            'items' => [
                $this->detail('Order', $statement->order?->slug),
                $this->detail('Entry type', $statement->entry_type),
                $this->detail('Direction', $statement->direction, 'status'),
                $this->detail('Amount', $this->money($statement->amount)),
                $this->detail('Currency', $statement->currency_code),
                $this->detail('Settlement', $statement->settlement_status, 'status'),
                $this->detail('Reference', trim(($statement->reference_type ?? '').' #'.($statement->reference_id ?? ''), ' #')),
                $this->detail('Description', $statement->description),
                $this->detail('Posted', $this->date($statement->posted_at)),
                $this->detail('Settled', $this->date($statement->settled_at)),
                $this->detail('Settlement reference', $statement->settlement_reference),
                $this->detail('Metadata', $statement->meta),
            ],
        ]]);
    }

    private function withdrawalsIndex(Request $request, Seller $seller): array
    {
        $items = SellerWithdrawalRequest::query()
            ->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (SellerWithdrawalRequest $withdrawal) => $this->row($withdrawal->id, [
            '#'.$withdrawal->id,
            $this->money($withdrawal->amount),
            $withdrawal->status,
            $withdrawal->request_note ?: '—',
            $withdrawal->admin_remark ?: '—',
            $withdrawal->external_transaction_id ?: '—',
            $this->date($withdrawal->created_at),
        ]))->all();

        return $this->baseIndex('withdrawals', 'Withdrawals', 'Request payouts from available seller earnings and track approval status.', ['ID', 'Amount', 'Status', 'Request note', 'Admin remark', 'Transaction', 'Created'], $rows, $items, [
            'Pending' => $this->money(SellerWithdrawalRequest::query()->where('seller_id', $seller->id)->whereIn('status', ['pending', 'requested', 'processing'])->sum('amount')),
            'Approved' => $this->money(SellerWithdrawalRequest::query()->where('seller_id', $seller->id)->where('status', 'approved')->sum('amount')),
            'Rejected' => $this->money(SellerWithdrawalRequest::query()->where('seller_id', $seller->id)->where('status', 'rejected')->sum('amount')),
        ], [$this->selectFilter('status', 'Status', ['pending' => 'Pending', 'processing' => 'Processing', 'approved' => 'Approved', 'rejected' => 'Rejected'])], true);
    }

    private function withdrawalForm(): array
    {
        return $this->baseForm('withdrawals', 'Request Withdrawal', [
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'note', 'label' => 'Request note', 'type' => 'textarea'],
        ]);
    }

    private function saveWithdrawal(Request $request, Seller $seller): SellerWithdrawalRequest
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->finance->requestSellerWithdrawal($seller, (float) $data['amount'], $data['note'] ?? null);
    }

    private function showWithdrawal(int $id, Seller $seller): array
    {
        $withdrawal = SellerWithdrawalRequest::query()->where('seller_id', $seller->id)->with(['processedBy', 'walletTransaction'])->findOrFail($id);

        return $this->baseShow('withdrawals', 'Withdrawal #'.$withdrawal->id, $withdrawal->id, [[
            'title' => 'Withdrawal details',
            'items' => [
                $this->detail('Amount', $this->money($withdrawal->amount)),
                $this->detail('Status', $withdrawal->status, 'status'),
                $this->detail('Request note', $withdrawal->request_note),
                $this->detail('Admin remark', $withdrawal->admin_remark),
                $this->detail('External transaction ID', $withdrawal->external_transaction_id),
                $this->detail('Processed by', $withdrawal->processedBy?->name),
                $this->detail('Processed at', $this->date($withdrawal->processed_at)),
                $this->detail('Created', $this->date($withdrawal->created_at)),
            ],
        ]]);
    }

    private function advertisementsIndex(Request $request, Seller $seller): array
    {
        $query = AdCampaign::query()->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn (Builder $builder) => $builder->where('title', 'like', '%'.$request->string('search')->toString().'%'))
            ->with(['store', 'product']);
        $items = $query->latest()->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (AdCampaign $campaign) => $this->row($campaign->id, [
            '#'.$campaign->id,
            $campaign->title,
            $campaign->ad_type,
            $campaign->placement,
            $campaign->status,
            $this->money($campaign->budget),
            $this->money($campaign->spent_amount),
            $this->date($campaign->starts_at),
            $this->date($campaign->ends_at),
        ], in_array($campaign->status, ['draft', 'rejected', 'paused'], true)))->all();

        return $this->baseIndex('advertisements', 'Advertisements', 'Create CPC/CPM campaigns for products and stores, then monitor approval and spend.', ['ID', 'Campaign', 'Type', 'Placement', 'Status', 'Budget', 'Spent', 'Starts', 'Ends'], $rows, $items, [
            'Campaigns' => AdCampaign::query()->where('seller_id', $seller->id)->count(),
            'Active' => AdCampaign::query()->where('seller_id', $seller->id)->where('status', 'active')->count(),
            'Budget' => $this->money(AdCampaign::query()->where('seller_id', $seller->id)->sum('budget')),
            'Spent' => $this->money(AdCampaign::query()->where('seller_id', $seller->id)->sum('spent_amount')),
        ], [$this->searchFilter('Campaign title'), $this->selectFilter('status', 'Status', ['pending_approval' => 'Pending approval', 'approved' => 'Approved', 'active' => 'Active', 'paused' => 'Paused', 'rejected' => 'Rejected', 'completed' => 'Completed'])], true);
    }

    private function advertisementForm(Seller $seller, ?int $id): array
    {
        $campaign = $id ? AdCampaign::query()->where('seller_id', $seller->id)->findOrFail($id) : new AdCampaign(['ad_type' => 'cpc', 'placement' => 'home_feed']);

        return $this->baseForm('advertisements', $id ? 'Edit Advertisement' : 'Create Advertisement', [
            ['name' => 'title', 'label' => 'Campaign title', 'type' => 'text', 'value' => $campaign->title, 'required' => true],
            ['name' => 'store_id', 'label' => 'Store', 'type' => 'select', 'options' => ['' => 'All stores'] + $this->optionList(Store::query()->where('seller_id', $seller->id)->orderBy('name')->get()), 'value' => $campaign->store_id],
            ['name' => 'product_id', 'label' => 'Product', 'type' => 'select', 'options' => ['' => 'No specific product'] + $this->optionList(Product::query()->where('seller_id', $seller->id)->orderBy('title')->get(), 'title'), 'value' => $campaign->product_id],
            ['name' => 'ad_type', 'label' => 'Pricing type', 'type' => 'select', 'options' => ['cpc' => 'Cost per click', 'cpm' => 'Cost per 1,000 impressions'], 'value' => $campaign->ad_type, 'required' => true],
            ['name' => 'placement', 'label' => 'Placement', 'type' => 'select', 'options' => ['home_feed' => 'Home feed', 'search' => 'Search', 'category' => 'Category', 'store' => 'Store'], 'value' => $campaign->placement, 'required' => true],
            ['name' => 'budget', 'label' => 'Budget', 'type' => 'number', 'step' => '0.01', 'value' => $campaign->budget, 'required' => true],
            ['name' => 'bid_amount', 'label' => 'Bid amount', 'type' => 'number', 'step' => '0.0001', 'value' => $campaign->bid_amount, 'required' => true],
            ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'datetime-local', 'value' => $campaign->starts_at?->format('Y-m-d\TH:i')],
            ['name' => 'ends_at', 'label' => 'Ends at', 'type' => 'datetime-local', 'value' => $campaign->ends_at?->format('Y-m-d\TH:i')],
        ], $id !== null, $id);
    }

    private function saveAdvertisement(Request $request, User $user, Seller $seller, ?int $id): AdCampaign
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'store_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'ad_type' => ['required', Rule::in(['cpc', 'cpm'])],
            'placement' => ['required', Rule::in(['home_feed', 'search', 'category', 'store'])],
            'budget' => ['required', 'numeric', 'min:100'],
            'bid_amount' => ['required', 'numeric', 'min:0.1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $campaign = $id ? AdCampaign::query()->where('seller_id', $seller->id)->findOrFail($id) : null;

        return $this->advertising->saveCampaign($seller, $user, $campaign, $data);
    }

    private function showAdvertisement(int $id, Seller $seller): array
    {
        $campaign = AdCampaign::query()->where('seller_id', $seller->id)->with(['store', 'product', 'stats'])->findOrFail($id);
        $actions = [];
        if (in_array($campaign->status, ['active', 'approved'], true)) {
            $actions[] = ['label' => 'Pause campaign', 'action' => 'pause', 'tone' => 'warning', 'fields' => [['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'pause']]];
        }
        if ($campaign->status === 'paused') {
            $actions[] = ['label' => 'Resume campaign', 'action' => 'resume', 'tone' => 'success', 'fields' => [['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'resume']]];
        }

        return $this->baseShow('advertisements', $campaign->title, $campaign->id, [[
            'title' => 'Campaign details',
            'items' => [
                $this->detail('UUID', $campaign->uuid),
                $this->detail('Store', $campaign->store?->name ?? 'All stores'),
                $this->detail('Product', $campaign->product?->title ?? '—'),
                $this->detail('Type', $campaign->ad_type),
                $this->detail('Placement', $campaign->placement),
                $this->detail('Status', $campaign->status, 'status'),
                $this->detail('Budget', $this->money($campaign->budget)),
                $this->detail('Spent', $this->money($campaign->spent_amount)),
                $this->detail('Bid amount', $this->money($campaign->bid_amount)),
                $this->detail('Starts', $this->date($campaign->starts_at)),
                $this->detail('Ends', $this->date($campaign->ends_at)),
                $this->detail('Rejection reason', $campaign->rejection_reason),
            ],
        ]], $actions, [[
            'title' => 'Performance',
            'columns' => ['Date', 'Impressions', 'Clicks', 'Conversions', 'Spend'],
            'rows' => $campaign->stats->map(fn ($stat) => [$stat->stat_date?->format('d M Y') ?? '—', $stat->impressions, $stat->clicks, $stat->conversions, $this->money($stat->spent_amount ?? 0)])->all(),
        ]], in_array($campaign->status, ['draft', 'rejected', 'paused'], true), in_array($campaign->status, ['draft', 'rejected'], true));
    }

    private function advertisementAction(int $id, Request $request, Seller $seller): string
    {
        $campaign = AdCampaign::query()->where('seller_id', $seller->id)->findOrFail($id);
        $command = $request->validate(['command' => ['required', Rule::in(['pause', 'resume'])]])['command'];
        $command === 'pause' ? $this->advertising->pause($seller, $campaign) : $this->advertising->resume($seller, $campaign);

        return 'Campaign status updated.';
    }

    private function deleteAdvertisement(int $id, Seller $seller): void
    {
        $campaign = AdCampaign::query()->where('seller_id', $seller->id)->findOrFail($id);
        if (! in_array($campaign->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['campaign' => 'Only draft or rejected campaigns can be deleted.']);
        }
        $campaign->delete();
    }

    private function subscriptionsIndex(Request $request, Seller $seller): array
    {
        $view = $request->string('view', 'plans')->toString();
        $tabs = ['plans' => 'Available plans', 'history' => 'Subscription history'];
        $current = $this->subscriptions->current($seller);

        if ($view === 'history') {
            $items = SellerSubscription::query()->where('seller_id', $seller->id)->with('plan')->latest('starts_at')->paginate($this->perPage($request));
            $rows = collect($items->items())->map(fn (SellerSubscription $subscription) => $this->row($subscription->id, [
                '#'.$subscription->id,
                $subscription->plan?->title ?? data_get($subscription->snapshot, 'title', '—'),
                $subscription->status,
                $subscription->payment_method,
                $this->date($subscription->starts_at),
                $this->date($subscription->ends_at),
            ]))->all();
            return $this->baseIndex('subscriptions', 'Subscriptions', 'Select plans and monitor seller feature limits.', ['ID', 'Plan', 'Status', 'Payment', 'Starts', 'Ends'], $rows, $items, [
                'Current plan' => $current?->plan?->title ?? 'No active plan',
                'Current status' => $current?->status ?? 'inactive',
                'Ends at' => $this->date($current?->ends_at),
            ], [], false, [], $tabs, 'history');
        }

        $items = SubscriptionPlan::query()->where('status', 'active')->with('limits')->orderBy('sort_order')->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (SubscriptionPlan $plan) => $this->row($plan->id, [
            '#'.$plan->id,
            $plan->title,
            $this->money($plan->price),
            $plan->duration_days.' days',
            $plan->trial_days.' days',
            $plan->is_featured ? 'Featured' : 'Standard',
        ]))->all();
        return $this->baseIndex('subscriptions', 'Subscriptions', 'Choose the plan that enables seller operations such as POS, ads and bulk uploads.', ['ID', 'Plan', 'Price', 'Duration', 'Trial', 'Type'], $rows, $items, [
            'Current plan' => $current?->plan?->title ?? 'No active plan',
            'Current status' => $current?->status ?? 'inactive',
            'Ends at' => $this->date($current?->ends_at),
        ], [], false, [], $tabs, 'plans');
    }

    private function showSubscription(int $id, Request $request, Seller $seller): array
    {
        $plan = $request->string('view', 'plans')->toString() === 'plans'
            ? SubscriptionPlan::query()->where('status', 'active')->with('limits')->find($id)
            : null;
        if ($plan) {
            return $this->baseShow('subscriptions', $plan->title, $plan->id, [[
                'title' => 'Plan details',
                'items' => [
                    $this->detail('Description', $plan->description),
                    $this->detail('Price', $this->money($plan->price)),
                    $this->detail('Duration', $plan->duration_days.' days'),
                    $this->detail('Trial', $plan->trial_days.' days'),
                    $this->detail('Featured', $plan->is_featured ? 'Yes' : 'No'),
                ],
            ]], [[
                'label' => 'Buy this plan',
                'action' => 'buy',
                'tone' => 'primary',
                'fields' => [
                    ['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'buy'],
                    ['name' => 'payment_method', 'label' => 'Payment method', 'type' => 'select', 'options' => ['wallet' => 'Seller wallet', 'sslcommerz' => 'SSLCommerz', 'stripe' => 'Stripe', 'razorpay' => 'Razorpay'], 'required' => true],
                ],
            ]], [[
                'title' => 'Feature limits',
                'columns' => ['Feature', 'Limit', 'Unlimited'],
                'rows' => $plan->limits->map(fn ($limit) => [$limit->feature_key, $limit->limit_value, $limit->is_unlimited ? 'Yes' : 'No'])->all(),
            ]]);
        }

        $subscription = SellerSubscription::query()->where('seller_id', $seller->id)->with(['plan', 'usages'])->findOrFail($id);
        return $this->baseShow('subscriptions', $subscription->plan?->title ?? 'Subscription', $subscription->id, [[
            'title' => 'Subscription details',
            'items' => [
                $this->detail('Status', $subscription->status, 'status'),
                $this->detail('Payment method', $subscription->payment_method),
                $this->detail('Starts', $this->date($subscription->starts_at)),
                $this->detail('Ends', $this->date($subscription->ends_at)),
                $this->detail('Trial ends', $this->date($subscription->trial_ends_at)),
                $this->detail('Auto renew', $subscription->auto_renew ? 'Yes' : 'No'),
                $this->detail('Snapshot', $subscription->snapshot),
            ],
        ]]);
    }

    private function subscriptionAction(int $id, Request $request, User $user, Seller $seller): string
    {
        $data = $request->validate([
            'command' => ['required', Rule::in(['buy'])],
            'payment_method' => ['required', Rule::in(['wallet', 'sslcommerz', 'stripe', 'razorpay'])],
        ]);
        $plan = SubscriptionPlan::query()->with('limits')->findOrFail($id);
        $result = $this->subscriptions->buy($seller, $user, $plan, $data['payment_method']);

        return $result['requires_external_payment']
            ? 'Subscription request created. Complete the external payment through the configured gateway.'
            : 'Subscription activated successfully.';
    }

    private function teamIndex(Request $request, Seller $seller): array
    {
        $view = $request->string('view', 'members')->toString();
        $tabs = ['members' => 'Team members', 'roles' => 'Roles & permissions'];
        $prefix = 'seller_'.$seller->id.'_';

        if ($view === 'roles') {
            $items = Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->withCount('users')->with('permissions')->paginate($this->perPage($request));
            $rows = collect($items->items())->map(fn (Role $role) => $this->row($role->id, [
                '#'.$role->id,
                Str::headline(Str::after($role->name, $prefix)),
                $role->permissions->count(),
                $role->users_count,
            ], true))->all();
            return $this->baseIndex('team', 'Team & Roles', 'Create seller-scoped roles and control staff access to panel modules.', ['ID', 'Role', 'Permissions', 'Members'], $rows, $items, [
                'Roles' => Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->count(),
                'Members' => DB::table('seller_user')->where('seller_id', $seller->id)->count(),
            ], [], true, [], $tabs, 'roles');
        }

        $items = User::query()->whereHas('sellers', fn (Builder $query) => $query->where('sellers.id', $seller->id))->with(['roles', 'sellers' => fn ($query) => $query->where('sellers.id', $seller->id)])->paginate($this->perPage($request));
        $rows = collect($items->items())->map(function (User $member) use ($seller): array {
            $membership = $member->sellers->firstWhere('id', $seller->id)?->pivot;
            return $this->row($member->id, [
                '#'.$member->id,
                $member->name,
                $member->email,
                $member->mobile ?: '—',
                $membership?->position ?: '—',
                $member->roles->map(fn (Role $role) => Str::headline(Str::after($role->name, 'seller_'.$seller->id.'_')))->implode(', ') ?: '—',
                $membership?->status ?? $member->status,
            ], true);
        })->all();
        return $this->baseIndex('team', 'Team & Roles', 'Invite seller staff, assign roles and disable access without sharing the owner account.', ['ID', 'Member', 'Email', 'Mobile', 'Position', 'Role', 'Status'], $rows, $items, [
            'Owner' => $seller->owner?->name ?? '—',
            'Team members' => DB::table('seller_user')->where('seller_id', $seller->id)->count(),
            'Active members' => DB::table('seller_user')->where('seller_id', $seller->id)->where('status', 'active')->count(),
        ], [], true, [], $tabs, 'members');
    }

    private function teamForm(Request $request, Seller $seller, ?string $id): array
    {
        $view = $request->string('view', 'members')->toString();
        $prefix = 'seller_'.$seller->id.'_';
        if ($view === 'roles') {
            $role = $id ? Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->with('permissions')->findOrFail((int) $id) : new Role();
            $permissions = Permission::query()->where('guard_name', GuardNameEnum::SELLER->value)->orderBy('name')->get();
            return $this->baseForm('team', $id ? 'Edit Seller Role' : 'Create Seller Role', [
                ['name' => 'record_type', 'label' => 'Record type', 'type' => 'hidden', 'value' => 'role'],
                ['name' => 'name', 'label' => 'Role name', 'type' => 'text', 'value' => $id ? Str::headline(Str::after($role->name, $prefix)) : null, 'required' => true],
                ['name' => 'permissions', 'label' => 'Permissions', 'type' => 'multiselect', 'options' => $permissions->pluck('name', 'name')->all(), 'value' => $role->permissions?->pluck('name')->all() ?? [], 'required' => true],
            ], $id !== null, $id, ['view' => 'roles']);
        }

        $member = $id ? User::query()->whereHas('sellers', fn (Builder $query) => $query->where('sellers.id', $seller->id))->with(['roles', 'sellers' => fn ($query) => $query->where('sellers.id', $seller->id)])->findOrFail((int) $id) : new User(['status' => 'active']);
        $membership = $id ? $member->sellers->firstWhere('id', $seller->id)?->pivot : null;
        $roles = Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->get();
        $roleOptions = [];
        foreach ($roles as $role) {
            $roleOptions[(string) $role->id] = Str::headline(Str::after($role->name, $prefix));
        }
        return $this->baseForm('team', $id ? 'Edit Team Member' : 'Add Team Member', [
            ['name' => 'record_type', 'label' => 'Record type', 'type' => 'hidden', 'value' => 'member'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => $member->name, 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $member->email, 'required' => ! $id, 'readonly' => (bool) $id],
            ['name' => 'mobile', 'label' => 'Mobile', 'type' => 'text', 'value' => $member->mobile],
            ['name' => 'password', 'label' => $id ? 'New password (optional)' : 'Password', 'type' => 'password', 'required' => ! $id],
            ['name' => 'position', 'label' => 'Position', 'type' => 'text', 'value' => $membership?->position],
            ['name' => 'role_id', 'label' => 'Role', 'type' => 'select', 'options' => $roleOptions, 'value' => $member->roles?->first()?->id, 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => $membership?->status ?? 'active'],
        ], $id !== null, $id, ['view' => 'members']);
    }

    private function saveTeamRecord(Request $request, Seller $seller, ?string $id): Model
    {
        $recordType = $request->string('record_type', $request->string('view', 'members')->toString() === 'roles' ? 'role' : 'member')->toString();
        $prefix = 'seller_'.$seller->id.'_';

        if ($recordType === 'role') {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'permissions' => ['required', 'array', 'min:1'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);
            $role = $id
                ? Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->findOrFail((int) $id)
                : Role::findOrCreate($prefix.Str::slug($data['name'], '_'), GuardNameEnum::SELLER->value);
            if ($id) {
                $role->name = $prefix.Str::slug($data['name'], '_');
                $role->save();
            }
            $permissions = Permission::query()->where('guard_name', GuardNameEnum::SELLER->value)->whereIn('name', $data['permissions'])->get();
            $role->syncPermissions($permissions);
            return $role;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32', Rule::unique('users', 'mobile')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'position' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
        if (! $id) {
            $rules['email'] = ['required', 'email', 'unique:users,email'];
        }
        $data = $request->validate($rules);
        $role = Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->findOrFail($data['role_id']);

        return DB::transaction(function () use ($data, $id, $seller, $role): User {
            if ($id) {
                $user = User::query()->whereHas('sellers', fn (Builder $query) => $query->where('sellers.id', $seller->id))->findOrFail((int) $id);
                $update = Arr::only($data, ['name', 'mobile', 'status']);
                if (! empty($data['password'])) {
                    $update['password'] = Hash::make($data['password']);
                }
                $user->update($update);
                DB::table('seller_user')->where('seller_id', $seller->id)->where('user_id', $user->id)->update([
                    'position' => $data['position'] ?? null,
                    'status' => $data['status'],
                    'updated_at' => now(),
                ]);
            } else {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'] ?? null,
                    'password' => Hash::make($data['password']),
                    'status' => $data['status'],
                    'access_panel' => GuardNameEnum::SELLER->value,
                    'logged_in_type' => 'platform',
                    'country' => 'Bangladesh',
                    'iso_2' => 'BD',
                    'country_code' => '+880',
                    'email_verified_at' => now(),
                ]);
                DB::table('seller_user')->insert([
                    'seller_id' => $seller->id,
                    'user_id' => $user->id,
                    'position' => $data['position'] ?? null,
                    'status' => $data['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $user->syncRoles([$role]);
            return $user;
        });
    }

    private function showTeamRecord(string $id, Request $request, Seller $seller): array
    {
        $view = $request->string('view', 'members')->toString();
        $prefix = 'seller_'.$seller->id.'_';
        if ($view === 'roles') {
            $role = Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', $prefix.'%')->with(['permissions', 'users'])->findOrFail((int) $id);
            return $this->baseShow('team', Str::headline(Str::after($role->name, $prefix)), $role->id, [[
                'title' => 'Role details',
                'items' => [
                    $this->detail('Role', Str::headline(Str::after($role->name, $prefix))),
                    $this->detail('Permissions', $role->permissions->pluck('name')->implode(', ')),
                    $this->detail('Assigned members', $role->users->count()),
                ],
            ]], [], [], true, ! $role->users()->exists(), ['view' => 'roles']);
        }

        $member = User::query()->whereHas('sellers', fn (Builder $query) => $query->where('sellers.id', $seller->id))->with(['roles', 'sellers' => fn ($query) => $query->where('sellers.id', $seller->id)])->findOrFail((int) $id);
        $membership = $member->sellers->firstWhere('id', $seller->id)?->pivot;
        return $this->baseShow('team', $member->name, $member->id, [[
            'title' => 'Team member details',
            'items' => [
                $this->detail('Name', $member->name),
                $this->detail('Email', $member->email),
                $this->detail('Mobile', $member->mobile),
                $this->detail('Position', $membership?->position),
                $this->detail('Role', $member->roles->map(fn (Role $role) => Str::headline(Str::after($role->name, $prefix)))->implode(', ')),
                $this->detail('Membership status', $membership?->status, 'status'),
                $this->detail('Account status', $member->status, 'status'),
            ],
        ]], [], [], true, (int) $member->id !== (int) $seller->user_id, ['view' => 'members']);
    }

    private function deleteTeamRecord(string $id, Request $request, Seller $seller, User $actor): void
    {
        $view = $request->string('view', 'members')->toString();
        if ($view === 'roles') {
            $role = Role::query()->where('guard_name', GuardNameEnum::SELLER->value)->where('name', 'like', 'seller_'.$seller->id.'_%')->findOrFail((int) $id);
            if ($role->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'Role assigned to team members cannot be deleted.']);
            }
            $role->delete();
            return;
        }

        abort_if((int) $id === (int) $seller->user_id || (int) $id === (int) $actor->id, 422);
        $deleted = DB::table('seller_user')->where('seller_id', $seller->id)->where('user_id', (int) $id)->delete();
        abort_unless($deleted > 0, 404);
        User::query()->whereKey((int) $id)->update(['status' => 'inactive']);
    }

    private function reviewsIndex(Request $request, Seller $seller): array
    {
        $items = Review::query()
            ->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('rating'), fn (Builder $query) => $query->where('rating', $request->integer('rating')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->with(['user', 'product', 'store'])
            ->latest()
            ->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (Review $review) => $this->row($review->id, [
            '#'.$review->id,
            $review->product?->title ?? '—',
            $review->user?->name ?? '—',
            $review->rating.'/5',
            Str::limit($review->comment, 70),
            $review->seller_reply ? 'Replied' : 'Pending reply',
            $review->status,
            $this->date($review->created_at),
        ]))->all();
        return $this->baseIndex('reviews', 'Product Reviews', 'Read verified customer reviews and post seller replies.', ['ID', 'Product', 'Customer', 'Rating', 'Comment', 'Reply', 'Status', 'Created'], $rows, $items, [
            'Reviews' => Review::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->count(),
            'Average rating' => number_format((float) Review::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->avg('rating'), 2),
            'Awaiting reply' => Review::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->whereNull('seller_reply')->count(),
        ], [
            $this->selectFilter('rating', 'Rating', ['5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star']),
            $this->selectFilter('status', 'Status', ['published' => 'Published', 'pending' => 'Pending', 'hidden' => 'Hidden']),
        ]);
    }

    private function showReview(int $id, Seller $seller): array
    {
        $review = Review::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->with(['user', 'product', 'store'])->findOrFail($id);
        return $this->baseShow('reviews', 'Review #'.$review->id, $review->id, [[
            'title' => 'Review details',
            'items' => [
                $this->detail('Product', $review->product?->title),
                $this->detail('Store', $review->store?->name),
                $this->detail('Customer', $review->user?->name),
                $this->detail('Rating', $review->rating.'/5'),
                $this->detail('Title', $review->title),
                $this->detail('Comment', $review->comment),
                $this->detail('Status', $review->status, 'status'),
                $this->detail('Seller reply', $review->seller_reply),
                $this->detail('Replied at', $this->date($review->seller_replied_at)),
            ],
        ]], [[
            'label' => $review->seller_reply ? 'Update reply' : 'Reply to review',
            'action' => 'reply',
            'tone' => 'primary',
            'fields' => [['name' => 'reply', 'label' => 'Seller reply', 'type' => 'textarea', 'value' => $review->seller_reply, 'required' => true]],
        ]]);
    }

    private function reviewAction(int $id, Request $request, User $user, Seller $seller): string
    {
        $review = Review::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->findOrFail($id);
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $review->update(['seller_reply' => $data['reply'], 'seller_replied_at' => now()]);
        return 'Review reply saved.';
    }

    private function feedbackIndex(Request $request, Seller $seller): array
    {
        $items = SellerFeedback::query()->where('seller_id', $seller->id)
            ->when($request->filled('rating'), fn (Builder $query) => $query->where('rating', $request->integer('rating')))
            ->latest()->paginate($this->perPage($request));
        $users = User::query()->whereIn('id', collect($items->items())->pluck('user_id'))->pluck('name', 'id');
        $rows = collect($items->items())->map(fn (SellerFeedback $feedback) => $this->row($feedback->id, [
            '#'.$feedback->id,
            $users[$feedback->user_id] ?? 'Customer #'.$feedback->user_id,
            $feedback->rating.'/5',
            Str::limit($feedback->comment, 80),
            $feedback->seller_reply ? 'Replied' : 'Pending reply',
            $feedback->status,
            $this->date($feedback->created_at),
        ]))->all();
        return $this->baseIndex('feedback', 'Seller Feedback', 'Review customer feedback about the seller business and respond professionally.', ['ID', 'Customer', 'Rating', 'Comment', 'Reply', 'Status', 'Created'], $rows, $items, [
            'Feedback entries' => SellerFeedback::query()->where('seller_id', $seller->id)->count(),
            'Average rating' => number_format((float) SellerFeedback::query()->where('seller_id', $seller->id)->avg('rating'), 2),
            'Awaiting reply' => SellerFeedback::query()->where('seller_id', $seller->id)->whereNull('seller_reply')->count(),
        ], [$this->selectFilter('rating', 'Rating', ['5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star'])]);
    }

    private function showFeedback(int $id, Seller $seller): array
    {
        $feedback = SellerFeedback::query()->where('seller_id', $seller->id)->findOrFail($id);
        $customer = User::query()->find($feedback->user_id);
        return $this->baseShow('feedback', 'Feedback #'.$feedback->id, $feedback->id, [[
            'title' => 'Feedback details',
            'items' => [
                $this->detail('Customer', $customer?->name),
                $this->detail('Order ID', $feedback->order_id),
                $this->detail('Rating', $feedback->rating.'/5'),
                $this->detail('Comment', $feedback->comment),
                $this->detail('Status', $feedback->status, 'status'),
                $this->detail('Seller reply', $feedback->seller_reply),
                $this->detail('Replied at', $this->date($feedback->seller_replied_at)),
            ],
        ]], [[
            'label' => $feedback->seller_reply ? 'Update reply' : 'Reply to feedback',
            'action' => 'reply',
            'tone' => 'primary',
            'fields' => [['name' => 'reply', 'label' => 'Seller reply', 'type' => 'textarea', 'value' => $feedback->seller_reply, 'required' => true]],
        ]]);
    }

    private function feedbackAction(int $id, Request $request, Seller $seller): string
    {
        $feedback = SellerFeedback::query()->where('seller_id', $seller->id)->findOrFail($id);
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $feedback->update(['seller_reply' => $data['reply'], 'seller_replied_at' => now()]);
        return 'Feedback reply saved.';
    }

    private function prescriptionsIndex(Request $request, Seller $seller): array
    {
        $items = Prescription::query()->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $sub) => $sub->where('patient_name', 'like', '%'.$search.'%')->orWhere('uuid', 'like', '%'.$search.'%'));
            })
            ->with(['user', 'store', 'order'])->latest()->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (Prescription $prescription) => $this->row($prescription->id, [
            '#'.$prescription->id,
            Str::limit($prescription->uuid, 12),
            $prescription->patient_name ?: $prescription->user?->name ?: '—',
            $prescription->store?->name ?? '—',
            $prescription->order?->slug ?? '—',
            $prescription->status,
            $this->date($prescription->created_at),
        ]))->all();
        return $this->baseIndex('prescriptions', 'Prescriptions', 'Review prescriptions assigned to this seller and mark approved prescriptions fulfilled.', ['ID', 'Reference', 'Patient', 'Store', 'Order', 'Status', 'Created'], $rows, $items, [
            'Assigned' => Prescription::query()->where('seller_id', $seller->id)->count(),
            'Approved' => Prescription::query()->where('seller_id', $seller->id)->where('status', 'approved')->count(),
            'Fulfilled' => Prescription::query()->where('seller_id', $seller->id)->where('status', 'fulfilled')->count(),
        ], [$this->searchFilter('Patient or reference'), $this->selectFilter('status', 'Status', ['pending' => 'Pending', 'under_review' => 'Under review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'fulfilled' => 'Fulfilled'])]);
    }

    private function showPrescription(int $id, Seller $seller): array
    {
        $prescription = Prescription::query()->where('seller_id', $seller->id)->with(['user', 'store', 'order', 'items'])->findOrFail($id);
        $actions = [];
        if ($prescription->status === 'approved') {
            $actions[] = ['label' => 'Mark fulfilled', 'action' => 'fulfill', 'tone' => 'success', 'fields' => [['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'fulfill']]];
        }
        return $this->baseShow('prescriptions', 'Prescription '.Str::limit($prescription->uuid, 14), $prescription->id, [[
            'title' => 'Prescription details',
            'items' => [
                $this->detail('Patient', $prescription->patient_name ?: $prescription->user?->name),
                $this->detail('Patient age', $prescription->patient_age),
                $this->detail('Doctor', $prescription->doctor_name),
                $this->detail('Doctor registration', $prescription->doctor_registration_no),
                $this->detail('Prescribed at', $prescription->prescribed_at?->format('d M Y')),
                $this->detail('Store', $prescription->store?->name),
                $this->detail('Order', $prescription->order?->slug),
                $this->detail('Status', $prescription->status, 'status'),
                $this->detail('Notes', $prescription->notes),
                $this->detail('Review notes', $prescription->review_notes),
                $this->detail('Rejection reason', $prescription->rejection_reason),
                $this->detail('Approved at', $this->date($prescription->approved_at)),
                $this->detail('Fulfilled at', $this->date($prescription->fulfilled_at)),
            ],
        ]], $actions, [[
            'title' => 'Prescription items',
            'columns' => ['Medicine', 'Dosage', 'Frequency', 'Duration', 'Quantity'],
            'rows' => $prescription->items->map(fn ($item) => [$item->medicine_name ?? $item->product_name ?? '—', $item->dosage ?? '—', $item->frequency ?? '—', $item->duration ?? '—', $item->quantity ?? '—'])->all(),
        ]]);
    }

    private function prescriptionAction(int $id, Request $request, Seller $seller): string
    {
        $request->validate(['command' => ['required', Rule::in(['fulfill'])]]);
        $prescription = Prescription::query()->where('seller_id', $seller->id)->findOrFail($id);
        if ($prescription->status !== 'approved') {
            throw ValidationException::withMessages(['prescription' => 'Only approved prescriptions can be fulfilled.']);
        }
        $prescription->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
        return 'Prescription marked as fulfilled.';
    }

    private function faqsIndex(Request $request, Seller $seller): array
    {
        $items = ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->with(['product', 'asker', 'answerer'])->latest()->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (ProductFaq $faq) => $this->row($faq->id, [
            '#'.$faq->id,
            $faq->product?->title ?? '—',
            Str::limit($faq->question, 80),
            $faq->asker?->name ?? '—',
            $faq->answer ? 'Answered' : 'Pending',
            $faq->status,
            $this->date($faq->created_at),
        ], true))->all();
        return $this->baseIndex('product-faqs', 'Product FAQs', 'Answer customer questions about seller products.', ['ID', 'Product', 'Question', 'Asked by', 'Answer', 'Status', 'Created'], $rows, $items, [
            'Questions' => ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->count(),
            'Unanswered' => ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->whereNull('answer')->count(),
            'Answered' => ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->whereNotNull('answer')->count(),
        ], [$this->selectFilter('status', 'Status', ['pending' => 'Pending', 'published' => 'Published', 'hidden' => 'Hidden'])]);
    }

    private function faqForm(Seller $seller, ?int $id): array
    {
        $faq = $id ? ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->findOrFail($id) : new ProductFaq(['status' => 'published']);
        return $this->baseForm('product-faqs', $id ? 'Answer Product FAQ' : 'Create Product FAQ', [
            ['name' => 'product_id', 'label' => 'Product', 'type' => 'select', 'options' => $this->optionList(Product::query()->where('seller_id', $seller->id)->orderBy('title')->get(), 'title'), 'value' => $faq->product_id, 'required' => true],
            ['name' => 'question', 'label' => 'Question', 'type' => 'textarea', 'value' => $faq->question, 'required' => true],
            ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'value' => $faq->answer],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'published' => 'Published', 'hidden' => 'Hidden'], 'value' => $faq->status, 'required' => true],
        ], $id !== null, $id);
    }

    private function saveFaq(Request $request, User $user, Seller $seller, ?int $id): ProductFaq
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['pending', 'published', 'hidden'])],
        ]);
        Product::query()->where('seller_id', $seller->id)->findOrFail($data['product_id']);
        $faq = $id ? ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->findOrFail($id) : new ProductFaq();
        $faq->fill($data);
        if (! $faq->asked_by) {
            $faq->asked_by = $user->id;
        }
        if (! empty($data['answer'])) {
            $faq->answered_by = $user->id;
            $faq->answered_at = now();
        }
        $faq->save();
        return $faq;
    }

    private function showFaq(int $id, Seller $seller): array
    {
        $faq = ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->with(['product', 'asker', 'answerer'])->findOrFail($id);
        return $this->baseShow('product-faqs', 'FAQ #'.$faq->id, $faq->id, [[
            'title' => 'Question and answer',
            'items' => [
                $this->detail('Product', $faq->product?->title),
                $this->detail('Asked by', $faq->asker?->name),
                $this->detail('Question', $faq->question),
                $this->detail('Answer', $faq->answer),
                $this->detail('Answered by', $faq->answerer?->name),
                $this->detail('Status', $faq->status, 'status'),
                $this->detail('Answered at', $this->date($faq->answered_at)),
            ],
        ]], [[
            'label' => $faq->answer ? 'Update answer' : 'Answer question',
            'action' => 'answer',
            'tone' => 'primary',
            'fields' => [
                ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'value' => $faq->answer, 'required' => true],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['published' => 'Published', 'pending' => 'Pending', 'hidden' => 'Hidden'], 'value' => $faq->status, 'required' => true],
            ],
        ]], [], true, true);
    }

    private function faqAction(int $id, Request $request, User $user, Seller $seller): string
    {
        $faq = ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->findOrFail($id);
        $data = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['published', 'pending', 'hidden'])],
        ]);
        $faq->update(['answer' => $data['answer'], 'status' => $data['status'], 'answered_by' => $user->id, 'answered_at' => now()]);
        return 'FAQ answer saved.';
    }

    private function deleteFaq(int $id, Seller $seller): void
    {
        ProductFaq::query()->whereHas('product', fn (Builder $query) => $query->where('seller_id', $seller->id))->findOrFail($id)->delete();
    }

    private function notificationsIndex(Request $request, User $user): array
    {
        $items = Notification::query()->where('user_id', $user->id)
            ->when($request->filled('read'), fn (Builder $query) => $query->where('is_read', $request->string('read')->toString() === 'read'))
            ->latest()->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (Notification $notification) => $this->row($notification->id, [
            '#'.$notification->id,
            $notification->title,
            Str::limit($notification->message, 90),
            $notification->type,
            $notification->is_read ? 'Read' : 'Unread',
            $this->date($notification->created_at),
        ]))->all();
        return $this->baseIndex('notifications', 'Notifications', 'Read seller alerts for orders, finance, prescriptions and system activity.', ['ID', 'Title', 'Message', 'Type', 'State', 'Created'], $rows, $items, [
            'All' => Notification::query()->where('user_id', $user->id)->count(),
            'Unread' => Notification::query()->where('user_id', $user->id)->where('is_read', false)->count(),
            'Read' => Notification::query()->where('user_id', $user->id)->where('is_read', true)->count(),
        ], [$this->selectFilter('read', 'State', ['unread' => 'Unread', 'read' => 'Read'])], false, [[
            'label' => 'Mark all as read',
            'action' => 'mark-all-read',
            'tone' => 'primary',
            'fields' => [['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'mark-all-read']],
        ]]);
    }

    private function showNotification(int $id, User $user): array
    {
        $notification = Notification::query()->where('user_id', $user->id)->findOrFail($id);
        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }
        return $this->baseShow('notifications', $notification->title, $notification->id, [[
            'title' => 'Notification',
            'items' => [
                $this->detail('Type', $notification->type),
                $this->detail('Message', $notification->message),
                $this->detail('State', $notification->is_read ? 'Read' : 'Unread', 'status'),
                $this->detail('Order ID', $notification->order_id),
                $this->detail('Store ID', $notification->store_id),
                $this->detail('Metadata', $notification->metadata),
                $this->detail('Created', $this->date($notification->created_at)),
            ],
        ]]);
    }

    private function notificationAction(int $id, Request $request, User $user): string
    {
        $notification = Notification::query()->where('user_id', $user->id)->findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        return 'Notification marked as read.';
    }

    private function notificationsPageAction(Request $request, User $user): string
    {
        $request->validate(['command' => ['required', Rule::in(['mark-all-read'])]]);
        Notification::query()->where('user_id', $user->id)->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
        return 'All notifications marked as read.';
    }

    private function bulkUploadsIndex(Request $request, Seller $seller): array
    {
        $items = BulkUploadJob::query()->where('seller_id', $seller->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->latest()->paginate($this->perPage($request));
        $rows = collect($items->items())->map(fn (BulkUploadJob $job) => $this->row($job->uuid, [
            Str::limit($job->uuid, 12),
            $job->type,
            $job->operation,
            $job->status,
            $job->processed_rows.'/'.$job->total_rows,
            $job->successful_rows,
            $job->failed_rows,
            $this->date($job->created_at),
        ]))->all();
        return $this->baseIndex('bulk-uploads', 'Bulk Uploads', 'Import and export catalogue or inventory CSV files in background-compatible jobs.', ['Reference', 'Type', 'Operation', 'Status', 'Progress', 'Success', 'Failed', 'Created'], $rows, $items, [
            'Jobs' => BulkUploadJob::query()->where('seller_id', $seller->id)->count(),
            'Processing' => BulkUploadJob::query()->where('seller_id', $seller->id)->whereIn('status', ['pending', 'processing'])->count(),
            'Failed rows' => BulkUploadJob::query()->where('seller_id', $seller->id)->sum('failed_rows'),
        ], [
            $this->selectFilter('type', 'Type', ['products' => 'Products', 'inventory' => 'Inventory']),
            $this->selectFilter('status', 'Status', ['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed']),
        ], false, [
            [
                'label' => 'Import CSV',
                'action' => 'import',
                'tone' => 'primary',
                'fields' => [
                    ['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'import'],
                    ['name' => 'type', 'label' => 'Import type', 'type' => 'select', 'options' => ['products' => 'Products', 'inventory' => 'Inventory'], 'required' => true],
                    ['name' => 'file', 'label' => 'CSV file', 'type' => 'file', 'required' => true],
                    ['name' => 'notify', 'label' => 'Notify when finished', 'type' => 'checkbox', 'value' => true],
                ],
            ],
            [
                'label' => 'Export CSV',
                'action' => 'export',
                'tone' => 'secondary',
                'fields' => [
                    ['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'export'],
                    ['name' => 'type', 'label' => 'Export type', 'type' => 'select', 'options' => ['products' => 'Products', 'inventory' => 'Inventory'], 'required' => true],
                ],
            ],
            [
                'label' => 'Download template',
                'action' => 'template',
                'tone' => 'outline-primary',
                'fields' => [
                    ['name' => 'command', 'label' => 'Command', 'type' => 'hidden', 'value' => 'template'],
                    ['name' => 'type', 'label' => 'Template type', 'type' => 'select', 'options' => ['products' => 'Products', 'inventory' => 'Inventory'], 'required' => true],
                ],
            ],
        ]);
    }

    private function showBulkUpload(string $uuid, Seller $seller): array
    {
        $job = BulkUploadJob::query()->where('seller_id', $seller->id)->where('uuid', $uuid)->firstOrFail();
        return $this->baseShow('bulk-uploads', 'Bulk Job '.Str::limit($job->uuid, 14), $job->uuid, [[
            'title' => 'Job details',
            'items' => [
                $this->detail('Type', $job->type),
                $this->detail('Operation', $job->operation),
                $this->detail('Status', $job->status, 'status'),
                $this->detail('Original file', $job->original_filename),
                $this->detail('Total rows', $job->total_rows),
                $this->detail('Processed rows', $job->processed_rows),
                $this->detail('Successful rows', $job->successful_rows),
                $this->detail('Failed rows', $job->failed_rows),
                $this->detail('Started', $this->date($job->started_at)),
                $this->detail('Finished', $this->date($job->finished_at)),
                $this->detail('Metadata', $job->metadata),
            ],
        ]]);
    }

    private function bulkUploadPageAction(Request $request, User $user, Seller $seller): string|StreamedResponse
    {
        $data = $request->validate([
            'command' => ['required', Rule::in(['import', 'export', 'template'])],
            'type' => ['required', Rule::in(['products', 'inventory'])],
            'file' => ['required_if:command,import', 'file', 'mimes:csv,txt', 'max:10240'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if ($data['command'] === 'template') {
            $columns = $this->bulk->templates($data['type']);
            return response()->streamDownload(function () use ($columns): void {
                $output = fopen('php://output', 'wb');
                fputcsv($output, $columns);
                fclose($output);
            }, $data['type'].'-template.csv', ['Content-Type' => 'text/csv']);
        }

        $job = $data['command'] === 'import'
            ? $this->bulk->createImportJob($user, $seller, $data['type'], $request->file('file'), $request->boolean('notify'))
            : $this->bulk->createExportJob($user, $seller, $data['type']);

        try {
            $this->bulk->process($job);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return 'Bulk '.$data['command'].' job created successfully.';
    }

    private function perPage(Request $request): int
    {
        return min(100, max(5, (int) $request->input('per_page', 20)));
    }
}
