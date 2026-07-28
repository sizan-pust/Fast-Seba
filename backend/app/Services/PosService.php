<?php

namespace App\Services;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\OrderStatusLog;
use App\Models\PosCustomerDisplay;
use App\Models\PosParkedSale;
use App\Models\PosRefund;
use App\Models\PosRefundLine;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PosService
{
    public function __construct(
        protected InventoryService $inventory,
        protected WalletService $customerWallets,
        protected TaxCollectionAddonService $catalogue,
        protected SubscriptionService $subscriptions
    ) {
    }

    public function sellerFor(User $user): Seller
    {
        $seller = Seller::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $eligibility = $this->subscriptions->eligibility(
            $seller,
            'pos_access',
            0
        );

        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'subscription' => $eligibility['reason'],
            ]);
        }

        return $seller;
    }

    public function searchCustomers(
        string $search,
        int $limit = 20
    ): Collection {
        return User::query()
            ->where('access_panel', GuardNameEnum::WEB->value)
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%');
            })
            ->limit($limit)
            ->get(['id', 'name', 'email', 'mobile']);
    }

    public function quickRegisterCustomer(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'],
                'password' => Hash::make(
                    $data['password'] ?? Str::random(24)
                ),
                'status' => 'active',
                'access_panel' => GuardNameEnum::WEB->value,
                'logged_in_type' => 'pos',
                'country' => 'Bangladesh',
                'iso_2' => 'BD',
                'country_code' => '+880',
                'mobile_verified_at' => now(),
            ]);

            $user->syncRoles([
                DefaultSystemRolesEnum::CUSTOMER->value,
            ]);

            $this->customerWallets->customerWallet($user);

            return $user;
        });
    }

    public function searchProducts(
        Seller $seller,
        int $storeId,
        ?string $search,
        ?string $barcode,
        int $limit = 30
    ): Collection {
        Store::query()
            ->where('seller_id', $seller->id)
            ->where('pos_enabled', true)
            ->findOrFail($storeId);

        return StoreProductVariant::query()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->whereHas('productVariant.product', function ($query) use ($seller): void {
                $query->where('seller_id', $seller->id)
                    ->where('status', 'active')
                    ->where('verification_status', 'approved');
            })
            ->when($barcode, function ($query) use ($barcode): void {
                $query->whereHas(
                    'productVariant',
                    fn ($variantQuery) =>
                        $variantQuery->where('barcode', $barcode)
                );
            })
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('sku', 'like', '%'.$search.'%')
                        ->orWhereHas(
                            'productVariant',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where('title', 'like', '%'.$search.'%')
                                    ->orWhere('barcode', 'like', '%'.$search.'%')
                                    ->orWhereHas(
                                        'product',
                                        fn ($productQuery) =>
                                            $productQuery->where(
                                                'title',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                    )
                        );
                });
            })
            ->with(['productVariant.product', 'store'])
            ->limit($limit)
            ->get()
            ->map(function (StoreProductVariant $inventory): array {
                $variant = $inventory->productVariant;
                $product = $variant->product;

                return [
                    'inventory_id' => $inventory->id,
                    'product_id' => $product->id,
                    'product_title' => $product->title,
                    'variant_id' => $variant->id,
                    'variant_title' => $variant->title,
                    'barcode' => $variant->barcode,
                    'sku' => $inventory->sku,
                    'price' => (float) $inventory->price,
                    'special_price' =>
                        $inventory->special_price === null
                            ? null
                            : (float) $inventory->special_price,
                    'effective_price' => $inventory->effectivePrice(),
                    'stock' => (int) $inventory->stock,
                    'addons' => $this->catalogue->variantAddonMatrix(
                        $inventory->store_id,
                        $variant->id
                    ),
                ];
            });
    }

    public function createOrder(
        Seller $seller,
        User $operator,
        array $data
    ): Order {
        return DB::transaction(function () use (
            $seller,
            $operator,
            $data
        ): Order {
            $store = Store::query()
                ->where('seller_id', $seller->id)
                ->where('pos_enabled', true)
                ->lockForUpdate()
                ->findOrFail($data['store_id']);

            $customer = User::query()->findOrFail($data['customer_id']);

            $calculatedItems = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            $defaultTaxClass = \App\Models\TaxClass::query()
                ->where('is_default', true)
                ->where('status', 'active')
                ->with('rates')
                ->first();

            foreach ($data['items'] as $row) {
                $inventory = StoreProductVariant::query()
                    ->where('store_id', $store->id)
                    ->with(['productVariant.product'])
                    ->lockForUpdate()
                    ->findOrFail($row['inventory_id']);

                $quantity = (int) $row['quantity'];

                if ((int) $inventory->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'Insufficient stock for SKU '.$inventory->sku.'.',
                    ]);
                }

                $product = $inventory->productVariant->product;
                $unitPrice = $inventory->effectivePrice();
                $addonLines = $this->validateAddons(
                    $store->id,
                    $inventory->product_variant_id,
                    $row['addons'] ?? [],
                    $quantity
                );

                $addonSubtotal = collect($addonLines)
                    ->sum('subtotal');

                $lineBase = round(
                    ($unitPrice * $quantity) + $addonSubtotal,
                    2
                );

                $tax = $this->catalogue->calculateTax(
                    $product->is_inclusive_tax
                        ? null
                        : $defaultTaxClass,
                    $lineBase
                );

                $lineTotal = round(
                    $lineBase + $tax['tax_amount'],
                    2
                );

                $calculatedItems[] = compact(
                    'inventory',
                    'product',
                    'quantity',
                    'unitPrice',
                    'addonLines',
                    'lineBase',
                    'lineTotal',
                    'tax'
                );

                $subtotal += $lineBase;
                $taxTotal += $tax['tax_amount'];
            }

            $discount = round(
                min(
                    (float) ($data['discount_amount'] ?? 0),
                    $subtotal + $taxTotal
                ),
                2
            );

            $finalTotal = round(
                $subtotal + $taxTotal - $discount,
                2
            );

            $tenders = $this->validateTenders(
                $data['tenders'],
                $finalTotal
            );

            $cashReceived = collect($tenders)
                ->where('method', 'cash')
                ->sum('received_amount');

            $changeReturned = round(
                max(
                    0,
                    $cashReceived
                        - collect($tenders)
                            ->where('method', 'cash')
                            ->sum('amount')
                ),
                2
            );

            $slug = 'FS-POS-'.now()->format('YmdHis')
                .'-'.Str::upper(Str::random(4));

            $invoice = 'INV-'.now()->format('Ymd')
                .'-'.Str::upper(Str::random(8));

            $order = Order::query()->create([
                'slug' => $slug,
                'user_id' => $customer->id,
                'address_id' => null,
                'delivery_zone_id' => $store->zones()->value(
                    'delivery_zones.id'
                ),
                'status' => 'delivered',
                'payment_method' => count($tenders) > 1
                    ? 'split'
                    : $tenders[0]['method'],
                'payment_status' => 'completed',
                'delivery_type' => 'pickup',
                'is_rush_order' => false,
                'promo_discount' => $discount,
                'wallet_balance' => collect($tenders)
                    ->where('method', 'wallet')
                    ->sum('amount'),
                'subtotal' => round($subtotal, 2),
                'delivery_charge' => 0,
                'handling_charges' => 0,
                'per_store_drop_off_fee' => 0,
                'total_payable' => $finalTotal,
                'final_total' => $finalTotal,
                'email' => $customer->email,
                'billing_name' => $customer->name,
                'billing_phone' => $customer->mobile,
                'shipping_name' => $customer->name,
                'shipping_phone' => $customer->mobile,
                'order_note' => $data['note'] ?? null,
                'paid_at' => now(),
                'metadata' => [
                    'tax_total' => round($taxTotal, 2),
                    'discount_reason' =>
                        $data['discount_reason'] ?? null,
                    'tenders' => $tenders,
                ],
            ]);

            $order->source = 'pos';
            $order->pos_operator_id = $operator->id;
            $order->pos_reference = $data['reference'] ?? $slug;
            $order->cash_received = $cashReceived;
            $order->change_returned = $changeReturned;
            $order->invoice_number = $invoice;
            $order->delivered_at = now();
            $order->save();

            $commissionRate = (float) ($seller->commission_rate ?? 0);
            $commission = round(
                $subtotal * ($commissionRate / 100),
                2
            );

            $sellerOrder = SellerOrder::query()->create([
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'store_id' => $store->id,
                'status' => 'delivered',
                'delivery_type' => 'pickup',
                'subtotal' => round($subtotal, 2),
                'commission_amount' => $commission,
                'seller_earnings' => round(
                    $subtotal - $commission,
                    2
                ),
                'accepted_at' => now(),
                'ready_for_pickup_at' => now(),
            ]);

            foreach ($calculatedItems as $calculated) {
                $inventory = $calculated['inventory'];
                $product = $calculated['product'];
                $quantity = $calculated['quantity'];

                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'seller_order_id' => $sellerOrder->id,
                    'product_id' => $product->id,
                    'product_variant_id' =>
                        $inventory->product_variant_id,
                    'store_id' => $store->id,
                    'product_title' => $product->title,
                    'variant_title' =>
                        $inventory->productVariant->title,
                    'sku' => $inventory->sku,
                    'price' => $calculated['unitPrice'],
                    'special_price' => null,
                    'quantity' => $quantity,
                    'subtotal' => $calculated['lineTotal'],
                    'tax_amount' => $calculated['tax']['tax_amount'],
                    'promo_discount' => 0,
                    'status' => 'delivered',
                    'is_returnable' => (bool) $product->is_returnable,
                    'returnable_until' =>
                        $product->is_returnable
                            ? now()->addDays(
                                (int) ($product->returnable_days ?? 1)
                            )
                            : null,
                    'metadata' => [
                        'pos_line_base' => $calculated['lineBase'],
                        'tax_lines' => $calculated['tax']['rates'],
                    ],
                ]);

                SellerOrderItem::query()->create([
                    'seller_order_id' => $sellerOrder->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_variant_id' =>
                        $inventory->product_variant_id,
                    'store_id' => $store->id,
                    'price' => $calculated['unitPrice'],
                    'quantity' => $quantity,
                    'subtotal' => $calculated['lineTotal'],
                ]);

                foreach ($calculated['addonLines'] as $addon) {
                    DB::table('order_item_addons')->insert([
                        'order_item_id' => $orderItem->id,
                        'addon_group_id' => $addon['addon_group_id'],
                        'addon_item_id' => $addon['addon_item_id'],
                        'group_title' => $addon['group_title'],
                        'item_title' => $addon['item_title'],
                        'unit_price' => $addon['unit_price'],
                        'quantity' => $addon['quantity'],
                        'subtotal' => $addon['subtotal'],
                        'metadata' => json_encode([
                            'store_id' => $store->id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('store_addon_items')
                        ->where('store_id', $store->id)
                        ->where('addon_item_id', $addon['addon_item_id'])
                        ->decrement('stock', $addon['quantity']);
                }

                $this->inventory->changeStock(
                    $inventory,
                    'remove',
                    $quantity,
                    'POS sale '.$order->invoice_number,
                    $operator,
                    'pos_order',
                    $order->id
                );
            }

            foreach ($tenders as $tender) {
                if ($tender['method'] === 'wallet') {
                    $this->customerWallets->debit(
                        $customer,
                        $tender['amount'],
                        'pos_order',
                        $order->id,
                        'POS order '.$order->invoice_number
                    );
                }

                OrderPaymentTransaction::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'transaction_id' =>
                        $tender['transaction_id'] ?? null,
                    'amount' => $tender['amount'],
                    'currency' => 'BDT',
                    'payment_method' => $tender['method'],
                    'payment_status' => 'completed',
                    'message' => 'POS tender',
                    'payment_details' => $tender,
                ]);
            }

            OrderStatusLog::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'delivered',
                'changed_by' => $operator->id,
                'actor_type' => 'seller_pos',
                'note' => 'POS order completed.',
            ]);

            return $order->fresh([
                'items',
                'sellerOrders',
                'paymentTransactions',
            ]);
        });
    }

    public function parkSale(
        Seller $seller,
        User $operator,
        array $data
    ): PosParkedSale {
        Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($data['store_id']);

        return PosParkedSale::query()->create([
            'seller_id' => $seller->id,
            'store_id' => $data['store_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'parked_by' => $operator->id,
            'reference' => $data['reference'] ?? null,
            'cart_payload' => $data['cart'],
            'totals_payload' => $data['totals'] ?? null,
            'note' => $data['note'] ?? null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function refund(
        Seller $seller,
        User $operator,
        Order $order,
        array $data
    ): PosRefund {
        return DB::transaction(function () use (
            $seller,
            $operator,
            $order,
            $data
        ): PosRefund {
            if ($order->source !== 'pos') {
                throw ValidationException::withMessages([
                    'order' => 'Only POS orders use this refund flow.',
                ]);
            }

            $sellerOrder = SellerOrder::query()
                ->where('order_id', $order->id)
                ->where('seller_id', $seller->id)
                ->firstOrFail();

            $refundTotal = 0.0;
            $lines = [];

            foreach ($data['items'] as $row) {
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('seller_order_id', $sellerOrder->id)
                    ->lockForUpdate()
                    ->findOrFail($row['order_item_id']);

                $alreadyRefunded = (int) PosRefundLine::query()
                    ->where('order_item_id', $item->id)
                    ->sum('quantity');

                $available = (int) $item->quantity - $alreadyRefunded;
                $quantity = (int) $row['quantity'];

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'Refund quantity exceeds available quantity.',
                    ]);
                }

                $unitAmount = round(
                    (float) $item->subtotal / (int) $item->quantity,
                    2
                );

                $amount = round($unitAmount * $quantity, 2);
                $refundTotal += $amount;

                $lines[] = compact('item', 'quantity', 'amount');
            }

            $refund = PosRefund::query()->create([
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'store_id' => $sellerOrder->store_id,
                'processed_by' => $operator->id,
                'amount' => round($refundTotal, 2),
                'method' => $data['method'],
                'status' => 'completed',
                'reason' => $data['reason'] ?? null,
                'metadata' => [
                    'invoice_number' => $order->invoice_number,
                ],
            ]);

            foreach ($lines as $line) {
                PosRefundLine::query()->create([
                    'pos_refund_id' => $refund->id,
                    'order_item_id' => $line['item']->id,
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'],
                ]);

                $inventory = StoreProductVariant::query()
                    ->where('store_id', $sellerOrder->store_id)
                    ->where(
                        'product_variant_id',
                        $line['item']->product_variant_id
                    )
                    ->first();

                if ($inventory) {
                    $this->inventory->changeStock(
                        $inventory,
                        'add',
                        $line['quantity'],
                        'POS refund '.$refund->uuid,
                        $operator,
                        'pos_refund',
                        $refund->id
                    );
                }
            }

            if ($data['method'] === 'wallet') {
                $this->customerWallets->credit(
                    $order->user,
                    $refundTotal,
                    'pos_refund',
                    $refund->id,
                    'POS refund '.$order->invoice_number
                );
            }

            OrderPaymentTransaction::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'transaction_id' => $refund->uuid,
                'amount' => -1 * round($refundTotal, 2),
                'currency' => 'BDT',
                'payment_method' => $data['method'],
                'payment_status' => 'refunded',
                'message' => 'POS refund',
                'payment_details' => [
                    'refund_id' => $refund->id,
                ],
            ]);

            return $refund->fresh('lines');
        });
    }

    public function pushDisplay(
        Seller $seller,
        int $storeId,
        array $state,
        ?string $token = null
    ): PosCustomerDisplay {
        Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($storeId);

        $display = $token
            ? PosCustomerDisplay::query()
                ->where('seller_id', $seller->id)
                ->where('token', $token)
                ->firstOrFail()
            : new PosCustomerDisplay([
                'seller_id' => $seller->id,
                'store_id' => $storeId,
            ]);

        $display->fill([
            'state_payload' => $state,
            'expires_at' => now()->addHours(12),
            'last_seen_at' => now(),
        ])->save();

        return $display->fresh();
    }

    private function validateAddons(
        int $storeId,
        int $variantId,
        array $selected,
        int $productQuantity
    ): array {
        if ($selected === []) {
            return [];
        }

        $matrix = collect(
            $this->catalogue->variantAddonMatrix($storeId, $variantId)
        )->keyBy('id');

        $grouped = collect($selected)
            ->groupBy('addon_group_id');

        $lines = [];

        foreach ($grouped as $groupId => $items) {
            $group = $matrix->get((int) $groupId);

            if (! $group) {
                throw ValidationException::withMessages([
                    'addons' =>
                        'An addon group is unavailable for the product.',
                ]);
            }

            $count = $items->count();

            if ($group['is_required'] && $count === 0) {
                throw ValidationException::withMessages([
                    'addons' => 'A required addon group is missing.',
                ]);
            }

            if ($count < $group['minimum_selection']) {
                throw ValidationException::withMessages([
                    'addons' =>
                        'Minimum addon selection requirement was not met.',
                ]);
            }

            if (
                $group['maximum_selection'] !== null
                && $count > $group['maximum_selection']
            ) {
                throw ValidationException::withMessages([
                    'addons' =>
                        'Maximum addon selection limit was exceeded.',
                ]);
            }

            $available = collect($group['items'])->keyBy('id');

            foreach ($items as $selectedItem) {
                $item = $available->get(
                    (int) $selectedItem['addon_item_id']
                );

                if (! $item || ! $item['is_available']) {
                    throw ValidationException::withMessages([
                        'addons' => 'An addon item is unavailable.',
                    ]);
                }

                $perProductQuantity = (int) (
                    $selectedItem['quantity'] ?? 1
                );

                $totalQuantity = $perProductQuantity * $productQuantity;

                if ($item['stock'] < $totalQuantity) {
                    throw ValidationException::withMessages([
                        'addons' =>
                            'An addon item has insufficient stock.',
                    ]);
                }

                $lines[] = [
                    'addon_group_id' => (int) $groupId,
                    'addon_item_id' => (int) $item['id'],
                    'group_title' => $group['title'],
                    'item_title' => $item['title'],
                    'unit_price' => (float) $item['price'],
                    'quantity' => $totalQuantity,
                    'subtotal' => round(
                        (float) $item['price'] * $totalQuantity,
                        2
                    ),
                ];
            }
        }

        foreach ($matrix as $group) {
            if (
                $group['is_required']
                && ! $grouped->has($group['id'])
            ) {
                throw ValidationException::withMessages([
                    'addons' =>
                        'A required addon group is missing.',
                ]);
            }
        }

        return $lines;
    }

    private function validateTenders(
        array $tenders,
        float $total
    ): array {
        $normalized = [];
        $sum = 0.0;

        foreach ($tenders as $tender) {
            $amount = round((float) $tender['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'tenders' => 'Tender amount must be positive.',
                ]);
            }

            $normalized[] = [
                'method' => $tender['method'],
                'amount' => $amount,
                'received_amount' => round(
                    (float) ($tender['received_amount'] ?? $amount),
                    2
                ),
                'transaction_id' =>
                    $tender['transaction_id'] ?? null,
                'metadata' => $tender['metadata'] ?? [],
            ];

            $sum += $amount;
        }

        if (round($sum, 2) !== round($total, 2)) {
            throw ValidationException::withMessages([
                'tenders' =>
                    'Tender amounts must equal the order total.',
            ]);
        }

        return $normalized;
    }
}
