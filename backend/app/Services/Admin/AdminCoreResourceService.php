<?php

namespace App\Services\Admin;

use App\Enums\GuardNameEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DeliveryService;
use App\Services\PrescriptionService;
use App\Services\ReturnRefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCoreResourceService
{
    public const LIVE_MODULES = [
        'orders', 'returns', 'dispatch', 'categories', 'brands',
        'products', 'inventory', 'tax-classes', 'customers',
        'sellers', 'stores', 'delivery-partners', 'delivery-zones',
        'prescriptions',
    ];

    public function __construct(
        private readonly DeliveryService $delivery,
        private readonly ReturnRefundService $returns,
        private readonly PrescriptionService $prescriptions,
        private readonly AuditService $audit
    ) {
    }

    public function index(string $module, Request $request): array
    {
        $this->ensureModule($module);
        $query = $this->query($module, $request);
        $records = $query->latest('id')
            ->paginate(min(100, max(10, (int) $request->input('per_page', 20))))
            ->withQueryString();

        return [
            'module' => $module,
            'meta' => $this->meta($module),
            'columns' => $this->columns($module),
            'records' => $records->through(fn ($model) => $this->row($module, $model)),
            'filters' => $this->filters($module),
            'stats' => $this->stats($module),
        ];
    }

    public function show(string $module, int $id): array
    {
        $this->ensureModule($module);
        $model = $this->detailQuery($module)->findOrFail($id);

        return [
            'module' => $module,
            'meta' => $this->meta($module),
            'recordId' => $id,
            'title' => $this->displayName($module, $model),
            'details' => $this->details($module, $model),
            'stateControls' => $this->stateControls($module, $model),
            'related' => $this->related($module, $model),
            'special' => $this->special($module, $model),
        ];
    }

    public function updateState(string $module, int $id, Request $request): void
    {
        $rules = $this->stateRules($module);

        if ($rules === []) {
            throw ValidationException::withMessages(['state' => 'This module has no direct state action.']);
        }

        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys($rules))],
            'value' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->validate([
            'value' => ['required', Rule::in($rules[$data['field']])],
        ]);

        $model = $this->stateModel($module, $id);
        $before = $model->toArray();
        $updates = [$data['field'] => $data['value']];

        if ($module === 'sellers' && $data['field'] === 'verification_status') {
            $metadata = $model->metadata ?? [];
            $metadata['verification_reason'] = $data['reason'] ?? null;
            $updates += [
                'visibility_status' => $data['value'] === 'approved' ? 'visible' : 'draft',
                'verified_at' => $data['value'] === 'approved' ? now() : null,
                'verified_by' => Auth::guard('admin')->id(),
                'metadata' => $metadata,
            ];
        }

        if ($module === 'stores' && $data['field'] === 'verification_status') {
            $metadata = $model->metadata ?? [];
            $metadata['verification_reason'] = $data['reason'] ?? null;
            $updates += [
                'visibility_status' => $data['value'] === 'approved' ? 'visible' : 'draft',
                'metadata' => $metadata,
            ];
        }

        if ($module === 'products' && $data['field'] === 'verification_status') {
            $updates['rejection_reason'] = $data['value'] === 'rejected'
                ? ($data['reason'] ?: 'Rejected by admin')
                : null;
        }

        $model->update($updates);

        $this->audit->record(
            Auth::guard('admin')->user(),
            'admin.'.$module.'.'.$data['field'].'_updated',
            $model::class,
            $model->getKey(),
            $before,
            $model->fresh()->toArray()
        );
    }

    public function assignOrder(int $id, int $riderId): void
    {
        $rider = DeliveryBoy::query()->with(['user', 'deliveryZone', 'location'])->findOrFail($riderId);
        $this->delivery->acceptOrder($rider, $id, Auth::guard('admin')->user());
    }

    public function assignReturn(int $id, int $riderId): void
    {
        $rider = DeliveryBoy::query()->findOrFail($riderId);
        $this->returns->assignPickup(Auth::guard('admin')->user(), $id, $rider);
    }

    public function refundReturn(int $id, ?string $comment): void
    {
        $this->returns->refund(Auth::guard('admin')->user(), $id, $comment);
    }

    public function reviewPrescription(int $id, array $data): void
    {
        $this->prescriptions->review(Auth::guard('admin')->user(), $id, $data);
    }

    private function query(string $module, Request $request): Builder
    {
        $query = match ($module) {
            'orders' => Order::query()->with(['user:id,name', 'deliveryZone:id,name', 'deliveryBoy.user:id,name']),
            'returns' => OrderItemReturn::query()->with(['order:id,slug', 'user:id,name', 'seller:id,business_name', 'store:id,name']),
            'dispatch' => Order::query()->where('delivery_type', 'delivery')
                ->whereIn('status', ['ready_for_pickup', 'assigned', 'picked_up', 'out_for_delivery'])
                ->with(['user:id,name', 'deliveryZone:id,name', 'deliveryBoy.user:id,name']),
            'categories' => Category::query()->with('parent:id,title')->withCount('products'),
            'brands' => Brand::query()->withCount('products'),
            'products' => Product::query()->with(['seller:id,business_name', 'category:id,title', 'brand:id,title'])->withCount('variants'),
            'inventory' => StoreProductVariant::query()->with(['store:id,name', 'productVariant:id,product_id,title', 'productVariant.product:id,title']),
            'tax-classes' => TaxClass::query()->withCount('rates'),
            'customers' => User::query()->where('access_panel', GuardNameEnum::WEB->value),
            'sellers' => Seller::query()->with('owner:id,name,email,mobile')->withCount('stores'),
            'stores' => Store::query()->with('seller:id,business_name')->withCount(['zones', 'storeProductVariants']),
            'delivery-partners' => DeliveryBoy::query()->with(['user:id,name,email,mobile', 'deliveryZone:id,name'])->withCount('assignments'),
            'delivery-zones' => DeliveryZone::query()->withCount(['stores', 'users']),
            'prescriptions' => Prescription::query()->with(['user:id,name', 'seller:id,business_name', 'store:id,name'])->withCount('items'),
        };

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $this->applySearch($module, $query, $search);
        }

        $filterMap = match ($module) {
            'orders' => [
                'status' => 'status',
                'payment_status' => 'payment_status',
                'zone_id' => 'delivery_zone_id',
            ],
            'returns' => ['status' => 'return_status'],
            'dispatch' => [
                'status' => 'status',
                'zone_id' => 'delivery_zone_id',
            ],
            'categories', 'brands', 'inventory', 'tax-classes',
            'customers', 'delivery-zones', 'prescriptions' => [
                'status' => 'status',
            ],
            'products', 'sellers', 'stores' => [
                'status' => 'status',
                'verification_status' => 'verification_status',
            ],
            'delivery-partners' => [
                'status' => 'status',
                'verification_status' => 'verification_status',
                'zone_id' => 'delivery_zone_id',
            ],
        };

        foreach ($filterMap as $key => $column) {
            if ($request->filled($key)) {
                $query->where($column, $request->input($key));
            }
        }

        if ($module === 'inventory' && $request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'low_stock_threshold');
        }

        return $query;
    }

    private function detailQuery(string $module): Builder
    {
        return match ($module) {
            'orders', 'dispatch' => Order::query()->with([
                'user', 'deliveryZone', 'deliveryBoy.user', 'items.product',
                'items.variant', 'items.store', 'paymentTransactions',
                'statusLogs', 'deliveryAssignments.deliveryBoy.user',
            ]),
            'returns' => OrderItemReturn::query()->with([
                'order', 'orderItem.product', 'orderItem.variant', 'user',
                'seller', 'store', 'deliveryBoy.user', 'refundTransaction',
            ]),
            'categories' => Category::query()->with(['parent', 'children'])->withCount('products'),
            'brands' => Brand::query()->withCount('products'),
            'products' => Product::query()->with(['seller.owner', 'category', 'brand', 'variants.storeProductVariants.store']),
            'inventory' => StoreProductVariant::query()->with(['store', 'productVariant.product', 'inventoryLogs']),
            'tax-classes' => TaxClass::query()->with('rates'),
            'customers' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->with(['deliveryZones', 'wallet']),
            'sellers' => Seller::query()->with(['owner', 'stores.zones']),
            'stores' => Store::query()->with(['seller.owner', 'zones', 'storeProductVariants.productVariant.product']),
            'delivery-partners' => DeliveryBoy::query()->with(['user', 'deliveryZone', 'location', 'assignments.order']),
            'delivery-zones' => DeliveryZone::query()->with(['stores:id,name', 'users:id,name']),
            'prescriptions' => Prescription::query()->with(['items', 'user', 'seller', 'store', 'reviewer', 'order']),
        };
    }

    private function applySearch(string $module, Builder $query, string $search): void
    {
        $columns = match ($module) {
            'orders', 'dispatch' => ['slug', 'invoice_number', 'billing_name', 'email'],
            'returns' => ['reason', 'details'],
            'categories', 'brands', 'products', 'tax-classes', 'delivery-zones' => ['title', 'slug'],
            'inventory' => ['sku'],
            'customers' => ['name', 'email', 'mobile'],
            'sellers' => ['business_name', 'legal_name'],
            'stores' => ['name', 'contact_email', 'contact_number'],
            'delivery-partners' => ['vehicle_number', 'license_number'],
            'prescriptions' => ['uuid', 'patient_name', 'doctor_name'],
        };

        if ($module === 'tax-classes' || $module === 'delivery-zones') {
            $columns[0] = 'name';
        }

        $query->where(function (Builder $inner) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $index === 0
                    ? $inner->where($column, 'like', '%'.$search.'%')
                    : $inner->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function meta(string $module): array
    {
        return match ($module) {
            'orders' => ['title' => 'Orders', 'description' => 'Monitor marketplace, customer and POS orders.'],
            'returns' => ['title' => 'Return Requests', 'description' => 'Track pickup, seller receipt and refunds.'],
            'dispatch' => ['title' => 'Dispatch Management', 'description' => 'Assign riders and monitor active deliveries.'],
            'categories' => ['title' => 'Categories', 'description' => 'Manage marketplace category hierarchy.'],
            'brands' => ['title' => 'Brands', 'description' => 'Control brand visibility and product coverage.'],
            'products' => ['title' => 'Products', 'description' => 'Review products, ownership and approval status.'],
            'inventory' => ['title' => 'Inventory', 'description' => 'Review store pricing and stock levels.'],
            'tax-classes' => ['title' => 'Tax Classes', 'description' => 'Manage product tax groups and rates.'],
            'customers' => ['title' => 'Customers', 'description' => 'Search customers and monitor account health.'],
            'sellers' => ['title' => 'Seller Management', 'description' => 'Approve businesses and control seller access.'],
            'stores' => ['title' => 'Stores', 'description' => 'Review seller stores, zones and online state.'],
            'delivery-partners' => ['title' => 'Delivery Partners', 'description' => 'Monitor verification, zones and availability.'],
            'delivery-zones' => ['title' => 'Delivery Zones', 'description' => 'Review coverage, charges and adoption.'],
            'prescriptions' => ['title' => 'Prescriptions', 'description' => 'Review prescriptions and assign approved pharmacies.'],
        };
    }

    private function columns(string $module): array
    {
        return match ($module) {
            'orders' => ['order', 'customer', 'zone', 'status', 'payment', 'total', 'created'],
            'returns' => ['return', 'order', 'customer', 'seller', 'status', 'pickup', 'refund'],
            'dispatch' => ['order', 'customer', 'zone', 'rider', 'status', 'total'],
            'categories' => ['category', 'parent', 'products', 'commission', 'status'],
            'brands' => ['brand', 'products', 'scope', 'status'],
            'products' => ['product', 'seller', 'category', 'brand', 'variants', 'verification', 'status'],
            'inventory' => ['product', 'variant', 'store', 'sku', 'price', 'stock', 'status'],
            'tax-classes' => ['tax', 'rates', 'default', 'status'],
            'customers' => ['customer', 'email', 'mobile', 'status', 'joined'],
            'sellers' => ['seller', 'owner', 'contact', 'stores', 'verification', 'status'],
            'stores' => ['store', 'seller', 'location', 'zones', 'inventory', 'verification', 'status'],
            'delivery-partners' => ['partner', 'contact', 'zone', 'vehicle', 'assignments', 'verification', 'status'],
            'delivery-zones' => ['zone', 'center', 'radius', 'stores', 'users', 'status'],
            'prescriptions' => ['prescription', 'patient', 'customer', 'items', 'pharmacy', 'status'],
        };
    }

    private function row(string $module, mixed $model): array
    {
        return match ($module) {
            'orders' => [
                'id' => $model->id, 'order' => $model->slug,
                'customer' => $model->user?->name ?? $model->billing_name ?? 'Guest',
                'zone' => $model->deliveryZone?->name ?? '—',
                'status' => $model->status, 'payment' => $model->payment_status,
                'total' => (float) $model->final_total, 'created' => $model->created_at,
            ],
            'returns' => [
                'id' => $model->id, 'return' => '#'.$model->id,
                'order' => $model->order?->slug ?? '—',
                'customer' => $model->user?->name ?? '—',
                'seller' => $model->seller?->business_name ?? '—',
                'status' => $model->return_status, 'pickup' => $model->pickup_status,
                'refund' => (float) $model->refund_amount,
            ],
            'dispatch' => [
                'id' => $model->id, 'order' => $model->slug,
                'customer' => $model->user?->name ?? $model->billing_name ?? 'Guest',
                'zone' => $model->deliveryZone?->name ?? '—',
                'rider' => $model->deliveryBoy?->user?->name ?? 'Unassigned',
                'status' => $model->status, 'total' => (float) $model->final_total,
            ],
            'categories' => [
                'id' => $model->id, 'category' => $model->title,
                'parent' => $model->parent?->title ?? 'Root',
                'products' => $model->products_count,
                'commission' => number_format((float) $model->commission, 2).'%',
                'status' => $model->status,
            ],
            'brands' => [
                'id' => $model->id, 'brand' => $model->title,
                'products' => $model->products_count,
                'scope' => str($model->scope_type ?? 'global')->replace('_', ' ')->title(),
                'status' => $model->status,
            ],
            'products' => [
                'id' => $model->id, 'product' => $model->title,
                'seller' => $model->seller?->business_name ?? '—',
                'category' => $model->category?->title ?? '—',
                'brand' => $model->brand?->title ?? '—',
                'variants' => $model->variants_count,
                'verification' => $model->verification_status,
                'status' => $model->status,
            ],
            'inventory' => [
                'id' => $model->id,
                'product' => $model->productVariant?->product?->title ?? '—',
                'variant' => $model->productVariant?->title ?? '—',
                'store' => $model->store?->name ?? '—', 'sku' => $model->sku,
                'price' => (float) $model->effectivePrice(), 'stock' => $model->stock,
                'status' => $model->status,
            ],
            'tax-classes' => [
                'id' => $model->id, 'tax' => $model->name,
                'rates' => $model->rates_count, 'default' => $model->is_default ? 'Yes' : 'No',
                'status' => $model->status,
            ],
            'customers' => [
                'id' => $model->id, 'customer' => $model->name,
                'email' => $model->email, 'mobile' => $model->mobile ?: '—',
                'status' => $model->status, 'joined' => $model->created_at,
            ],
            'sellers' => [
                'id' => $model->id, 'seller' => $model->business_name,
                'owner' => $model->owner?->name ?? '—',
                'contact' => $model->owner?->email ?? $model->owner?->mobile ?? '—',
                'stores' => $model->stores_count,
                'verification' => $model->verification_status, 'status' => $model->status,
            ],
            'stores' => [
                'id' => $model->id, 'store' => $model->name,
                'seller' => $model->seller?->business_name ?? '—',
                'location' => collect([$model->city, $model->state])->filter()->implode(', ') ?: '—',
                'zones' => $model->zones_count, 'inventory' => $model->store_product_variants_count,
                'verification' => $model->verification_status, 'status' => $model->status,
            ],
            'delivery-partners' => [
                'id' => $model->id, 'partner' => $model->user?->name ?? '—',
                'contact' => $model->user?->mobile ?? $model->user?->email ?? '—',
                'zone' => $model->deliveryZone?->name ?? '—',
                'vehicle' => collect([$model->vehicle_type, $model->vehicle_number])->filter()->implode(' · ') ?: '—',
                'assignments' => $model->assignments_count,
                'verification' => $model->verification_status, 'status' => $model->status,
            ],
            'delivery-zones' => [
                'id' => $model->id, 'zone' => $model->name,
                'center' => $model->center_latitude.', '.$model->center_longitude,
                'radius' => number_format((float) $model->radius_km, 2).' km',
                'stores' => $model->stores_count, 'users' => $model->users_count,
                'status' => $model->status,
            ],
            'prescriptions' => [
                'id' => $model->id,
                'prescription' => str($model->uuid)->substr(0, 8)->upper(),
                'patient' => $model->patient_name,
                'customer' => $model->user?->name ?? '—', 'items' => $model->items_count,
                'pharmacy' => $model->store?->name ?? $model->seller?->business_name ?? 'Unassigned',
                'status' => $model->status,
            ],
        };
    }

    private function filters(string $module): array
    {
        $filters = [];
        if (in_array($module, ['orders', 'dispatch', 'delivery-partners'], true)) {
            $filters['zone_id'] = DeliveryZone::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
        }
        if ($module === 'orders') {
            $filters['status'] = Order::query()->distinct()->pluck('status')->filter()->values()->all();
            $filters['payment_status'] = Order::query()->distinct()->pluck('payment_status')->filter()->values()->all();
        } elseif ($module === 'returns') {
            $filters['status'] = OrderItemReturn::query()->distinct()->pluck('return_status')->filter()->values()->all();
        } elseif ($module === 'prescriptions') {
            $filters['status'] = Prescription::query()->distinct()->pluck('status')->filter()->values()->all();
        } else {
            $rules = $this->stateRules($module);
            foreach ($rules as $field => $values) {
                $filters[$field] = $values;
            }
        }
        if ($module === 'inventory') {
            $filters['low_stock'] = ['1' => 'Low stock only'];
        }
        return $filters;
    }

    private function stats(string $module): array
    {
        return match ($module) {
            'orders' => ['All orders' => Order::query()->count(), 'Pending' => Order::query()->whereIn('status', ['pending', 'confirmed'])->count(), 'Delivered' => Order::query()->where('status', 'delivered')->count(), 'Today' => Order::query()->whereDate('created_at', today())->count()],
            'returns' => ['All returns' => OrderItemReturn::query()->count(), 'Requested' => OrderItemReturn::query()->where('return_status', 'requested')->count(), 'Pickup ready' => OrderItemReturn::query()->where('return_status', 'seller_approved')->count(), 'Refunded' => OrderItemReturn::query()->where('return_status', 'refund_processed')->count()],
            'dispatch' => ['Ready' => Order::query()->where('status', 'ready_for_pickup')->count(), 'Assigned' => Order::query()->where('status', 'assigned')->count(), 'Picked up' => Order::query()->where('status', 'picked_up')->count(), 'Out for delivery' => Order::query()->where('status', 'out_for_delivery')->count()],
            'categories' => ['Categories' => Category::query()->count(), 'Root' => Category::query()->whereNull('parent_id')->count(), 'Home' => Category::query()->where('is_home_category', true)->count(), 'Inactive' => Category::query()->where('status', 'inactive')->count()],
            'brands' => ['Brands' => Brand::query()->count(), 'Active' => Brand::query()->where('status', 'active')->count(), 'Inactive' => Brand::query()->where('status', 'inactive')->count(), 'With products' => Brand::query()->has('products')->count()],
            'products' => ['Products' => Product::query()->count(), 'Pending' => Product::query()->where('verification_status', 'pending')->count(), 'Approved' => Product::query()->where('verification_status', 'approved')->count(), 'Active' => Product::query()->where('status', 'active')->count()],
            'inventory' => ['Inventory rows' => StoreProductVariant::query()->count(), 'In stock' => StoreProductVariant::query()->where('stock', '>', 0)->count(), 'Out of stock' => StoreProductVariant::query()->where('stock', '<=', 0)->count(), 'Low stock' => StoreProductVariant::query()->whereColumn('stock', '<=', 'low_stock_threshold')->count()],
            'tax-classes' => ['Tax classes' => TaxClass::query()->count(), 'Active' => TaxClass::query()->where('status', 'active')->count(), 'Inactive' => TaxClass::query()->where('status', 'inactive')->count(), 'Default' => TaxClass::query()->where('is_default', true)->count()],
            'customers' => ['Customers' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->count(), 'Active' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->where('status', 'active')->count(), 'Verified' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->whereNotNull('email_verified_at')->count(), 'Joined today' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->whereDate('created_at', today())->count()],
            'sellers' => ['Sellers' => Seller::query()->count(), 'Pending' => Seller::query()->where('verification_status', 'pending')->count(), 'Approved' => Seller::query()->where('verification_status', 'approved')->count(), 'Active' => Seller::query()->where('status', 'active')->count()],
            'stores' => ['Stores' => Store::query()->count(), 'Online' => Store::query()->where('status', 'online')->count(), 'Pending' => Store::query()->where('verification_status', 'pending')->count(), 'Recommended' => Store::query()->where('is_recommended', true)->count()],
            'delivery-partners' => ['Partners' => DeliveryBoy::query()->count(), 'Available' => DeliveryBoy::query()->where('status', 'available')->count(), 'On delivery' => DeliveryBoy::query()->where('status', 'on_delivery')->count(), 'Pending' => DeliveryBoy::query()->where('verification_status', 'pending')->count()],
            'delivery-zones' => ['Zones' => DeliveryZone::query()->count(), 'Active' => DeliveryZone::query()->where('status', 'active')->count(), 'Rush enabled' => DeliveryZone::query()->where('rush_delivery_enabled', true)->count(), 'Inactive' => DeliveryZone::query()->where('status', 'inactive')->count()],
            'prescriptions' => ['Prescriptions' => Prescription::query()->count(), 'Pending' => Prescription::query()->where('status', 'pending')->count(), 'Under review' => Prescription::query()->where('status', 'under_review')->count(), 'Approved' => Prescription::query()->where('status', 'approved')->count()],
        };
    }

    private function details(string $module, mixed $model): array
    {
        $array = $model->toArray();
        $allowed = match ($module) {
            'orders', 'dispatch' => ['slug', 'invoice_number', 'status', 'payment_method', 'payment_status', 'delivery_type', 'source', 'subtotal', 'delivery_charge', 'final_total', 'billing_name', 'shipping_phone', 'shipping_address_1', 'shipping_city', 'created_at'],
            'returns' => ['id', 'quantity', 'reason', 'details', 'refund_amount', 'refund_method', 'pickup_status', 'return_status', 'seller_comment', 'admin_comment', 'requested_at'],
            'categories' => ['title', 'slug', 'description', 'status', 'commission', 'sort_order', 'is_home_category', 'created_at'],
            'brands' => ['title', 'slug', 'description', 'scope_type', 'status', 'created_at'],
            'products' => ['title', 'slug', 'short_description', 'status', 'verification_status', 'rejection_reason', 'created_at'],
            'inventory' => ['sku', 'price', 'special_price', 'cost', 'stock', 'low_stock_threshold', 'status', 'created_at'],
            'tax-classes' => ['name', 'slug', 'description', 'is_default', 'status', 'created_at'],
            'customers' => ['name', 'email', 'mobile', 'country', 'status', 'reward_points', 'email_verified_at', 'mobile_verified_at', 'created_at'],
            'sellers' => ['business_name', 'legal_name', 'trade_license_number', 'tax_number', 'city', 'commission_rate', 'verification_status', 'visibility_status', 'status', 'created_at'],
            'stores' => ['name', 'contact_email', 'contact_number', 'city', 'state', 'status', 'verification_status', 'visibility_status', 'is_recommended', 'allows_pickup', 'created_at'],
            'delivery-partners' => ['status', 'verification_status', 'is_blocked', 'blocked_reason', 'vehicle_type', 'vehicle_number', 'license_number', 'created_at'],
            'delivery-zones' => ['name', 'slug', 'center_latitude', 'center_longitude', 'radius_km', 'regular_delivery_charges', 'rush_delivery_charges', 'free_delivery_amount', 'status', 'created_at'],
            'prescriptions' => ['uuid', 'patient_name', 'patient_age', 'doctor_name', 'doctor_registration_no', 'prescribed_at', 'notes', 'status', 'review_notes', 'rejection_reason', 'expires_at', 'created_at'],
        };

        return collect($allowed)->mapWithKeys(fn ($key) => [$key => $array[$key] ?? null])->all();
    }

    private function related(string $module, mixed $model): array
    {
        return match ($module) {
            'orders', 'dispatch' => ['title' => 'Order items', 'rows' => $model->items->map(fn ($item) => [$item->product_title, $item->variant_title, $item->quantity, '৳'.number_format((float) $item->subtotal, 2), $item->status])->all()],
            'products' => ['title' => 'Variants', 'rows' => $model->variants->map(fn ($variant) => [$variant->title, $variant->storeProductVariants->count(), $variant->storeProductVariants->sum('stock')])->all()],
            'sellers' => ['title' => 'Stores', 'rows' => $model->stores->map(fn ($store) => [$store->name, $store->city, $store->verification_status, $store->status])->all()],
            'stores' => ['title' => 'Inventory', 'rows' => $model->storeProductVariants->take(30)->map(fn ($inventory) => [$inventory->productVariant?->product?->title, $inventory->sku, $inventory->stock, $inventory->status])->all()],
            'delivery-partners' => ['title' => 'Assignments', 'rows' => $model->assignments->sortByDesc('id')->take(20)->map(fn ($assignment) => [$assignment->order?->slug, $assignment->status, $assignment->total_earnings, $assignment->assigned_at?->format('d M Y H:i')])->values()->all()],
            'prescriptions' => ['title' => 'Medicines', 'rows' => $model->items->map(fn ($item) => [$item->medicine_name, $item->strength, $item->dosage, $item->duration, $item->quantity])->all()],
            default => ['title' => null, 'rows' => []],
        };
    }

    private function special(string $module, mixed $model): ?array
    {
        if (in_array($module, ['orders', 'dispatch'], true)) {
            return [
                'type' => 'assign-order',
                'enabled' => $model->status === 'ready_for_pickup',
                'riders' => $this->availableRiders($model->delivery_zone_id),
            ];
        }

        if ($module === 'returns') {
            return [
                'type' => 'return-actions',
                'canAssign' => $model->return_status === 'seller_approved' && ! $model->delivery_boy_id,
                'canRefund' => $model->return_status === 'received_by_seller' && ! $model->refundTransaction,
                'riders' => $this->availableRiders($model->order?->delivery_zone_id),
            ];
        }

        if ($module === 'prescriptions') {
            return [
                'type' => 'prescription-review',
                'sellers' => Seller::query()
                    ->where('verification_status', 'approved')
                    ->where('status', 'active')
                    ->with(['stores' => fn ($query) => $query->where('verification_status', 'approved')])
                    ->orderBy('business_name')
                    ->get(),
            ];
        }

        return null;
    }

    private function displayName(string $module, mixed $model): string
    {
        return match ($module) {
            'orders', 'dispatch' => $model->slug,
            'returns' => 'Return #'.$model->id,
            'categories', 'brands', 'products' => $model->title,
            'inventory' => $model->productVariant?->product?->title ?? 'Inventory #'.$model->id,
            'tax-classes', 'delivery-zones' => $model->name,
            'customers' => $model->name,
            'sellers' => $model->business_name,
            'stores' => $model->name,
            'delivery-partners' => $model->user?->name ?? 'Delivery Partner',
            'prescriptions' => 'Prescription '.str($model->uuid)->substr(0, 8)->upper(),
        };
    }

    private function stateRules(string $module): array
    {
        return match ($module) {
            'categories', 'brands', 'tax-classes', 'delivery-zones', 'inventory' => ['status' => ['active', 'inactive']],
            'customers' => ['status' => ['active', 'inactive', 'blocked']],
            'sellers' => ['verification_status' => ['pending', 'approved', 'rejected'], 'status' => ['active', 'inactive', 'blocked']],
            'stores' => ['verification_status' => ['pending', 'approved', 'rejected'], 'status' => ['online', 'offline', 'blocked']],
            'products' => ['verification_status' => ['pending', 'approved', 'rejected'], 'status' => ['active', 'draft', 'inactive']],
            'delivery-partners' => ['verification_status' => ['pending', 'approved', 'rejected'], 'status' => ['available', 'offline', 'on_delivery', 'suspended']],
            default => [],
        };
    }

    private function stateControls(string $module, mixed $model): array
    {
        return collect($this->stateRules($module))->map(
            fn (array $options, string $field) => [
                'field' => $field,
                'label' => str($field)->replace('_', ' ')->title()->toString(),
                'current' => $model->{$field},
                'options' => $options,
            ]
        )->values()->all();
    }

    private function stateModel(string $module, int $id): mixed
    {
        return match ($module) {
            'categories' => Category::query()->findOrFail($id),
            'brands' => Brand::query()->findOrFail($id),
            'products' => Product::query()->findOrFail($id),
            'inventory' => StoreProductVariant::query()->findOrFail($id),
            'tax-classes' => TaxClass::query()->findOrFail($id),
            'customers' => User::query()->where('access_panel', GuardNameEnum::WEB->value)->findOrFail($id),
            'sellers' => Seller::query()->findOrFail($id),
            'stores' => Store::query()->findOrFail($id),
            'delivery-partners' => DeliveryBoy::query()->findOrFail($id),
            'delivery-zones' => DeliveryZone::query()->findOrFail($id),
            default => throw ValidationException::withMessages(['state' => 'Unsupported state action.']),
        };
    }

    private function availableRiders(?int $zoneId): array
    {
        return DeliveryBoy::query()
            ->where('verification_status', 'approved')
            ->where('is_blocked', false)
            ->where('status', 'available')
            ->when($zoneId, fn (Builder $query) => $query->where('delivery_zone_id', $zoneId))
            ->with('user:id,name,mobile')
            ->get()
            ->all();
    }

    private function ensureModule(string $module): void
    {
        abort_unless(in_array($module, self::LIVE_MODULES, true), 404);
    }
}
