param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $directory = Split-Path $Path -Parent

    if ($directory) {
        New-Item -ItemType Directory -Force -Path $directory |
            Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText(
        $Path,
        $Content,
        $utf8NoBom
    )
}

function Backup-File(
    [string]$Path,
    [string]$BackupRoot
) {
    if (-not (Test-Path $Path)) {
        return
    }

    $relative = $Path.Substring(
        $BackendPath.Length
    ).TrimStart("\")

    $destination = Join-Path $BackupRoot $relative
    $directory = Split-Path $destination -Parent

    New-Item -ItemType Directory -Force -Path $directory |
        Out-Null

    Copy-Item $Path $destination -Force
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path `
    $BackendPath `
    "_phase4_backups\$timestamp"

New-Item -ItemType Directory -Force -Path $backupRoot |
    Out-Null

$targets = @(
    "$BackendPath\routes\api.php",
    "$BackendPath\routes\commerce.php",
    "$BackendPath\app\Http\Controllers\Api\ProductApiController.php",
    "$BackendPath\app\Http\Resources\ProductListResource.php",
    "$BackendPath\app\Http\Resources\ProductVariantResource.php"
)

foreach ($target in $targets) {
    Backup-File $target $backupRoot
}

Write-Utf8NoBom -Path "$BackendPath\database\migrations\2026_07_28_040000_create_commerce_core_tables.php" -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('landmark')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('mobile', 32);
            $table->string('address_type', 20)->default('home');
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'address_type']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wishlist_id')
                ->constrained('wishlists')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['wishlist_id', 'product_variant_id', 'store_id'],
                'wishlist_variant_store_unique'
            );
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('save_for_later')->default(false);
            $table->timestamps();

            $table->unique(
                ['cart_id', 'product_variant_id', 'store_id'],
                'cart_variant_store_unique'
            );
            $table->index(['cart_id', 'save_for_later']);
        });

        Schema::create('promos', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_amount', 12, 2);
            $table->string('promo_mode', 20)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('individual_use')->default(false);
            $table->unsignedInteger('max_total_usage')->nullable();
            $table->unsignedInteger('max_usage_per_user')->nullable();
            $table->decimal('min_order_total', 12, 2)->default(0);
            $table->decimal('max_discount_value', 12, 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['promo_mode', 'scope_id']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('promo_user_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_id')
                ->constrained('promos')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['promo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_user_usages');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('addresses');
    }
};
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Address.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'address_line1',
        'address_line2',
        'city',
        'landmark',
        'state',
        'zipcode',
        'mobile',
        'address_type',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Cart.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Cart extends Model
{
    protected $fillable = ['uuid', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            $cart->uuid ??= (string) Str::uuid();
        });
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\CartItem.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'store_id',
        'quantity',
        'save_for_later',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'save_for_later' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Promo.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'start_date',
        'end_date',
        'discount_type',
        'discount_amount',
        'promo_mode',
        'scope_id',
        'usage_count',
        'individual_use',
        'max_total_usage',
        'max_usage_per_user',
        'min_order_total',
        'max_discount_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'discount_amount' => 'decimal:2',
            'usage_count' => 'integer',
            'individual_use' => 'boolean',
            'max_total_usage' => 'integer',
            'max_usage_per_user' => 'integer',
            'min_order_total' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
        ];
    }

    public function userUsages(): HasMany
    {
        return $this->hasMany(PromoUserUsage::class);
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return $this->max_total_usage === null
            || $this->usage_count < $this->max_total_usage;
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\PromoUserUsage.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoUserUsage extends Model
{
    protected $fillable = [
        'promo_id',
        'user_id',
        'usage_count',
    ];

    protected function casts(): array
    {
        return ['usage_count' => 'integer'];
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Wishlist.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wishlist extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'slug',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $wishlist): void {
            $wishlist->uuid ??= (string) Str::uuid();
            $wishlist->slug = self::uniqueSlug(
                $wishlist->user_id,
                $wishlist->title
            );
        });

        static::updating(function (self $wishlist): void {
            if ($wishlist->isDirty('title')) {
                $wishlist->slug = self::uniqueSlug(
                    $wishlist->user_id,
                    $wishlist->title,
                    $wishlist->id
                );
            }
        });
    }

    private static function uniqueSlug(
        int $userId,
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'wishlist';
        $slug = $base;
        $counter = 2;

        while (
            self::query()
                ->where('user_id', $userId)
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\WishlistItem.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    protected $fillable = [
        'wishlist_id',
        'product_id',
        'product_variant_id',
        'store_id',
    ];

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\CommerceService.php" -Content @'
<?php

namespace App\Services;

use App\Enums\WalletTypeEnum;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommerceService
{
    public function __construct(
        protected PromoService $promoService
    ) {
    }

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    public function add(User $user, array $data): CartItem
    {
        return DB::transaction(function () use ($user, $data): CartItem {
            $cart = $this->getOrCreateCart($user);
            $inventory = $this->inventory(
                (int) $data['product_variant_id'],
                (int) $data['store_id']
            );

            $product = $inventory->productVariant->product;
            $quantity = (int) $data['quantity'];

            $this->validateQuantity(
                $quantity,
                $product,
                $inventory->stock
            );

            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_variant_id' =>
                    $inventory->product_variant_id,
                'store_id' => $inventory->store_id,
            ]);

            $newQuantity = $item->exists
                ? $item->quantity + $quantity
                : $quantity;

            $this->validateQuantity(
                $newQuantity,
                $product,
                $inventory->stock
            );

            $item->fill([
                'product_id' => $product->id,
                'quantity' => $newQuantity,
                'save_for_later' => false,
            ])->save();

            return $item->fresh($this->relations());
        });
    }

    public function update(
        User $user,
        int $cartItemId,
        int $quantity
    ): CartItem {
        $item = $this->ownedItem($user, $cartItemId);
        $inventory = $this->inventory(
            $item->product_variant_id,
            $item->store_id
        );

        $this->validateQuantity(
            $quantity,
            $inventory->productVariant->product,
            $inventory->stock
        );

        $item->update(['quantity' => $quantity]);

        return $item->fresh($this->relations());
    }

    public function remove(User $user, int $cartItemId): void
    {
        $this->ownedItem($user, $cartItemId)->delete();
    }

    public function clear(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();
    }

    public function toggleSaveForLater(
        User $user,
        int $cartItemId
    ): CartItem {
        $item = $this->ownedItem($user, $cartItemId);
        $item->update([
            'save_for_later' => ! $item->save_for_later,
        ]);

        return $item->fresh($this->relations());
    }

    public function sync(User $user, array $items): array
    {
        $result = [
            'synced' => 0,
            'failed' => [],
        ];

        foreach ($items as $index => $data) {
            try {
                $this->add($user, $data);
                $result['synced']++;
            } catch (\Throwable $e) {
                $result['failed'][] = [
                    'index' => $index,
                    'product_variant_id' =>
                        $data['product_variant_id'] ?? null,
                    'store_id' => $data['store_id'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    public function payload(
        User $user,
        array $options = []
    ): array {
        $cart = $this->getOrCreateCart($user);

        $items = $cart->items()
            ->with($this->relations())
            ->orderBy('created_at')
            ->get();

        $active = $items->where('save_for_later', false)->values();
        $removed = [];
        $itemPayloads = [];

        foreach ($active as $item) {
            $inventory = $this->inventoryForItem($item);

            if (! $inventory || $inventory->stock < 1) {
                $removed[] = $this->removedItem($item, 'out_of_stock');
                continue;
            }

            if ($item->quantity > $inventory->stock) {
                $removed[] = $this->removedItem(
                    $item,
                    'quantity_adjusted'
                );
                $item->quantity = $inventory->stock;
                $item->save();
            }

            $itemPayloads[] = $this->itemPayload($item, $inventory);
        }

        $summary = $this->paymentSummary(
            $user,
            $active,
            collect($itemPayloads),
            $options
        );

        return [
            'id' => $cart->id,
            'uuid' => $cart->uuid,
            'user_id' => $cart->user_id,
            'items_count' => count($itemPayloads),
            'total_quantity' => collect($itemPayloads)
                ->sum('quantity'),
            'items' => $itemPayloads,
            'payment_summary' => $summary,
            'removed_items' => $removed,
            'removed_count' => count($removed),
            'delivery_zone' => $summary['delivery_zone'],
            'created_at' => $cart->created_at?->format(
                'Y-m-d H:i:s'
            ),
            'updated_at' => $cart->updated_at?->format(
                'Y-m-d H:i:s'
            ),
        ];
    }

    public function savedItems(User $user): array
    {
        $cart = $this->getOrCreateCart($user);

        return $cart->items()
            ->where('save_for_later', true)
            ->with($this->relations())
            ->get()
            ->map(function (CartItem $item): array {
                $inventory = $this->inventoryForItem($item);

                return $inventory
                    ? $this->itemPayload($item, $inventory)
                    : $this->removedItem($item, 'unavailable');
            })
            ->values()
            ->all();
    }

    private function paymentSummary(
        User $user,
        Collection $items,
        Collection $payloads,
        array $options
    ): array {
        $itemsTotal = round(
            (float) $payloads->sum('total_item_special_price'),
            2
        );

        [$latitude, $longitude] = $this->coordinates(
            $user,
            $options
        );

        $zoneInfo = ($latitude !== null && $longitude !== null)
            ? DeliveryZoneService::getZonesAtPoint(
                $latitude,
                $longitude
            )
            : ['zone' => null, 'zone_id' => null];

        /** @var DeliveryZone|null $zone */
        $zone = $zoneInfo['zone'] ?? null;
        $totalStores = $payloads
            ->pluck('store.id')
            ->filter()
            ->unique()
            ->count();

        $isRushRequested = filter_var(
            $options['rush_delivery'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $rushAvailable = (bool) (
            $zone?->rush_delivery_enabled ?? false
        );
        $isRush = $isRushRequested && $rushAvailable;

        $distanceKm = $this->maxStoreDistance(
            $payloads,
            $latitude,
            $longitude
        );

        $regular = (float) (
            $zone?->regular_delivery_charges ?? 0
        );
        $rush = (float) ($zone?->rush_delivery_charges ?? 0);
        $baseDelivery = $isRush ? $rush : $regular;

        if (
            $zone
            && (float) $zone->free_delivery_amount > 0
            && $itemsTotal >= (float) $zone->free_delivery_amount
        ) {
            $baseDelivery = 0;
        }

        $dropFee = max(0, $totalStores - 1)
            * (float) ($zone?->per_store_drop_off_fee ?? 0);
        $distanceCharge = round(
            $distanceKm
            * (float) (
                $zone?->distance_based_delivery_charges ?? 0
            ),
            2
        );
        $handling = (float) ($zone?->handling_charges ?? 0);
        $delivery = round(
            $baseDelivery + $dropFee + $distanceCharge,
            2
        );

        $promo = $this->promoService->validate(
            $options['promo_code'] ?? null,
            $user,
            $itemsTotal,
            $items
        );

        $subtotalAfterPromo = max(
            0,
            $itemsTotal - $promo['discount']
        );
        $beforeWallet = round(
            $subtotalAfterPromo + $delivery + $handling,
            2
        );

        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', WalletTypeEnum::CUSTOMER->value)
            ->first();

        $walletBalance = max(
            0,
            (float) ($wallet?->balance ?? 0)
            - (float) ($wallet?->blocked_balance ?? 0)
        );

        $useWallet = filter_var(
            $options['use_wallet'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $walletUsed = $useWallet
            ? min($walletBalance, $beforeWallet)
            : 0.0;

        $estimatedTime = $this->estimatedDeliveryTime(
            $payloads,
            $zone,
            $distanceKm,
            $isRush
        );

        return [
            'items_total' => number_format(
                $itemsTotal,
                2,
                '.',
                ''
            ),
            'per_store_drop_off_fee' => number_format(
                $dropFee,
                2,
                '.',
                ''
            ),
            'is_rush_delivery' => $isRush,
            'is_rush_delivery_available' => $rushAvailable,
            'delivery_charges' => number_format(
                $delivery,
                2,
                '.',
                ''
            ),
            'regular_delivery_charge' => number_format(
                $regular,
                2,
                '.',
                ''
            ),
            'rush_delivery_charge' => number_format(
                $rush,
                2,
                '.',
                ''
            ),
            'handling_charges' => number_format(
                $handling,
                2,
                '.',
                ''
            ),
            'delivery_distance_charges' => number_format(
                $distanceCharge,
                2,
                '.',
                ''
            ),
            'delivery_distance_km' => round($distanceKm, 2),
            'total_stores' => $totalStores,
            'total_delivery_charges' => number_format(
                $delivery + $handling,
                2,
                '.',
                ''
            ),
            'estimated_delivery_time' => $estimatedTime,
            'use_wallet' => $useWallet,
            'promo_code' => $options['promo_code'] ?? null,
            'promo_discount' => number_format(
                $promo['discount'],
                2,
                '.',
                ''
            ),
            'promo_applied' => $promo['success']
                ? $promo['promo']
                : null,
            'promo_error' => $promo['success']
                ? null
                : $promo['message'],
            'wallet_balance' => number_format(
                $walletBalance,
                2,
                '.',
                ''
            ),
            'wallet_amount_used' => number_format(
                $walletUsed,
                2,
                '.',
                ''
            ),
            'payable_amount' => number_format(
                max(0, $beforeWallet - $walletUsed),
                2,
                '.',
                ''
            ),
            'delivery_zone' => $zone ? [
                'id' => $zone->id,
                'name' => $zone->name,
                'slug' => $zone->slug,
            ] : null,
        ];
    }

    private function itemPayload(
        CartItem $item,
        StoreProductVariant $inventory
    ): array {
        $effective = $inventory->effectivePrice();
        $regularTotal = round(
            (float) $inventory->price * $item->quantity,
            2
        );
        $effectiveTotal = round(
            $effective * $item->quantity,
            2
        );

        return [
            'id' => $item->id,
            'cart_id' => $item->cart_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'store_id' => $item->store_id,
            'quantity' => $item->quantity,
            'save_for_later' => (bool) $item->save_for_later,
            'product' => [
                'id' => $item->product?->id,
                'name' => $item->product?->title,
                'title' => $item->product?->title,
                'slug' => $item->product?->slug,
                'minimum_order_quantity' => (int) (
                    $item->product?->minimum_order_quantity ?? 1
                ),
                'quantity_step_size' => (int) (
                    $item->product?->quantity_step_size ?? 1
                ),
                'total_allowed_quantity' => (int) (
                    $item->product?->total_allowed_quantity ?? 1
                ),
                'is_attachment_required' => (bool) (
                    $item->product?->is_attachment_required ?? false
                ),
                'attachment_mode' =>
                    $item->product?->attachment_mode,
                'image' => $item->product?->mainImageUrl() ?? '',
                'estimated_delivery_time' =>
                    $item->product?->base_prep_time ?? 0,
                'image_fit' =>
                    $item->product?->image_fit ?? 'contain',
                'store_status' => [
                    'is_open' => $item->store?->status === 'online',
                    'status' => $item->store?->status,
                ],
                'ratings' => 0,
                'rating_count' => 0,
            ],
            'variant' => [
                'id' => $item->variant?->id,
                'title' => $item->variant?->title,
                'slug' => $item->variant?->slug,
                'image' => $item->variant?->imageUrl() ?? '',
                'price' => $inventory->price,
                'special_price' => $inventory->special_price,
                'stock' => $inventory->stock,
                'sku' => $inventory->sku,
                'is_addons' => false,
            ],
            'store' => [
                'id' => $item->store?->id,
                'name' => $item->store?->name,
                'slug' => $item->store?->slug,
                'total_products' => 0,
                'status' => [
                    'is_open' => $item->store?->status === 'online',
                    'status' => $item->store?->status,
                ],
                'allows_pickup' =>
                    (bool) ($item->store?->allows_pickup ?? false),
                'pickup_instructions' =>
                    $item->store?->pickup_instructions,
            ],
            'addons' => [],
            'addons_total' => 0.0,
            'total_item_price' => number_format(
                $regularTotal,
                2,
                '.',
                ''
            ),
            'total_item_special_price' => number_format(
                $effectiveTotal,
                2,
                '.',
                ''
            ),
            'created_at' => $item->created_at?->format(
                'Y-m-d H:i:s'
            ),
            'updated_at' => $item->updated_at?->format(
                'Y-m-d H:i:s'
            ),
        ];
    }

    private function relations(): array
    {
        return [
            'product',
            'variant',
            'store',
        ];
    }

    private function inventory(
        int $variantId,
        int $storeId
    ): StoreProductVariant {
        $inventory = StoreProductVariant::query()
            ->where('product_variant_id', $variantId)
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->with([
                'productVariant.product',
                'store',
            ])
            ->first();

        if (
            ! $inventory
            || ! $inventory->productVariant
            || ! $inventory->productVariant->product
            || $inventory->productVariant->product->status !== 'active'
            || $inventory->productVariant->product
                ->verification_status !== 'approved'
            || ! $inventory->productVariant->availability
            || $inventory->productVariant->visibility !== 'published'
            || $inventory->store?->status !== 'online'
        ) {
            throw ValidationException::withMessages([
                'product_variant_id' => [
                    'The selected product is not available.',
                ],
            ]);
        }

        return $inventory;
    }

    private function inventoryForItem(
        CartItem $item
    ): ?StoreProductVariant {
        return StoreProductVariant::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->where('store_id', $item->store_id)
            ->where('status', 'active')
            ->first();
    }

    private function validateQuantity(
        int $quantity,
        $product,
        int $stock
    ): void {
        $minimum = max(
            1,
            (int) $product->minimum_order_quantity
        );
        $step = max(
            1,
            (int) $product->quantity_step_size
        );
        $maximum = min(
            max($minimum, (int) $product->total_allowed_quantity),
            $stock
        );

        $validStep = ($quantity - $minimum) % $step === 0;

        if (
            $quantity < $minimum
            || $quantity > $maximum
            || ! $validStep
        ) {
            throw ValidationException::withMessages([
                'quantity' => [
                    "Quantity must be between {$minimum} and "
                    ."{$maximum}, using step {$step}.",
                ],
            ]);
        }
    }

    private function ownedItem(
        User $user,
        int $cartItemId
    ): CartItem {
        $item = CartItem::query()
            ->whereKey($cartItemId)
            ->whereHas(
                'cart',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
            ->first();

        abort_if(! $item, 404, 'Cart item not found.');

        return $item;
    }

    private function coordinates(
        User $user,
        array $options
    ): array {
        if (! empty($options['address_id'])) {
            $address = Address::query()
                ->where('user_id', $user->id)
                ->find($options['address_id']);

            if ($address) {
                return [
                    (float) $address->latitude,
                    (float) $address->longitude,
                ];
            }
        }

        if (
            isset($options['latitude'], $options['longitude'])
            && is_numeric($options['latitude'])
            && is_numeric($options['longitude'])
        ) {
            return [
                (float) $options['latitude'],
                (float) $options['longitude'],
            ];
        }

        return [null, null];
    }

    private function maxStoreDistance(
        Collection $payloads,
        ?float $latitude,
        ?float $longitude
    ): float {
        if ($latitude === null || $longitude === null) {
            return 0.0;
        }

        return (float) $payloads
            ->map(function (array $payload) use (
                $latitude,
                $longitude
            ): float {
                $storeId = $payload['store']['id'] ?? null;

                if (! $storeId) {
                    return 0.0;
                }

                $store = Store::query()->find($storeId);

                if (
                    ! $store
                    || ! is_numeric($store->latitude)
                    || ! is_numeric($store->longitude)
                ) {
                    return 0.0;
                }

                return $this->distance(
                    $latitude,
                    $longitude,
                    (float) $store->latitude,
                    (float) $store->longitude
                );
            })
            ->max();
    }

    private function estimatedDeliveryTime(
        Collection $payloads,
        ?DeliveryZone $zone,
        float $distanceKm,
        bool $rush
    ): int {
        $prep = (int) $payloads
            ->map(fn (array $item) =>
                $item['product']['estimated_delivery_time'] ?? 0
            )
            ->max();

        $perKm = $rush
            ? (int) (
                $zone?->rush_delivery_time_per_km
                ?? $zone?->delivery_time_per_km
                ?? 0
            )
            : (int) ($zone?->delivery_time_per_km ?? 0);

        return (int) ceil(
            $prep
            + ($distanceKm * $perKm)
            + (int) ($zone?->buffer_time ?? 0)
        );
    }

    private function distance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );
    }

    private function removedItem(
        CartItem $item,
        string $reason
    ): array {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'store_id' => $item->store_id,
            'quantity' => $item->quantity,
            'reason' => $reason,
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\PromoService.php" -Content @'
<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Promo;
use App\Models\PromoUserUsage;
use App\Models\User;

class PromoService
{
    public function validate(
        ?string $code,
        User $user,
        float $cartTotal,
        iterable $cartItems
    ): array {
        if (! $code) {
            return [
                'success' => true,
                'discount' => 0.0,
                'promo' => null,
                'message' => '',
            ];
        }

        $promo = Promo::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first();

        if (! $promo || ! $promo->isCurrentlyActive()) {
            return $this->failure('Invalid or expired promo code.');
        }

        if ($cartTotal < (float) $promo->min_order_total) {
            return $this->failure(
                'Minimum order total is not reached.',
                $promo
            );
        }

        if ($promo->max_usage_per_user !== null) {
            $usage = PromoUserUsage::query()
                ->where('promo_id', $promo->id)
                ->where('user_id', $user->id)
                ->value('usage_count') ?? 0;

            if ($usage >= $promo->max_usage_per_user) {
                return $this->failure(
                    'Promo usage limit reached.',
                    $promo
                );
            }
        }

        if (! $this->scopeMatches($promo, $cartItems)) {
            return $this->failure(
                'Promo is not applicable to this cart.',
                $promo
            );
        }

        $discount = $promo->discount_type === 'percentage'
            ? $cartTotal * ((float) $promo->discount_amount / 100)
            : (float) $promo->discount_amount;

        if ($promo->max_discount_value !== null) {
            $discount = min(
                $discount,
                (float) $promo->max_discount_value
            );
        }

        $discount = round(min($discount, $cartTotal), 2);

        return [
            'success' => true,
            'discount' => $discount,
            'promo' => $this->promoArray($promo),
            'message' => 'Promo applied successfully.',
        ];
    }

    public function available(User $user): array
    {
        return Promo::query()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (Promo $promo) use ($user): bool {
                if (! $promo->isCurrentlyActive()) {
                    return false;
                }

                if ($promo->max_usage_per_user === null) {
                    return true;
                }

                $usage = PromoUserUsage::query()
                    ->where('promo_id', $promo->id)
                    ->where('user_id', $user->id)
                    ->value('usage_count') ?? 0;

                return $usage < $promo->max_usage_per_user;
            })
            ->map(fn (Promo $promo) => $this->promoArray($promo))
            ->values()
            ->all();
    }

    private function scopeMatches(
        Promo $promo,
        iterable $cartItems
    ): bool {
        if ($promo->promo_mode === 'global') {
            return true;
        }

        foreach ($cartItems as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }

            if (
                $promo->promo_mode === 'store'
                && $item->store_id === (int) $promo->scope_id
            ) {
                return true;
            }

            if (
                $promo->promo_mode === 'product'
                && $item->product_id === (int) $promo->scope_id
            ) {
                return true;
            }

            if (
                $promo->promo_mode === 'category'
                && $item->product?->category_id
                    === (int) $promo->scope_id
            ) {
                return true;
            }
        }

        return false;
    }

    private function promoArray(Promo $promo): array
    {
        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'description' => $promo->description,
            'start_date' => $promo->start_date,
            'end_date' => $promo->end_date,
            'discount_type' => $promo->discount_type,
            'discount_amount' => $promo->discount_amount,
            'promo_mode' => $promo->promo_mode,
            'min_order_total' => $promo->min_order_total,
            'max_discount_value' => $promo->max_discount_value,
            'status' => $promo->isCurrentlyActive()
                ? 'active'
                : 'expired',
        ];
    }

    private function failure(
        string $message,
        ?Promo $promo = null
    ): array {
        return [
            'success' => false,
            'discount' => 0.0,
            'promo' => $promo ? $this->promoArray($promo) : null,
            'message' => $message,
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\AddressResource.php" -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'mobile' => $this->mobile,
            'address_type' => $this->address_type,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\ProductListResource.php" -Content @'
<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $user = $request->user('sanctum');

        $favorite = null;
        $itemCountInCart = 0;
        $isSaveForLater = false;

        if ($user) {
            $favoriteItems = WishlistItem::query()
                ->whereHas(
                    'wishlist',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_id', $product->id)
                ->with(['wishlist', 'variant', 'store'])
                ->get();

            if ($favoriteItems->isNotEmpty()) {
                $favorite = $favoriteItems
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'wishlist_id' => $item->wishlist_id,
                        'wishlist_title' => $item->wishlist?->title,
                        'variant_id' => $item->variant?->id,
                        'variant_name' => $item->variant?->title,
                        'store_id' => $item->store?->id,
                        'store_name' => $item->store?->name,
                    ])
                    ->values()
                    ->all();
            }

            $cartItems = CartItem::query()
                ->whereHas(
                    'cart',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_id', $product->id);

            $itemCountInCart = (clone $cartItems)
                ->where('save_for_later', false)
                ->sum('quantity');

            $isSaveForLater = (clone $cartItems)
                ->where('save_for_later', true)
                ->exists();
        }

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'seller_id' => $product->seller_id,
            'title' => $product->title,
            'slug' => $product->slug,
            'type' => $product->type,
            'short_description' => $product->short_description,
            'category' => $product->category?->slug,
            'brand' => $product->brand?->slug,
            'category_name' => $product->category?->title,
            'brand_name' => $product->brand?->title,
            'seller' => $product->seller?->owner?->name
                ?? $product->seller?->business_name
                ?? 'N/A',
            'indicator' => $product->indicator,
            'favorite' => $favorite,
            'estimated_delivery_time' => null,
            'base_prep_time' => (int) $product->base_prep_time,
            'ratings' => 0.0,
            'rating_count' => 0,
            'main_image' => $product->mainImageUrl(),
            'image_fit' => $product->image_fit,
            'item_count_in_cart' => (int) $itemCountInCart,
            'is_save_for_later' => $isSaveForLater,
            'additional_images' => $product->additionalImageUrls(),
            'minimum_order_quantity' => (int) (
                $product->minimum_order_quantity
            ),
            'quantity_step_size' => (int) $product->quantity_step_size,
            'total_allowed_quantity' => (int) (
                $product->total_allowed_quantity
            ),
            'is_returnable' => (float) $product->is_returnable,
            'is_attachment_required' => (float) (
                $product->is_attachment_required
            ),
            'attachment_mode' => $product->attachment_mode,
            'requires_otp' => (float) $product->requires_otp,
            'tags' => $product->tags ?? [],
            'warranty_period' => $product->warranty_period,
            'guarantee_period' => $product->guarantee_period,
            'made_in' => $product->made_in,
            'is_inclusive_tax' => (bool) $product->is_inclusive_tax,
            'video_type' => $product->video_type,
            'video_link' => $product->video_link,
            'status' => $product->status,
            'featured' => $product->featured ? '1' : '0',
            'badge' => $product->badge ? [
                'id' => $product->badge->id,
                'label' => $product->badge->label,
                'bg_color' => $product->badge->bg_color,
                'text_color' => $product->badge->text_color,
                'border_color' => $product->badge->border_color,
            ] : null,
            'metadata' => $product->metadata ?? [],
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'store_status' => $this->storeStatus($product),
            'variants' => ProductVariantResource::collection(
                $product->variants
            ),
            'attributes' => $this->formattedAttributes($product),
            'is_sponsored' => false,
            'campaign_id' => null,
            'visitor_key' => null,
        ];
    }

    protected function formattedAttributes(Product $product): array
    {
        $groups = [];

        foreach ($product->variantAttributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if (! $attribute || ! $value) {
                continue;
            }

            $slug = $attribute->slug;

            $groups[$slug] ??= [
                'name' => $attribute->title,
                'slug' => $slug,
                'swatche_type' => $attribute->swatche_type,
                'values' => [],
                'swatch_values' => [],
            ];

            if (! in_array(
                $value->title,
                $groups[$slug]['values'],
                true
            )) {
                $groups[$slug]['values'][] = $value->title;
                $groups[$slug]['swatch_values'][] = [
                    'value' => $value->title,
                    'swatch' => $value->swatchValue(),
                ];
            }
        }

        return array_values($groups);
    }

    protected function storeStatus(Product $product): array
    {
        $store = $product->variants
            ->first()
            ?->storeProductVariants
            ->first()
            ?->store;

        if (! $store) {
            return [];
        }

        return [
            'is_open' => $store->status === 'online',
            'current_slot' => null,
            'next_opening_time' => '',
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\ProductVariantResource.php" -Content @'
<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inventory = $this->storeProductVariants->first();

        $attributes = [];

        foreach ($this->attributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if ($attribute && $value) {
                $attributes[$attribute->slug] = $value->title;
            }
        }

        $cartItem = null;
        $user = $request->user('sanctum');

        if ($user && $inventory) {
            $cartItem = CartItem::query()
                ->whereHas(
                    'cart',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_variant_id', $this->id)
                ->where('store_id', $inventory->store_id)
                ->where('save_for_later', false)
                ->first();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->imageUrl(),
            'weight' => (float) ($this->weight ?? 0),
            'height' => (float) ($this->height ?? 0),
            'breadth' => (float) ($this->breadth ?? 0),
            'length' => (float) ($this->length ?? 0),
            'availability' => (bool) $this->availability,
            'cart_item' => [
                'exists' => $cartItem !== null,
                'cart_item_id' => $cartItem?->id,
            ],
            'barcode' => $this->barcode,
            'is_default' => (bool) $this->is_default,
            'price' => $inventory?->price,
            'special_price' => $inventory?->special_price,
            'store_id' => $inventory?->store_id,
            'store_slug' => $inventory?->store?->slug,
            'store_name' => $inventory?->store?->name,
            'stock' => $inventory?->stock,
            'sku' => $inventory?->sku,
            'attributes' => $attributes,
            'addon_groups' => [],
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\WishlistItemResource.php" -Content @'
<?php

namespace App\Http\Resources;

use App\Models\StoreProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inventory = StoreProductVariant::query()
            ->where('product_variant_id', $this->product_variant_id)
            ->where('store_id', $this->store_id)
            ->first();

        return [
            'id' => $this->id,
            'wishlist_id' => $this->wishlist_id,
            'product' => [
                'id' => $this->product?->id,
                'title' => $this->product?->title,
                'name' => $this->product?->title,
                'slug' => $this->product?->slug,
                'image' => $this->product?->mainImageUrl() ?? '',
                'short_description' =>
                    $this->product?->short_description,
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'title' => $this->variant?->title,
                'slug' => $this->variant?->slug,
                'sku' => $inventory?->sku,
                'image' => $this->variant?->imageUrl() ?? '',
                'price' => $inventory?->price,
                'special_price' => $inventory?->special_price,
                'store_id' => $this->store_id,
                'store_slug' => $this->store?->slug,
                'store_name' => $this->store?->name,
                'stock' => $inventory?->stock ?? 0,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'slug' => $this->store?->slug,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\WishlistResource.php" -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'items_count' => $this->items_count
                ?? $this->items->count(),
            'items' => WishlistItemResource::collection(
                $this->whenLoaded('items')
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\ProductApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogueQueryService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function __construct(
        protected CatalogueQueryService $catalogue
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'categories' => ['nullable', 'string'],
            'brands' => ['nullable', 'string'],
            'exclude_product' => ['nullable', 'string'],
            'sort' => ['nullable', 'string', 'max:50'],
            'store' => ['nullable', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:255'],
            'include_child_categories' => ['nullable'],
            'attribute_values' => ['nullable', 'string'],
            'recommended' => ['nullable'],
        ]);

        $validated['include_child_categories'] = filter_var(
            $validated['include_child_categories'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $paginator = $this->catalogue->products(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated,
            (int) ($validated['per_page'] ?? 15)
        );

        $categorySlugs = $this->csv($validated['categories'] ?? null);
        $brandSlugs = $this->csv($validated['brands'] ?? null);

        $categoryIds = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $brandIds = Brand::query()
            ->whereIn('slug', $brandSlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return ApiResponseType::sendJsonResponse(
            true,
            $paginator->total() > 0
                ? 'Products fetched successfully.'
                : 'Products not found.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'keywords' => array_values(array_filter([
                    $validated['search'] ?? null,
                ])),
                'category_ids' => $categoryIds,
                'brand_ids' => $brandIds,
                'data' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ]
        );
    }

    public function show(
        Request $request,
        string $slug
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $product = $this->catalogue->productBySlug(
            $slug,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if (! $product) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Product not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Product fetched successfully.',
            new ProductResource($product)
        );
    }

    public function storeWise(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'store_slug' => ['nullable', 'string', 'max:500'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $store = ! empty($validated['store_id'])
            ? Store::query()->find($validated['store_id'])
            : Store::query()
                ->where('slug', $validated['store_slug'] ?? '')
                ->first();

        if (! $store) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Store ID or slug is required.',
                [],
                422
            );
        }

        $query = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->whereHas(
                'variants.storeProductVariants',
                fn ($inventoryQuery) => $inventoryQuery
                    ->where('store_id', $store->id)
                    ->where('status', 'active')
                    ->where('stock', '>', 0)
            )
            ->with([
                'category.parent',
                'brand.scopeCategory',
                'seller.owner',
                'badge',
                'variantAttributes.attribute',
                'variantAttributes.attributeValue.attribute',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue.attribute',
                'variants.storeProductVariants' =>
                    fn ($inventoryQuery) => $inventoryQuery
                        ->where('store_id', $store->id)
                        ->where('status', 'active')
                        ->where('stock', '>', 0),
                'variants.storeProductVariants.store',
            ])
            ->orderBy('title');

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ]
        );
    }

    public function searchByKeywords(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'keywords' => ['required', 'string', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $groups = [];

        foreach (array_filter(
            array_map('trim', explode(',', $validated['keywords']))
        ) as $keyword) {
            $paginator = $this->catalogue->products(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                ['search' => $keyword],
                (int) ($validated['per_page'] ?? 10)
            );

            $groups[] = [
                'keyword' => $keyword,
                'total_products' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'products' => collect($paginator->items())
                    ->map(
                        fn ($product) =>
                            (new ProductListResource($product))
                                ->resolve($request)
                    )
                    ->values()
                    ->all(),
            ];
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched by keywords successfully.',
            $groups
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(
                    'title',
                    'like',
                    '%'.$search.'%'
                )
            )
            ->orderBy('title')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return ApiResponseType::sendJsonResponse(
            true,
            'Products fetched successfully.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => collect($paginator->items())
                    ->map(fn ($product) => [
                        'id' => $product->id,
                        'value' => $product->id,
                        'text' => $product->title,
                        'image' => $product->mainImageUrl(),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function csv(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value))
        ));
    }

}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\AddressApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15));

        $items = collect($paginator->items());

        if (! empty($validated['zone_id'])) {
            $zoneId = (int) $validated['zone_id'];
            $items = $items->filter(function (Address $address) use (
                $zoneId
            ): bool {
                $info = DeliveryZoneService::getZonesAtPoint(
                    (float) $address->latitude,
                    (float) $address->longitude
                );

                return (int) ($info['zone_id'] ?? 0) === $zoneId;
            })->values();
        }

        $data = $paginator->toArray();
        $data['data'] = $items
            ->map(
                fn (Address $address) =>
                    (new AddressResource($address))->resolve($request)
            )
            ->all();
        $data['total'] = count($data['data']);

        return ApiResponseType::sendJsonResponse(
            true,
            'Addresses fetched successfully.',
            $data
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);

        if (! DeliveryZoneService::existsAtPoint(
            (float) $validated['latitude'],
            (float) $validated['longitude']
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery is not available at this address.',
                [],
                422
            );
        }

        $address = DB::transaction(function () use (
            $request,
            $validated
        ): Address {
            if (! empty($validated['is_default'])) {
                Address::query()
                    ->where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            return Address::query()->create(array_merge(
                $validated,
                ['user_id' => $request->user()->id]
            ));
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Address created successfully.',
            new AddressResource($address),
            201
        );
    }

    public function show(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Address fetched successfully.',
            new AddressResource($address)
        );
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);
        $validated = $this->validateAddress($request, true);

        $latitude = (float) (
            $validated['latitude'] ?? $address->latitude
        );
        $longitude = (float) (
            $validated['longitude'] ?? $address->longitude
        );

        if (! DeliveryZoneService::existsAtPoint(
            $latitude,
            $longitude
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery is not available at this address.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $request,
            $address,
            $validated
        ): void {
            if (! empty($validated['is_default'])) {
                Address::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return ApiResponseType::sendJsonResponse(
            true,
            'Address updated successfully.',
            new AddressResource($address->fresh())
        );
    }

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        $address = $this->ownedAddress($request, $id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            Address::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->first()
                ?->update(['is_default' => true]);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Address deleted successfully.',
            []
        );
    }

    private function validateAddress(
        Request $request,
        bool $partial = false
    ): array {
        $prefix = $partial ? 'sometimes|' : '';

        return $request->validate([
            'address_line1' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'mobile' => [$partial ? 'sometimes' : 'required', 'string', 'max:32'],
            'address_type' => [$partial ? 'sometimes' : 'required', 'in:home,work,other'],
            'country' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'country_code' => [$partial ? 'sometimes' : 'required', 'string', 'max:10'],
            'latitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'longitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function ownedAddress(
        Request $request,
        string $id
    ): Address {
        $address = Address::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        abort_if(! $address, 404, 'Address not found.');

        return $address;
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\CartApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\CommerceService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartApiController extends Controller
{
    public function __construct(
        protected CommerceService $commerce
    ) {
    }

    public function getCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'promo_code' => ['nullable', 'string', 'max:100'],
            'rush_delivery' => ['nullable'],
            'use_wallet' => ['nullable'],
            'delivery_type' => ['nullable', 'in:delivery,pickup'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Cart fetched successfully.',
            $this->commerce->payload($request->user(), $validated)
        );
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_variant_id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = $this->commerce->add(
            $request->user(),
            $validated
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Item added to cart successfully.',
            ['cart_item_id' => $item->id],
            201
        );
    }

    public function updateCartItemQuantity(
        Request $request,
        int $cartItemId
    ): JsonResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = $this->commerce->update(
            $request->user(),
            $cartItemId,
            (int) $validated['quantity']
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Cart quantity updated successfully.',
            ['cart_item_id' => $item->id]
        );
    }

    public function removeFromCart(
        Request $request,
        int $cartItemId
    ): JsonResponse {
        $this->commerce->remove(
            $request->user(),
            $cartItemId
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Item removed from cart successfully.',
            []
        );
    }

    public function getSaveForLaterItems(
        Request $request
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Saved items fetched successfully.',
            $this->commerce->savedItems($request->user())
        );
    }

    public function saveForLater(
        Request $request,
        int $cartItemId
    ): JsonResponse {
        $item = $this->commerce->toggleSaveForLater(
            $request->user(),
            $cartItemId
        );

        return ApiResponseType::sendJsonResponse(
            true,
            $item->save_for_later
                ? 'Item saved for later.'
                : 'Item moved back to cart.',
            [
                'cart_item_id' => $item->id,
                'save_for_later' => $item->save_for_later,
            ]
        );
    }

    public function clearCart(Request $request): JsonResponse
    {
        $this->commerce->clear($request->user());

        return ApiResponseType::sendJsonResponse(
            true,
            'Cart cleared successfully.',
            []
        );
    }

    public function syncCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:100'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],
            'items.*.store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Cart synchronized.',
            $this->commerce->sync(
                $request->user(),
                $validated['items']
            )
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\PromoApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CommerceService;
use App\Services\PromoService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoApiController extends Controller
{
    public function __construct(
        protected PromoService $promos,
        protected CommerceService $commerce
    ) {
    }

    public function getUserAvailablePromos(
        Request $request
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Promos fetched successfully.',
            $this->promos->available($request->user())
        );
    }

    public function validatePromoCode(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'promo_code' => ['required', 'string', 'max:100'],
            'cart_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cart = $this->commerce->getOrCreateCart(
            $request->user()
        );

        $items = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('save_for_later', false)
            ->with('product')
            ->get();

        $cartPayload = $this->commerce->payload(
            $request->user(),
            []
        );

        $cartTotal = isset($validated['cart_amount'])
            ? (float) $validated['cart_amount']
            : (float) (
                $cartPayload['payment_summary']['items_total'] ?? 0
            );

        $result = $this->promos->validate(
            $validated['promo_code'],
            $request->user(),
            $cartTotal,
            $items
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            [
                'promo_code' => $validated['promo_code'],
                'discount' => number_format(
                    $result['discount'],
                    2,
                    '.',
                    ''
                ),
                'promo_details' => $result['promo'],
            ],
            $result['success'] ? 200 : 422
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\WishlistApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Http\Resources\WishlistResource;
use App\Models\ProductVariant;
use App\Models\StoreProductVariant;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WishlistApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->with([
                'items.product',
                'items.variant',
                'items.store',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15));

        $data = $paginator->toArray();
        $data['data'] = collect($paginator->items())
            ->map(
                fn (Wishlist $wishlist) =>
                    (new WishlistResource($wishlist))->resolve($request)
            )
            ->all();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlists fetched successfully.',
            $data
        );
    }

    public function getTitles(Request $request): JsonResponse
    {
        $data = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Wishlist $wishlist) => [
                'id' => $wishlist->id,
                'title' => $wishlist->title,
                'slug' => $wishlist->slug,
                'items_count' => $wishlist->items_count,
                'created_at' => $wishlist->created_at?->format(
                    'Y-m-d H:i:s'
                ),
            ])
            ->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist titles fetched successfully.',
            $data
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wishlist_title' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => [
                'required_with:product_id',
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'store_id' => [
                'required_with:product_id',
                'nullable',
                'integer',
                'exists:stores,id',
            ],
        ]);

        $result = DB::transaction(function () use (
            $request,
            $validated
        ): array {
            $title = $validated['wishlist_title'] ?? 'Favorite';

            $wishlist = Wishlist::query()->firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'title' => $title,
                ],
                ['is_default' => $title === 'Favorite']
            );

            if (empty($validated['product_id'])) {
                return [
                    'message' => 'Wishlist created successfully.',
                    'data' => new WishlistResource(
                        $wishlist->loadCount('items')
                    ),
                ];
            }

            $variant = ProductVariant::query()
                ->where('product_id', $validated['product_id'])
                ->findOrFail($validated['product_variant_id']);

            StoreProductVariant::query()
                ->where('product_variant_id', $variant->id)
                ->where('store_id', $validated['store_id'])
                ->where('status', 'active')
                ->firstOrFail();

            $item = WishlistItem::query()->firstOrCreate([
                'wishlist_id' => $wishlist->id,
                'product_id' => $validated['product_id'],
                'product_variant_id' => $variant->id,
                'store_id' => $validated['store_id'],
            ]);

            return [
                'message' => $item->wasRecentlyCreated
                    ? 'Item added to wishlist.'
                    : 'Item already exists in wishlist.',
                'data' => new WishlistItemResource(
                    $item->load(['product', 'variant', 'store'])
                ),
            ];
        });

        return ApiResponseType::sendJsonResponse(
            true,
            $result['message'],
            $result['data']
        );
    }

    public function createWishlist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $exists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('title', $validated['title'])
            ->exists();

        if ($exists) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Wishlist already exists.',
                [],
                422
            );
        }

        $wishlist = Wishlist::query()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist created successfully.',
            new WishlistResource($wishlist),
            201
        );
    }

    public function show(
        Request $request,
        string $id
    ): JsonResponse {
        $wishlist = $this->ownedWishlist($request, $id)
            ->loadCount('items')
            ->load([
                'items.product',
                'items.variant',
                'items.store',
            ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist fetched successfully.',
            new WishlistResource($wishlist)
        );
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $wishlist = $this->ownedWishlist($request, $id);
        $wishlist->update(['title' => $validated['title']]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist updated successfully.',
            new WishlistResource(
                $wishlist->fresh()->loadCount('items')
            )
        );
    }

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        $this->ownedWishlist($request, $id)->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist deleted successfully.',
            []
        );
    }

    public function removeItem(
        Request $request,
        string $itemId
    ): JsonResponse {
        $item = WishlistItem::query()
            ->whereHas(
                'wishlist',
                fn ($query) => $query->where(
                    'user_id',
                    $request->user()->id
                )
            )
            ->find($itemId);

        abort_if(! $item, 404, 'Wishlist item not found.');
        $item->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist item removed successfully.',
            []
        );
    }

    public function moveItem(
        Request $request,
        string $itemId
    ): JsonResponse {
        $validated = $request->validate([
            'target_wishlist_id' => [
                'required',
                'integer',
                'exists:wishlists,id',
            ],
        ]);

        $item = WishlistItem::query()
            ->whereHas(
                'wishlist',
                fn ($query) => $query->where(
                    'user_id',
                    $request->user()->id
                )
            )
            ->find($itemId);

        abort_if(! $item, 404, 'Wishlist item not found.');

        $target = $this->ownedWishlist(
            $request,
            (string) $validated['target_wishlist_id']
        );

        $duplicate = WishlistItem::query()
            ->where('wishlist_id', $target->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('store_id', $item->store_id)
            ->first();

        if ($duplicate) {
            $item->delete();
            $item = $duplicate;
        } else {
            $item->update(['wishlist_id' => $target->id]);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist item moved successfully.',
            new WishlistItemResource(
                $item->fresh(['product', 'variant', 'store'])
            )
        );
    }

    private function ownedWishlist(
        Request $request,
        string $id
    ): Wishlist {
        $wishlist = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        abort_if(! $wishlist, 404, 'Wishlist not found.');

        return $wishlist;
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\routes\commerce.php" -Content @'
<?php

use App\Http\Controllers\Api\User\AddressApiController;
use App\Http\Controllers\Api\User\CartApiController;
use App\Http\Controllers\Api\User\PromoApiController;
use App\Http\Controllers\Api\User\WishlistApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('user')
    ->group(function (): void {
        Route::apiResource(
            'addresses',
            AddressApiController::class
        );

        Route::prefix('wishlists')->group(function (): void {
            Route::get('/', [WishlistApiController::class, 'index']);
            Route::get(
                '/titles',
                [WishlistApiController::class, 'getTitles']
            );
            Route::post(
                '/',
                [WishlistApiController::class, 'store']
            );
            Route::post(
                '/create',
                [WishlistApiController::class, 'createWishlist']
            );
            Route::delete(
                '/items/{itemId}',
                [WishlistApiController::class, 'removeItem']
            );
            Route::put(
                '/items/{itemId}/move',
                [WishlistApiController::class, 'moveItem']
            );
            Route::get(
                '/{id}',
                [WishlistApiController::class, 'show']
            );
            Route::put(
                '/{id}',
                [WishlistApiController::class, 'update']
            );
            Route::delete(
                '/{id}',
                [WishlistApiController::class, 'destroy']
            );
        });

        Route::prefix('cart')->group(function (): void {
            Route::get('/', [CartApiController::class, 'getCart']);
            Route::post(
                '/add',
                [CartApiController::class, 'addToCart']
            );
            Route::get(
                '/item/save-for-later',
                [CartApiController::class, 'getSaveForLaterItems']
            );
            Route::post(
                '/item/save-for-later/{cartItemId}',
                [CartApiController::class, 'saveForLater']
            );
            Route::post(
                '/item/{cartItemId}',
                [CartApiController::class, 'updateCartItemQuantity']
            );
            Route::delete(
                '/item/{cartItemId}',
                [CartApiController::class, 'removeFromCart']
            );
            Route::get(
                '/clear-cart',
                [CartApiController::class, 'clearCart']
            );
            Route::post(
                '/sync',
                [CartApiController::class, 'syncCart']
            );
        });

        Route::prefix('promos')->group(function (): void {
            Route::get(
                '/available',
                [PromoApiController::class, 'getUserAvailablePromos']
            );
            Route::get(
                '/validate',
                [PromoApiController::class, 'validatePromoCode']
            );
        });
    });
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\CommerceSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        Promo::query()->updateOrCreate(
            ['code' => 'FAST10'],
            [
                'description' => '10% development promo.',
                'start_date' => now()->subDay(),
                'end_date' => now()->addYear(),
                'discount_type' => 'percentage',
                'discount_amount' => 10,
                'promo_mode' => 'global',
                'usage_count' => 0,
                'individual_use' => false,
                'max_total_usage' => 10000,
                'max_usage_per_user' => 10,
                'min_order_total' => 10,
                'max_discount_value' => 100,
                'status' => 'active',
            ]
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\tests\Feature\Api\CommerceCoreApiTest.php" -Content @'
<?php

namespace Tests\Feature\Api;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Wishlist;
use App\Models\User;
use Database\Seeders\CatalogueInventorySeeder;
use Database\Seeders\CommerceSeeder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceCoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected ProductVariant $variant;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->seed(CatalogueInventorySeeder::class);
        $this->seed(CommerceSeeder::class);

        $this->user = User::query()->create([
            'name' => 'Commerce Customer',
            'email' => 'commerce@example.test',
            'mobile' => '01718888888',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => 'platform',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ]);

        $this->user->syncRoles([
            DefaultSystemRolesEnum::CUSTOMER->value,
        ]);

        \App\Models\Wallet::query()->create([
            'user_id' => $this->user->id,
            'type' => 'customer',
            'balance' => 0,
            'blocked_balance' => 0,
            'currency_code' => 'BDT',
        ]);

        $this->product = Product::query()->firstOrFail();
        $this->variant = ProductVariant::query()->firstOrFail();
        $this->store = Store::query()->firstOrFail();

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_list_update_and_delete_address(): void
    {
        $created = $this->postJson('/api/user/addresses', [
            'address_line1' => 'Dhanmondi',
            'address_line2' => 'Road 1',
            'city' => 'Dhaka',
            'landmark' => 'Lake',
            'state' => 'Dhaka',
            'zipcode' => '1205',
            'mobile' => '01710000000',
            'address_type' => 'home',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.city', 'Dhaka');

        $id = $created->json('data.id');

        $this->getJson('/api/user/addresses')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->putJson('/api/user/addresses/'.$id, [
            'address_type' => 'work',
        ])
            ->assertOk()
            ->assertJsonPath('data.address_type', 'work');

        $this->deleteJson('/api/user/addresses/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $id]);
    }

    public function test_outside_zone_address_is_rejected(): void
    {
        $this->postJson('/api/user/addresses', [
            'address_line1' => 'Outside',
            'city' => 'Sylhet',
            'mobile' => '01710000001',
            'address_type' => 'home',
            'country' => 'Bangladesh',
            'country_code' => '+880',
            'latitude' => 24.9000,
            'longitude' => 91.9000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_wishlist_crud_and_item_move_work(): void
    {
        $first = $this->postJson('/api/user/wishlists/create', [
            'title' => 'Medicine',
        ])->assertCreated();

        $second = $this->postJson('/api/user/wishlists/create', [
            'title' => 'Monthly',
        ])->assertCreated();

        $firstId = $first->json('data.id');
        $secondId = $second->json('data.id');

        $item = $this->postJson('/api/user/wishlists', [
            'wishlist_title' => 'Medicine',
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $itemId = $item->json('data.id');

        $this->putJson(
            '/api/user/wishlists/items/'.$itemId.'/move',
            ['target_wishlist_id' => $secondId]
        )
            ->assertOk()
            ->assertJsonPath('data.wishlist_id', $secondId);

        $this->deleteJson('/api/user/wishlists/'.$firstId)
            ->assertOk();

        $this->getJson('/api/user/wishlists')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_cart_add_update_save_and_remove_work(): void
    {
        $added = $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ])->assertCreated();

        $cartItemId = $added->json('data.cart_item_id');

        $this->getJson(
            '/api/user/cart?latitude=23.8103&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath(
                'data.payment_summary.items_total',
                '36.00'
            );

        $this->postJson('/api/user/cart/item/'.$cartItemId, [
            'quantity' => 3,
        ])->assertOk();

        $this->postJson(
            '/api/user/cart/item/save-for-later/'.$cartItemId
        )
            ->assertOk()
            ->assertJsonPath('data.save_for_later', true);

        $this->getJson('/api/user/cart/item/save-for-later')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/user/cart/item/'.$cartItemId)
            ->assertOk();

        $this->assertDatabaseMissing(
            'cart_items',
            ['id' => $cartItemId]
        );
    }

    public function test_cart_quantity_cannot_exceed_product_limit(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 21,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_cart_sync_handles_multiple_items(): void
    {
        $this->postJson('/api/user/cart/sync', [
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'store_id' => $this->store->id,
                    'quantity' => 1,
                ],
                [
                    'product_variant_id' => $this->variant->id,
                    'store_id' => $this->store->id,
                    'quantity' => 2,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.synced', 2);

        $this->assertDatabaseHas('cart_items', [
            'quantity' => 3,
        ]);
    }

    public function test_promo_is_available_and_applied_to_cart(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/user/promos/available')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'FAST10');

        $this->getJson(
            '/api/user/promos/validate'
            .'?promo_code=FAST10'
        )
            ->assertOk()
            ->assertJsonPath('data.discount', '1.80');

        $this->getJson(
            '/api/user/cart'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&promo_code=FAST10'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.payment_summary.promo_discount',
                '1.80'
            );
    }

    public function test_authenticated_product_payload_contains_cart_and_wishlist_state(): void
    {
        $this->postJson('/api/user/cart/add', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/user/wishlists', [
            'wishlist_title' => 'Favorite',
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
        ])->assertOk();

        $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.data.0.item_count_in_cart',
                2
            )
            ->assertJsonPath(
                'data.data.0.variants.0.cart_item.exists',
                true
            );

        $this->assertNotEmpty(
            $this->getJson(
                '/api/delivery-zone/products'
                .'?latitude=23.8103'
                .'&longitude=90.4125'
            )->json('data.data.0.favorite')
        );
    }

    public function test_product_filter_response_returns_selected_ids(): void
    {
        $response = $this->getJson(
            '/api/delivery-zone/products'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
            .'&categories=pharmacy'
            .'&brands=fastsheba-generic'
            .'&include_child_categories=1'
        )->assertOk();

        $this->assertNotEmpty(
            $response->json('data.category_ids')
        );
        $this->assertNotEmpty(
            $response->json('data.brand_ids')
        );
    }
}
'@

$apiRoutesPath = "$BackendPath\routes\api.php"
$apiRoutes = [System.IO.File]::ReadAllText($apiRoutesPath)

$commerceInclude = "require __DIR__.'/commerce.php';"

if (-not $apiRoutes.Contains($commerceInclude)) {
    $apiRoutes = $apiRoutes.TrimEnd()
        + "`n`n"
        + $commerceInclude
        + "`n"

    Write-Utf8NoBom -Path $apiRoutesPath -Content $apiRoutes
}

Write-Host ""
Write-Host "Phase 4 Commerce Core batch installed." `
    -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" `
    -ForegroundColor Cyan
Write-Host ""
Write-Host "Included:"
Write-Host "  Address CRUD with delivery-zone validation"
Write-Host "  Wishlist CRUD and item movement"
Write-Host "  Cart add/update/remove/save/sync"
Write-Host "  Delivery, promo and wallet price summary"
Write-Host "  Authenticated product cart/wishlist state"
Write-Host "  Selected category/brand IDs in product response"
Write-Host "  Commerce demo promo and feature tests"
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate"
Write-Host "  herd php artisan db:seed --class=CommerceSeeder"
Write-Host "  herd php artisan migrate --env=testing"
Write-Host "  herd php artisan test --filter=CommerceCoreApiTest"
Write-Host "  herd php artisan test"
