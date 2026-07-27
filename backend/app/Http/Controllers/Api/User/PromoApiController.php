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