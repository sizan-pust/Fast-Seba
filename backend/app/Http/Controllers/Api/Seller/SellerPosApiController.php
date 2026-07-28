<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosParkedSale;
use App\Models\PosRefund;
use App\Models\Seller;
use App\Services\IdempotencyService;
use App\Services\PosService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerPosApiController extends Controller
{
    public function __construct(
        protected PosService $pos,
        protected IdempotencyService $idempotency
    ) {
    }

    private function seller(Request $request): Seller
    {
        return $this->pos->sellerFor($request->user());
    }

    public function customers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'POS customers fetched.',
            $this->pos->searchCustomers(
                $data['search'],
                $data['limit'] ?? 20
            )
        );
    }

    public function registerCustomer(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'required',
                'string',
                'max:32',
                'unique:users,mobile',
            ],
            'email' => [
                'nullable',
                'email',
                'unique:users,email',
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'POS customer registered.',
            $this->pos->quickRegisterCustomer($data),
            201
        );
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'POS products fetched.',
            $this->pos->searchProducts(
                $this->seller($request),
                $data['store_id'],
                $data['search'] ?? null,
                $data['barcode'] ?? null,
                $data['limit'] ?? 30
            )
        );
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => [
                'required',
                'integer',
                'exists:store_product_variants,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.addons' => ['nullable', 'array'],
            'items.*.addons.*.addon_group_id' => [
                'required',
                'integer',
                'exists:addon_groups,id',
            ],
            'items.*.addons.*.addon_item_id' => [
                'required',
                'integer',
                'exists:addon_items,id',
            ],
            'items.*.addons.*.quantity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'tenders' => ['required', 'array', 'min:1'],
            'tenders.*.method' => [
                'required',
                Rule::in([
                    'cash',
                    'card',
                    'wallet',
                    'bank',
                    'external',
                ]),
            ],
            'tenders.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenders.*.received_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'tenders.*.transaction_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'tenders.*.metadata' => ['nullable', 'array'],
        ]);

        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Idempotency-Key header is required.',
                [],
                422
            );
        }

        $replay = $this->idempotency->replayOrReserve(
            $request->user(),
            'seller-pos-order',
            $key,
            $data
        );

        if ($replay) {
            return $replay;
        }

        $order = $this->pos->createOrder(
            $this->seller($request),
            $request->user(),
            $data
        );

        $body = [
            'success' => true,
            'message' => 'POS order created.',
            'data' => $this->receiptPayload($order),
        ];

        $this->idempotency->remember(
            'seller-pos-order',
            $key,
            201,
            $body
        );

        return response()->json($body, 201);
    }

    public function recentOrders(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = Order::query()
            ->where('source', 'pos')
            ->whereHas(
                'sellerOrders',
                fn ($query) =>
                    $query->where('seller_id', $seller->id)
            )
            ->with(['user', 'items', 'paymentTransactions'])
            ->latest()
            ->paginate(
                min(
                    100,
                    max(1, (int) $request->input('per_page', 15))
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Recent POS orders fetched.',
            $items
        );
    }

    public function receipt(
        Request $request,
        int $id
    ): JsonResponse {
        $order = $this->ownedPosOrder(
            $this->seller($request),
            $id
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'POS receipt fetched.',
            $this->receiptPayload($order)
        );
    }

    public function parkedSales(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = PosParkedSale::query()
            ->where('seller_id', $seller->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();

        return ApiResponseType::sendJsonResponse(
            true,
            'Parked sales fetched.',
            $items
        );
    }

    public function parkSale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'cart' => ['required', 'array', 'min:1'],
            'totals' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Sale parked.',
            $this->pos->parkSale(
                $this->seller($request),
                $request->user(),
                $data
            ),
            201
        );
    }

    public function updateParkedSale(
        Request $request,
        int $id
    ): JsonResponse {
        $sale = PosParkedSale::query()
            ->where('seller_id', $this->seller($request)->id)
            ->findOrFail($id);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'cart' => ['sometimes', 'array', 'min:1'],
            'totals' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $sale->update([
            'customer_id' =>
                $data['customer_id'] ?? $sale->customer_id,
            'reference' => $data['reference'] ?? $sale->reference,
            'cart_payload' =>
                $data['cart'] ?? $sale->cart_payload,
            'totals_payload' =>
                $data['totals'] ?? $sale->totals_payload,
            'note' => $data['note'] ?? $sale->note,
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Parked sale updated.',
            $sale->fresh()
        );
    }

    public function deleteParkedSale(
        Request $request,
        int $id
    ): JsonResponse {
        PosParkedSale::query()
            ->where('seller_id', $this->seller($request)->id)
            ->findOrFail($id)
            ->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Parked sale deleted.',
            []
        );
    }

    public function refundPreview(
        Request $request,
        int $id
    ): JsonResponse {
        $order = $this->ownedPosOrder(
            $this->seller($request),
            $id
        );

        $items = $order->items->map(function ($item): array {
            $refunded = (int) \App\Models\PosRefundLine::query()
                ->where('order_item_id', $item->id)
                ->sum('quantity');

            return [
                'order_item_id' => $item->id,
                'product_title' => $item->product_title,
                'variant_title' => $item->variant_title,
                'quantity' => $item->quantity,
                'refunded_quantity' => $refunded,
                'available_quantity' =>
                    max(0, $item->quantity - $refunded),
                'line_total' => $item->subtotal,
            ];
        })->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'POS refund preview fetched.',
            [
                'order_id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'items' => $items,
            ]
        );
    }

    public function createRefund(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'method' => [
                'required',
                Rule::in(['cash', 'wallet', 'card', 'bank']),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => [
                'required',
                'integer',
                'exists:order_items,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'POS refund completed.',
            $this->pos->refund(
                $this->seller($request),
                $request->user(),
                $this->ownedPosOrder(
                    $this->seller($request),
                    $id
                ),
                $data
            ),
            201
        );
    }

    public function refunds(
        Request $request,
        int $id
    ): JsonResponse {
        $order = $this->ownedPosOrder(
            $this->seller($request),
            $id
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'POS refunds fetched.',
            PosRefund::query()
                ->where('order_id', $order->id)
                ->with('lines')
                ->latest()
                ->get()
        );
    }

    public function pushCustomerDisplay(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'token' => ['nullable', 'uuid'],
            'state' => ['required', 'array'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Customer display updated.',
            $this->pos->pushDisplay(
                $this->seller($request),
                $data['store_id'],
                $data['state'],
                $data['token'] ?? null
            )
        );
    }

    private function ownedPosOrder(
        Seller $seller,
        int $id
    ): Order {
        return Order::query()
            ->where('source', 'pos')
            ->whereHas(
                'sellerOrders',
                fn ($query) =>
                    $query->where('seller_id', $seller->id)
            )
            ->with([
                'user',
                'items',
                'sellerOrders',
                'paymentTransactions',
            ])
            ->findOrFail($id);
    }

    private function receiptPayload(Order $order): array
    {
        $addons = \Illuminate\Support\Facades\DB::table(
            'order_item_addons'
        )
            ->whereIn('order_item_id', $order->items->pluck('id'))
            ->get()
            ->groupBy('order_item_id');

        return [
            'order_id' => $order->id,
            'slug' => $order->slug,
            'invoice_number' => $order->invoice_number,
            'pos_reference' => $order->pos_reference,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'mobile' => $order->user?->mobile,
            ],
            'items' => $order->items->map(
                fn ($item) => [
                    'id' => $item->id,
                    'product_title' => $item->product_title,
                    'variant_title' => $item->variant_title,
                    'sku' => $item->sku,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'tax_amount' => $item->tax_amount,
                    'subtotal' => $item->subtotal,
                    'addons' => collect(
                        $addons->get($item->id, [])
                    )->values(),
                ]
            )->values(),
            'subtotal' => $order->subtotal,
            'discount' => $order->promo_discount,
            'tax_total' => data_get(
                $order->metadata,
                'tax_total',
                0
            ),
            'final_total' => $order->final_total,
            'cash_received' => $order->cash_received,
            'change_returned' => $order->change_returned,
            'payments' => $order->paymentTransactions,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
