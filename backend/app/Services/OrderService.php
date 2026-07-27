<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\OrderStatusLog;
use App\Models\PromoUserUsage;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderService
{
    public function __construct(
        protected CommerceService $commerce,
        protected InventoryService $inventoryService,
        protected PaymentService $paymentService,
        protected WalletService $walletService
    ) {}

    public function create(User $user, array $data): Order
    {
        if (! $user->isFullyVerified()) {
            throw ValidationException::withMessages([
                'verification' => 'Email and mobile verification are required before checkout.',
            ]);
        }

        return DB::transaction(function () use ($user, $data): Order {
            $deliveryType = $data['delivery_type'];
            $address = $deliveryType === 'delivery'
                ? Address::query()->where('user_id', $user->id)->findOrFail($data['address_id'])
                : null;

            $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $cart) { throw ValidationException::withMessages(['cart' => 'Cart is empty.']); }

            $cartItems = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('save_for_later', false)
                ->with(['product','variant','store.seller'])
                ->lockForUpdate()
                ->get();
            if ($cartItems->isEmpty()) { throw ValidationException::withMessages(['cart' => 'Cart is empty.']); }

            if ($deliveryType === 'pickup') {
                if ($data['payment_type'] === 'cod') {
                    throw ValidationException::withMessages(['payment_type' => 'Pickup orders cannot use Cash on Delivery.']);
                }
                $unsupported = $cartItems->filter(fn (CartItem $item) => ! (bool) $item->store?->allows_pickup);
                if ($unsupported->isNotEmpty()) {
                    throw ValidationException::withMessages(['delivery_type' => 'One or more stores do not support pickup.']);
                }
            }

            $options = [
                'address_id' => $address?->id,
                'latitude' => $deliveryType === 'pickup' ? $cartItems->first()?->store?->latitude : null,
                'longitude' => $deliveryType === 'pickup' ? $cartItems->first()?->store?->longitude : null,
                'promo_code' => $data['promo_code'] ?? null,
                'use_wallet' => ($data['payment_type'] === 'wallet') || (bool) ($data['use_wallet'] ?? false),
                'rush_delivery' => (bool) ($data['rush_delivery'] ?? false),
            ];
            $cartPayload = $this->commerce->payload($user, $options);
            if (($cartPayload['items_count'] ?? 0) < 1 || ($cartPayload['removed_count'] ?? 0) > 0) {
                throw ValidationException::withMessages(['cart' => 'Cart contains unavailable or out-of-stock items.']);
            }

            $summary = $cartPayload['payment_summary'];
            if (! empty($summary['promo_error'])) {
                throw ValidationException::withMessages(['promo_code' => $summary['promo_error']]);
            }

            $payment = $this->paymentService->validate($data, (float) $summary['payable_amount']);
            $zoneId = $summary['delivery_zone']['id'] ?? null;
            if ($deliveryType === 'delivery' && ! $zoneId) {
                throw ValidationException::withMessages(['address_id' => 'Delivery is not available at this address.']);
            }

            $order = Order::query()->create([
                'user_id' => $user->id,'address_id' => $address?->id,'delivery_zone_id' => $zoneId,
                'status' => 'awaiting_store_response','payment_method' => $data['payment_type'],
                'payment_status' => $payment['status'],'delivery_type' => $deliveryType,
                'is_rush_order' => (bool) ($summary['is_rush_delivery'] ?? false),
                'promo_id' => $summary['promo_applied']['id'] ?? null,
                'promo_code' => $summary['promo_applied']['code'] ?? null,
                'promo_discount' => (float) $summary['promo_discount'],
                'wallet_balance' => (float) $summary['wallet_amount_used'],
                'subtotal' => (float) $summary['items_total'],
                'delivery_charge' => (float) $summary['delivery_charges'],
                'handling_charges' => (float) $summary['handling_charges'],
                'per_store_drop_off_fee' => (float) $summary['per_store_drop_off_fee'],
                'total_payable' => (float) $summary['payable_amount'],
                'final_total' => round((float) $summary['items_total'] - (float) $summary['promo_discount'] + (float) $summary['delivery_charges'] + (float) $summary['handling_charges'], 2),
                'email' => $user->email,'billing_name' => $user->name,'billing_phone' => $user->mobile,
                'shipping_name' => $user->name,
                'shipping_address_1' => $address?->address_line1 ?? $cartItems->first()?->store?->address,
                'shipping_address_2' => $address?->address_line2,
                'shipping_landmark' => $address?->landmark,
                'shipping_city' => $address?->city ?? $cartItems->first()?->store?->city,
                'shipping_state' => $address?->state,
                'shipping_zip' => $address?->zipcode,
                'shipping_country' => $address?->country ?? $cartItems->first()?->store?->country,
                'shipping_phone' => $address?->mobile ?? $user->mobile,
                'order_note' => $data['order_note'] ?? null,
                'estimated_delivery_time' => $summary['estimated_delivery_time'] ?? null,
                'paid_at' => $payment['status'] === 'completed' ? now() : null,
                'metadata' => ['gift_card' => $data['gift_card'] ?? null],
            ]);

            foreach ($cartItems->groupBy('store_id') as $storeId => $storeItems) {
                $store = $storeItems->first()->store;
                $seller = $store?->seller;
                if (! $store || ! $seller) { throw new RuntimeException('Store or seller is unavailable.'); }
                $storeSubtotal = 0.0;
                foreach ($storeItems as $item) {
                    $inventory = StoreProductVariant::query()
                        ->where('store_id', $storeId)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()->firstOrFail();
                    if ($inventory->stock < $item->quantity) {
                        throw ValidationException::withMessages(['cart' => 'Insufficient stock for '.$item->product?->title.'.']);
                    }
                    $storeSubtotal += $inventory->effectivePrice() * $item->quantity;
                }
                $commissionRate = (float) ($seller->commission_rate ?? 0);
                $commission = round($storeSubtotal * $commissionRate / 100, 2);
                $sellerOrder = SellerOrder::query()->create([
                    'order_id' => $order->id,'seller_id' => $seller->id,'store_id' => $store->id,
                    'status' => 'awaiting_store_response','delivery_type' => $deliveryType,
                    'subtotal' => $storeSubtotal,'commission_amount' => $commission,
                    'seller_earnings' => round($storeSubtotal - $commission, 2),
                ]);

                foreach ($storeItems as $item) {
                    $inventory = StoreProductVariant::query()
                        ->where('store_id', $storeId)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()->firstOrFail();
                    $unit = $inventory->effectivePrice();
                    $line = round($unit * $item->quantity, 2);
                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,'seller_order_id' => $sellerOrder->id,
                        'product_id' => $item->product_id,'product_variant_id' => $item->product_variant_id,
                        'store_id' => $store->id,'product_title' => $item->product?->title ?? 'Product',
                        'variant_title' => $item->variant?->title,'sku' => $inventory->sku,
                        'price' => $inventory->price,'special_price' => $inventory->special_price,
                        'quantity' => $item->quantity,'subtotal' => $line,'status' => 'awaiting_store_response',
                        'is_returnable' => (bool) ($item->product?->is_returnable ?? false),
                        'returnable_until' => ($item->product?->is_returnable && $item->product?->returnable_days)
                            ? now()->addDays((int) $item->product->returnable_days) : null,
                    ]);
                    SellerOrderItem::query()->create([
                        'seller_order_id' => $sellerOrder->id,'order_item_id' => $orderItem->id,
                        'product_id' => $item->product_id,'product_variant_id' => $item->product_variant_id,
                        'store_id' => $store->id,'price' => $unit,'quantity' => $item->quantity,'subtotal' => $line,
                    ]);
                    $this->inventoryService->changeStock(
                        $inventory,'remove',$item->quantity,'Order placed',$user,'order_item',$orderItem->id
                    );
                }
            }

            if ((float) $summary['wallet_amount_used'] > 0) {
                $this->walletService->debit($user, (float) $summary['wallet_amount_used'], 'order', $order->id, 'Wallet payment for '.$order->slug);
            }

            OrderPaymentTransaction::query()->create([
                'order_id' => $order->id,'user_id' => $user->id,
                'transaction_id' => $payment['transaction_id'],'amount' => (float) $summary['payable_amount'],
                'currency' => 'BDT','payment_method' => $data['payment_type'],
                'payment_status' => $payment['status'],'message' => 'Order payment initialized.',
                'payment_details' => $payment['details'],
            ]);

            if (! empty($summary['promo_applied']['id'])) {
                $usage = PromoUserUsage::query()->firstOrCreate(
                    ['promo_id' => $summary['promo_applied']['id'], 'user_id' => $user->id],
                    ['usage_count' => 0]
                );
                $usage->increment('usage_count');
                $usage->promo()->increment('usage_count');
            }

            OrderStatusLog::query()->create([
                'order_id' => $order->id,'to_status' => 'awaiting_store_response',
                'changed_by' => $user->id,'actor_type' => 'customer','note' => 'Order created.',
            ]);
            CartItem::query()->where('cart_id', $cart->id)->where('save_for_later', false)->delete();

            return $this->loadOrder($order);
        });
    }

    public function userOrders(User $user, int $perPage, array $filters): LengthAwarePaginator
    {
        return Order::query()->where('user_id', $user->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['order_type'] ?? null, fn ($q, $type) => $q->where('delivery_type', $type))
            ->with(['items.store','items.product','items.variant'])
            ->latest()->paginate($perPage);
    }

    public function userOrder(User $user, string $slug): Order
    {
        $order = Order::query()->where('user_id', $user->id)->where('slug', $slug)->firstOrFail();
        return $this->loadOrder($order);
    }

    public function cancelItem(User $user, int $itemId): Order
    {
        return DB::transaction(function () use ($user, $itemId): Order {
            $item = OrderItem::query()->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
                ->lockForUpdate()->findOrFail($itemId);
            if (! in_array($item->status, ['awaiting_store_response','accepted_by_seller'], true)) {
                throw ValidationException::withMessages(['status' => 'This item can no longer be cancelled.']);
            }
            $from = $item->status;
            $inventory = StoreProductVariant::query()
                ->where('store_id', $item->store_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->firstOrFail();
            $this->inventoryService->changeStock($inventory,'add',$item->quantity,'Customer cancellation',$user,'order_item',$item->id);
            $item->update(['status' => 'cancelled','cancelled_at' => now(),'cancellation_reason' => 'Cancelled by customer']);
            OrderStatusLog::query()->create([
                'order_id' => $item->order_id,'order_item_id' => $item->id,'from_status' => $from,
                'to_status' => 'cancelled','changed_by' => $user->id,'actor_type' => 'customer','note' => 'Cancelled by customer.',
            ]);
            $order = $item->order()->lockForUpdate()->firstOrFail();
            $this->recalculateStatuses($order);
            return $this->loadOrder($order->fresh());
        });
    }

    public function reorder(User $user, int $orderId): array
    {
        $order = Order::query()->where('user_id', $user->id)->with('items')->findOrFail($orderId);
        $result = ['added' => 0, 'failed' => []];
        foreach ($order->items as $item) {
            if (! $item->product_variant_id) { $result['failed'][] = ['item_id' => $item->id,'message' => 'Variant unavailable.']; continue; }
            try {
                $this->commerce->add($user, [
                    'product_variant_id' => $item->product_variant_id,
                    'store_id' => $item->store_id,
                    'quantity' => $item->quantity,
                ]);
                $result['added']++;
            } catch (\Throwable $e) {
                $result['failed'][] = ['item_id' => $item->id,'message' => $e->getMessage()];
            }
        }
        return $result;
    }

    public function sellerOrders(Seller $seller, int $perPage, ?string $status): LengthAwarePaginator
    {
        return SellerOrder::query()->where('seller_id', $seller->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['order.user','store','items.product','items.variant'])
            ->latest()->paginate($perPage);
    }

    public function sellerOrder(Seller $seller, int $id): SellerOrder
    {
        return SellerOrder::query()->where('seller_id', $seller->id)
            ->with(['order.user','order.deliveryZone','store','items.product','items.variant'])
            ->findOrFail($id);
    }

    public function updateSellerItem(User $actor, Seller $seller, int $itemId, string $status, ?string $reason): OrderItem
    {
        return DB::transaction(function () use ($actor, $seller, $itemId, $status, $reason): OrderItem {
            $item = OrderItem::query()->whereHas('sellerOrder', fn ($q) => $q->where('seller_id', $seller->id))
                ->lockForUpdate()->findOrFail($itemId);
            $allowed = [
                'awaiting_store_response' => ['accepted_by_seller','rejected_by_seller'],
                'accepted_by_seller' => ['preparing','rejected_by_seller'],
                'preparing' => ['ready_for_pickup'],
            ];
            if (! in_array($status, $allowed[$item->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid order status transition.']);
            }
            $from = $item->status;
            if ($status === 'rejected_by_seller') {
                $inventory = StoreProductVariant::query()
                    ->where('store_id', $item->store_id)
                    ->where('product_variant_id', $item->product_variant_id)->firstOrFail();
                $this->inventoryService->changeStock($inventory,'add',$item->quantity,'Seller rejection',$actor,'order_item',$item->id);
                $item->cancellation_reason = $reason ?: 'Rejected by seller';
                $item->cancelled_at = now();
            }
            $item->status = $status;
            $item->save();
            OrderStatusLog::query()->create([
                'order_id' => $item->order_id,'order_item_id' => $item->id,'from_status' => $from,
                'to_status' => $status,'changed_by' => $actor->id,'actor_type' => 'seller','note' => $reason,
            ]);
            $sellerOrder = $item->sellerOrder()->lockForUpdate()->firstOrFail();
            $statuses = $sellerOrder->items()->pluck('status')->all();
            $sellerOrder->status = $this->aggregateStatus($statuses);
            if ($status === 'accepted_by_seller') { $sellerOrder->accepted_at ??= now(); }
            if ($status === 'ready_for_pickup') { $sellerOrder->ready_for_pickup_at = now(); }
            $sellerOrder->save();
            $order = $item->order()->lockForUpdate()->firstOrFail();
            $this->recalculateStatuses($order);
            return $item->fresh(['order','sellerOrder','product','variant','store']);
        });
    }

    private function recalculateStatuses(Order $order): void
    {
        $statuses = $order->items()->pluck('status')->all();
        $order->status = $this->aggregateStatus($statuses);
        if ($order->status === 'cancelled') { $order->cancelled_at = now(); }
        $order->save();
    }

    private function aggregateStatus(array $statuses): string
    {
        $unique = array_values(array_unique($statuses));
        if ($unique === ['cancelled'] || count(array_filter($statuses, fn ($s) => in_array($s, ['cancelled','rejected_by_seller'], true))) === count($statuses)) { return 'cancelled'; }
        if (in_array('ready_for_pickup', $statuses, true)) { return 'ready_for_pickup'; }
        if (in_array('preparing', $statuses, true)) { return 'preparing'; }
        if (in_array('accepted_by_seller', $statuses, true)) { return 'accepted_by_seller'; }
        if (in_array('rejected_by_seller', $statuses, true)) { return 'partially_accepted'; }
        return 'awaiting_store_response';
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load([
            'deliveryZone','items.product','items.variant','items.store.seller.owner',
            'sellerOrders.store','paymentTransactions','statusLogs',
        ]);
    }
}
