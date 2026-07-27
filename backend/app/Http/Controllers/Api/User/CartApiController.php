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