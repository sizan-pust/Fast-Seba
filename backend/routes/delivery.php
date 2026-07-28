<?php

use App\Http\Controllers\Api\Admin\AdminAuthApiController;
use App\Http\Controllers\Api\Admin\AdminOrderApiController;
use App\Http\Controllers\Api\Admin\AdminReturnApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyAuthApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyOrderApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyReturnPickupApiController;
use App\Http\Controllers\Api\Seller\SellerOrderApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('delivery-boy')
    ->group(function (): void {
        Route::post(
            'login',
            [DeliveryBoyAuthApiController::class, 'login']
        );
    });

Route::prefix('admin')
    ->group(function (): void {
        Route::post(
            'login',
            [AdminAuthApiController::class, 'login']
        );
    });

Route::middleware('auth:sanctum')
    ->group(function (): void {
        Route::prefix('delivery-boy')
            ->group(function (): void {
                Route::post(
                    'logout',
                    [DeliveryBoyAuthApiController::class, 'logout']
                );

                Route::get(
                    'profile',
                    [DeliveryBoyAuthApiController::class, 'profile']
                );

                Route::post(
                    'status/update',
                    [
                        DeliveryBoyAuthApiController::class,
                        'updateStatus',
                    ]
                );

                Route::post(
                    'update-current-location',
                    [
                        DeliveryBoyOrderApiController::class,
                        'updateLocation',
                    ]
                );

                Route::get(
                    'get-last-location',
                    [
                        DeliveryBoyOrderApiController::class,
                        'lastLocation',
                    ]
                );

                Route::get(
                    'orders/available',
                    [
                        DeliveryBoyOrderApiController::class,
                        'available',
                    ]
                );

                Route::get(
                    'orders/my',
                    [
                        DeliveryBoyOrderApiController::class,
                        'myOrders',
                    ]
                );

                Route::get(
                    'orders/{orderId}',
                    [
                        DeliveryBoyOrderApiController::class,
                        'show',
                    ]
                );

                Route::post(
                    'orders/{orderId}/accept',
                    [
                        DeliveryBoyOrderApiController::class,
                        'accept',
                    ]
                );

                Route::put(
                    'orders/{orderId}/status',
                    [
                        DeliveryBoyOrderApiController::class,
                        'updateStatus',
                    ]
                );

                Route::get(
                    'return-pickups/available',
                    [
                        DeliveryBoyReturnPickupApiController::class,
                        'available',
                    ]
                );

                Route::get(
                    'return-pickups/my',
                    [
                        DeliveryBoyReturnPickupApiController::class,
                        'myPickups',
                    ]
                );

                Route::post(
                    'return-pickups/{returnId}/accept',
                    [
                        DeliveryBoyReturnPickupApiController::class,
                        'accept',
                    ]
                );

                Route::put(
                    'return-pickups/{returnId}/status',
                    [
                        DeliveryBoyReturnPickupApiController::class,
                        'updateStatus',
                    ]
                );
            });

        Route::prefix('seller')
            ->group(function (): void {
                Route::get(
                    'returns',
                    [
                        SellerOrderApiController::class,
                        'returns',
                    ]
                );

                Route::post(
                    'returns/{returnId}/decision',
                    [
                        SellerOrderApiController::class,
                        'decideReturn',
                    ]
                );
            });

        Route::prefix('admin')
            ->group(function (): void {
                Route::post(
                    'logout',
                    [AdminAuthApiController::class, 'logout']
                );

                Route::get(
                    'orders',
                    [AdminOrderApiController::class, 'index']
                );

                Route::get(
                    'orders/{orderId}',
                    [AdminOrderApiController::class, 'show']
                );

                Route::post(
                    'orders/{orderId}/assign-rider',
                    [
                        AdminOrderApiController::class,
                        'assignRider',
                    ]
                );

                Route::get(
                    'returns',
                    [AdminReturnApiController::class, 'index']
                );

                Route::post(
                    'returns/{returnId}/assign-rider',
                    [
                        AdminReturnApiController::class,
                        'assignRider',
                    ]
                );

                Route::post(
                    'returns/{returnId}/refund',
                    [
                        AdminReturnApiController::class,
                        'refund',
                    ]
                );
            });
    });
