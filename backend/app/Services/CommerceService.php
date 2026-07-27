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