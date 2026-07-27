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