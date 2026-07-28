<?php

use App\Http\Controllers\Api\Admin\AdminFinanceApiController;
use App\Http\Controllers\Api\Admin\AdminManagementApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyFinanceApiController;
use App\Http\Controllers\Api\PaymentGatewayApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeManagementApiController;
use App\Http\Controllers\Api\Seller\SellerFinanceApiController;
use App\Http\Controllers\Api\Seller\SellerMediaApiController;
use App\Http\Controllers\Api\Seller\SellerOnboardingApiController;
use App\Http\Controllers\Api\Seller\SellerManagementApiController;
use Illuminate\Support\Facades\Route;

Route::get(
    'payment/gateways',
    [PaymentGatewayApiController::class, 'index']
);

Route::post(
    'seller/register',
    [SellerOnboardingApiController::class, 'register']
);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('seller')->group(function (): void {
        Route::get(
            'profile',
            [SellerOnboardingApiController::class, 'profile']
        );

        Route::get(
            'dashboard',
            [SellerManagementApiController::class, 'dashboard']
        );

        Route::prefix('stores')->group(function (): void {
            Route::get(
                '/',
                [SellerManagementApiController::class, 'stores']
            );
            Route::get(
                '/enums',
                [SellerManagementApiController::class, 'storeEnums']
            );
            Route::post(
                '/',
                [SellerManagementApiController::class, 'createStore']
            );
            Route::get(
                '/{id}',
                [SellerManagementApiController::class, 'showStore']
            );
            Route::post(
                '/{id}',
                [SellerManagementApiController::class, 'updateStore']
            );
            Route::post(
                '/{id}/status',
                [SellerManagementApiController::class, 'updateStoreStatus']
            );
            Route::delete(
                '/{id}',
                [SellerManagementApiController::class, 'deleteStore']
            );
        });

        Route::prefix('products')->group(function (): void {
            Route::get(
                '/',
                [SellerManagementApiController::class, 'products']
            );
            Route::get(
                '/enums',
                [SellerManagementApiController::class, 'productEnums']
            );
            Route::post(
                '/',
                [SellerManagementApiController::class, 'createProduct']
            );
            Route::get(
                '/{id}',
                [SellerManagementApiController::class, 'showProduct']
            );
            Route::post(
                '/{id}',
                [SellerManagementApiController::class, 'updateProduct']
            );
            Route::post(
                '/{id}/update-status',
                [SellerManagementApiController::class, 'updateProductStatus']
            );
            Route::delete(
                '/{id}',
                [SellerManagementApiController::class, 'deleteProduct']
            );
        });

        Route::prefix('attributes')->group(function (): void {
            Route::get(
                '/',
                [SellerAttributeManagementApiController::class, 'index']
            );
            Route::post(
                '/',
                [SellerAttributeManagementApiController::class, 'store']
            );
            Route::post(
                '/{id}',
                [SellerAttributeManagementApiController::class, 'update']
            );
            Route::delete(
                '/{id}',
                [SellerAttributeManagementApiController::class, 'destroy']
            );
            Route::post(
                '/{attributeId}/values',
                [SellerAttributeManagementApiController::class, 'storeValue']
            );
            Route::post(
                '/values/{valueId}',
                [SellerAttributeManagementApiController::class, 'updateValue']
            );
            Route::delete(
                '/values/{valueId}',
                [SellerAttributeManagementApiController::class, 'destroyValue']
            );
        });

        Route::prefix('media')->group(function (): void {
            Route::post(
                '/products/{productId}',
                [SellerMediaApiController::class, 'uploadProductMedia']
            );
            Route::post(
                '/variants/{variantId}',
                [SellerMediaApiController::class, 'uploadVariantImage']
            );
            Route::post(
                '/stores/{storeId}',
                [SellerMediaApiController::class, 'uploadStoreMedia']
            );
            Route::delete(
                '/{mediaId}',
                [SellerMediaApiController::class, 'deleteMedia']
            );
        });

        Route::prefix('inventory')->group(function (): void {
            Route::get(
                '/',
                [SellerManagementApiController::class, 'inventory']
            );
            Route::post(
                '/{id}/adjust',
                [SellerManagementApiController::class, 'adjustInventory']
            );
            Route::get(
                '/{id}/logs',
                [SellerManagementApiController::class, 'inventoryLogs']
            );
        });

        Route::prefix('wallet')->group(function (): void {
            Route::get(
                '/',
                [SellerFinanceApiController::class, 'wallet']
            );
            Route::get(
                '/transactions',
                [SellerFinanceApiController::class, 'walletTransactions']
            );
        });

        Route::prefix('withdrawals')->group(function (): void {
            Route::get(
                '/',
                [SellerFinanceApiController::class, 'withdrawals']
            );
            Route::get(
                '/history',
                [SellerFinanceApiController::class, 'withdrawals']
            );
            Route::post(
                '/',
                [SellerFinanceApiController::class, 'createWithdrawal']
            );
            Route::get(
                '/{id}',
                [SellerFinanceApiController::class, 'withdrawal']
            );
        });

        Route::prefix('commissions')->group(function (): void {
            Route::get(
                '/',
                [SellerFinanceApiController::class, 'statements']
            );
            Route::get(
                '/debits',
                [SellerFinanceApiController::class, 'statements']
            );
            Route::get(
                '/history',
                [SellerFinanceApiController::class, 'statements']
            );
        });
    });

    Route::prefix('delivery-boy')->group(function (): void {
        Route::get(
            'wallet',
            [DeliveryBoyFinanceApiController::class, 'wallet']
        );
        Route::get(
            'wallet/transactions',
            [DeliveryBoyFinanceApiController::class, 'transactions']
        );
        Route::get(
            'withdrawals',
            [DeliveryBoyFinanceApiController::class, 'withdrawals']
        );
        Route::post(
            'withdrawals',
            [DeliveryBoyFinanceApiController::class, 'createWithdrawal']
        );
    });

    Route::prefix('admin')->group(function (): void {
        Route::get(
            'dashboard',
            [AdminManagementApiController::class, 'dashboard']
        );

        Route::prefix('sellers')->group(function (): void {
            Route::get(
                '/',
                [AdminManagementApiController::class, 'sellers']
            );
            Route::post(
                '/',
                [AdminManagementApiController::class, 'createSeller']
            );
            Route::get(
                '/{id}',
                [AdminManagementApiController::class, 'showSeller']
            );
            Route::post(
                '/{id}/verify',
                [AdminManagementApiController::class, 'verifySeller']
            );
            Route::post(
                '/{id}/status',
                [AdminManagementApiController::class, 'updateSellerStatus']
            );
        });

        Route::prefix('stores')->group(function (): void {
            Route::get(
                '/',
                [AdminManagementApiController::class, 'stores']
            );
            Route::get(
                '/{id}',
                [AdminManagementApiController::class, 'showStore']
            );
            Route::post(
                '/{id}/verify',
                [AdminManagementApiController::class, 'verifyStore']
            );
            Route::post(
                '/{id}/status',
                [AdminManagementApiController::class, 'updateStoreStatus']
            );
            Route::post(
                '/{id}/recommended',
                [AdminManagementApiController::class, 'recommendStore']
            );
        });

        Route::prefix('products')->group(function (): void {
            Route::get(
                '/',
                [AdminManagementApiController::class, 'products']
            );
            Route::get(
                '/{id}',
                [AdminManagementApiController::class, 'showProduct']
            );
            Route::post(
                '/{id}/verification-status',
                [AdminManagementApiController::class, 'verifyProduct']
            );
            Route::post(
                '/{id}/update-status',
                [AdminManagementApiController::class, 'updateProductStatus']
            );
        });

        Route::prefix('finance')->group(function (): void {
            Route::post(
                'sync',
                [AdminFinanceApiController::class, 'sync']
            );
            Route::get(
                'statements',
                [AdminFinanceApiController::class, 'statements']
            );
            Route::post(
                'statements/{id}/settle',
                [AdminFinanceApiController::class, 'settleStatement']
            );
            Route::get(
                'seller-withdrawals',
                [AdminFinanceApiController::class, 'sellerWithdrawals']
            );
            Route::post(
                'seller-withdrawals/{id}/process',
                [AdminFinanceApiController::class, 'processSellerWithdrawal']
            );
            Route::get(
                'rider-withdrawals',
                [AdminFinanceApiController::class, 'riderWithdrawals']
            );
            Route::post(
                'rider-withdrawals/{id}/process',
                [AdminFinanceApiController::class, 'processRiderWithdrawal']
            );
        });

        Route::prefix('payment-gateways')->group(function (): void {
            Route::get(
                '/',
                [AdminFinanceApiController::class, 'gateways']
            );
            Route::put(
                '/{code}',
                [AdminFinanceApiController::class, 'updateGateway']
            );
        });
    });
});
