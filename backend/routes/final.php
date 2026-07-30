<?php

use App\Http\Controllers\Api\Admin\AdminBulkUploadApiController;
use App\Http\Controllers\Api\Admin\AdminFinalOperationsApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyCashApiController;
use App\Http\Controllers\Api\PaymentIntentApiController;
use App\Http\Controllers\Api\PublicFinalApiController;
use App\Http\Controllers\Api\Seller\SellerAddonApiController;
use App\Http\Controllers\Api\Seller\SellerAdvertisingApiController;
use App\Http\Controllers\Api\Seller\SellerBulkUploadApiController;
use App\Http\Controllers\Api\Seller\SellerFeedbackApiController;
use App\Http\Controllers\Api\Seller\SellerPosApiController;
use App\Http\Controllers\Api\Seller\SellerSubscriptionApiController;
use App\Http\Controllers\Api\Seller\SellerTeamApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\Api\User\CustomerFinalApiController;
use Illuminate\Support\Facades\Route;

Route::get('system/live', [SystemApiController::class, 'live']);
Route::get('system/ready', [SystemApiController::class, 'ready']);
Route::get('docs/openapi.json', [SystemApiController::class, 'openApi']);
Route::get(
    'pos/customer-display/{token}',
    [SystemApiController::class, 'customerDisplay']
)->middleware('throttle:120,1');

Route::get(
    'tax-classes',
    [PublicFinalApiController::class, 'taxClasses']
);
Route::get(
    'collections',
    [PublicFinalApiController::class, 'collections']
);
Route::get(
    'collections/{slug}',
    [PublicFinalApiController::class, 'collection']
);
Route::get(
    'stores/{storeId}/variants/{variantId}/addons',
    [PublicFinalApiController::class, 'variantAddons']
);
Route::get(
    'advertisements',
    [PublicFinalApiController::class, 'advertisements']
);
Route::post(
    'advertisements/{uuid}/events',
    [PublicFinalApiController::class, 'recordAdEvent']
)->middleware('throttle:120,1');
Route::get(
    'sellers/{sellerId}/rating',
    [PublicFinalApiController::class, 'sellerRating']
);
Route::get(
    'sellers/{sellerId}/reviews',
    [PublicFinalApiController::class, 'sellerReviews']
);
Route::get(
    'delivery-boys/{riderId}/rating',
    [PublicFinalApiController::class, 'riderRating']
);

Route::get(
    'payments/intents/{uuid}/redirect',
    [PaymentIntentApiController::class, 'redirect']
)->middleware('throttle:60,1');

Route::post(
    'payments/webhooks/{provider}',
    [PaymentIntentApiController::class, 'webhook']
)->middleware('throttle:300,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('payments/intents')->group(function (): void {
        Route::post(
            '/',
            [PaymentIntentApiController::class, 'create']
        )->middleware('throttle:20,1');
        Route::get(
            '{uuid}',
            [PaymentIntentApiController::class, 'show']
        );
    });

    Route::prefix('user')->group(function (): void {
        Route::get(
            'followed-sellers',
            [CustomerFinalApiController::class, 'followedSellers']
        );
        Route::post(
            'followed-sellers/{sellerId}',
            [CustomerFinalApiController::class, 'followSeller']
        );
        Route::delete(
            'followed-sellers/{sellerId}',
            [CustomerFinalApiController::class, 'unfollowSeller']
        );

        Route::prefix('feedback')->group(function (): void {
            Route::post(
                'sellers',
                [CustomerFinalApiController::class, 'createSellerFeedback']
            )->middleware('throttle:10,1');
            Route::post(
                'delivery',
                [CustomerFinalApiController::class, 'createDeliveryFeedback']
            )->middleware('throttle:10,1');
            Route::put(
                'sellers/{id}',
                [CustomerFinalApiController::class, 'updateSellerFeedback']
            )->middleware('throttle:10,1');
            Route::put(
                'delivery/{id}',
                [CustomerFinalApiController::class, 'updateDeliveryFeedback']
            )->middleware('throttle:10,1');
            Route::get(
                'sellers',
                [CustomerFinalApiController::class, 'mySellerFeedback']
            );
            Route::get(
                'delivery',
                [CustomerFinalApiController::class, 'myDeliveryFeedback']
            );
        });
    });

    Route::prefix('seller')->group(function (): void {
        Route::prefix('addons')->group(function (): void {
            Route::get(
                '/',
                [SellerAddonApiController::class, 'index']
            );
            Route::post(
                '/',
                [SellerAddonApiController::class, 'store']
            );
            Route::post(
                'matrix/attach',
                [SellerAddonApiController::class, 'attachMatrix']
            );
            Route::get(
                'matrix/{storeId}/{variantId}',
                [SellerAddonApiController::class, 'matrix']
            );
            Route::get(
                '{id}',
                [SellerAddonApiController::class, 'show']
            );
            Route::post(
                '{id}',
                [SellerAddonApiController::class, 'update']
            );
            Route::delete(
                '{id}',
                [SellerAddonApiController::class, 'destroy']
            );
        });

        Route::prefix('subscriptions')->group(function (): void {
            Route::get(
                'plans',
                [SellerSubscriptionApiController::class, 'plans']
            );
            Route::get(
                'current',
                [SellerSubscriptionApiController::class, 'current']
            );
            Route::post(
                'eligibility',
                [SellerSubscriptionApiController::class, 'eligibility']
            );
            Route::post(
                'buy',
                [SellerSubscriptionApiController::class, 'buy']
            )->middleware('throttle:10,1');
            Route::get(
                'history',
                [SellerSubscriptionApiController::class, 'history']
            );
        });

        Route::prefix('advertisements')->group(function (): void {
            Route::get(
                'config',
                [SellerAdvertisingApiController::class, 'config']
            );
            Route::get(
                'wallet',
                [SellerAdvertisingApiController::class, 'wallet']
            );
            Route::get(
                'wallet/transactions',
                [SellerAdvertisingApiController::class, 'transactions']
            );
            Route::post(
                'wallet/topup-from-earnings',
                [SellerAdvertisingApiController::class, 'topupFromEarnings']
            )->middleware('throttle:10,1');
            Route::post(
                'wallet/topup-gateway',
                [SellerAdvertisingApiController::class, 'topupGateway']
            )->middleware('throttle:10,1');
            Route::get(
                '/',
                [SellerAdvertisingApiController::class, 'index']
            );
            Route::post(
                '/',
                [SellerAdvertisingApiController::class, 'store']
            );
            Route::get(
                '{id}',
                [SellerAdvertisingApiController::class, 'show']
            );
            Route::post(
                '{id}',
                [SellerAdvertisingApiController::class, 'update']
            );
            Route::post(
                '{id}/pause',
                [SellerAdvertisingApiController::class, 'pause']
            );
            Route::post(
                '{id}/resume',
                [SellerAdvertisingApiController::class, 'resume']
            );
        });

        Route::prefix('pos')->group(function (): void {
            Route::get(
                'customers',
                [SellerPosApiController::class, 'customers']
            );
            Route::post(
                'customers',
                [SellerPosApiController::class, 'registerCustomer']
            )->middleware('throttle:20,1');
            Route::get(
                'products',
                [SellerPosApiController::class, 'products']
            );
            Route::post(
                'orders',
                [SellerPosApiController::class, 'createOrder']
            )->middleware('throttle:30,1');
            Route::get(
                'orders',
                [SellerPosApiController::class, 'recentOrders']
            );
            Route::get(
                'orders/{id}/receipt',
                [SellerPosApiController::class, 'receipt']
            );
            Route::get(
                'parked-sales',
                [SellerPosApiController::class, 'parkedSales']
            );
            Route::post(
                'parked-sales',
                [SellerPosApiController::class, 'parkSale']
            );
            Route::post(
                'parked-sales/{id}',
                [SellerPosApiController::class, 'updateParkedSale']
            );
            Route::delete(
                'parked-sales/{id}',
                [SellerPosApiController::class, 'deleteParkedSale']
            );
            Route::get(
                'orders/{id}/refund-preview',
                [SellerPosApiController::class, 'refundPreview']
            );
            Route::post(
                'orders/{id}/refunds',
                [SellerPosApiController::class, 'createRefund']
            )->middleware('throttle:20,1');
            Route::get(
                'orders/{id}/refunds',
                [SellerPosApiController::class, 'refunds']
            );
            Route::post(
                'customer-display',
                [SellerPosApiController::class, 'pushCustomerDisplay']
            )->middleware('throttle:120,1');
        });

        Route::prefix('bulk-uploads')->group(function (): void {
            Route::get(
                '/',
                [SellerBulkUploadApiController::class, 'index']
            );
            Route::get(
                'templates/{type}',
                [SellerBulkUploadApiController::class, 'template']
            );
            Route::post(
                'import',
                [SellerBulkUploadApiController::class, 'import']
            )->middleware('throttle:10,1');
            Route::post(
                'export',
                [SellerBulkUploadApiController::class, 'export']
            )->middleware('throttle:10,1');
            Route::get(
                '{uuid}',
                [SellerBulkUploadApiController::class, 'show']
            );
            Route::get(
                '{uuid}/download',
                [SellerBulkUploadApiController::class, 'download']
            );
            Route::get(
                '{uuid}/errors',
                [SellerBulkUploadApiController::class, 'downloadErrors']
            );
        });

        Route::prefix('feedback')->group(function (): void {
            Route::get(
                '/',
                [SellerFeedbackApiController::class, 'index']
            );
            Route::post(
                '{id}/reply',
                [SellerFeedbackApiController::class, 'reply']
            );
        });

        Route::prefix('team')->group(function (): void {
            Route::get(
                'permissions',
                [SellerTeamApiController::class, 'permissions']
            );
            Route::get(
                'roles',
                [SellerTeamApiController::class, 'roles']
            );
            Route::post(
                'roles',
                [SellerTeamApiController::class, 'createRole']
            );
            Route::delete(
                'roles/{id}',
                [SellerTeamApiController::class, 'deleteRole']
            );
            Route::get(
                'members',
                [SellerTeamApiController::class, 'members']
            );
            Route::post(
                'members',
                [SellerTeamApiController::class, 'createMember']
            );
            Route::post(
                'members/{userId}',
                [SellerTeamApiController::class, 'updateMember']
            );
            Route::delete(
                'members/{userId}',
                [SellerTeamApiController::class, 'removeMember']
            );
        });
    });

    Route::prefix('delivery-boy')->group(function (): void {
        Route::get(
            'cash-transactions',
            [DeliveryBoyCashApiController::class, 'index']
        );
        Route::get(
            'cash-statistics',
            [DeliveryBoyCashApiController::class, 'statistics']
        );
        Route::post(
            'cash-remittances',
            [DeliveryBoyCashApiController::class, 'requestRemittance']
        )->middleware('throttle:10,1');
    });

    Route::prefix('admin')->group(function (): void {
        Route::get(
            'operations/dashboard',
            [AdminFinalOperationsApiController::class, 'dashboard']
        );

        Route::prefix('tax-classes')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'taxClasses']
            );
            Route::post(
                '/',
                [AdminFinalOperationsApiController::class, 'saveTaxClass']
            );
            Route::delete(
                '{id}',
                [AdminFinalOperationsApiController::class, 'deleteTaxClass']
            );
        });

        Route::prefix('tax-rates')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'taxRates']
            );
            Route::post(
                '/',
                [AdminFinalOperationsApiController::class, 'saveTaxRate']
            );
            Route::delete(
                '{id}',
                [AdminFinalOperationsApiController::class, 'deleteTaxRate']
            );
        });

        Route::prefix('collections')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'collections']
            );
            Route::post(
                '/',
                [AdminFinalOperationsApiController::class, 'saveCollection']
            );
            Route::delete(
                '{id}',
                [AdminFinalOperationsApiController::class, 'deleteCollection']
            );
        });

        Route::prefix('subscription-plans')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'subscriptionPlans']
            );
            Route::post(
                '/',
                [AdminFinalOperationsApiController::class, 'saveSubscriptionPlan']
            );
        });

        Route::get(
            'seller-subscriptions',
            [AdminFinalOperationsApiController::class, 'sellerSubscriptions']
        );

        Route::prefix('advertisements')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'adCampaigns']
            );
            Route::post(
                '{id}/approve',
                [AdminFinalOperationsApiController::class, 'approveAd']
            );
            Route::post(
                '{id}/reject',
                [AdminFinalOperationsApiController::class, 'rejectAd']
            );
        });

        Route::prefix('delivery-cash')->group(function (): void {
            Route::get(
                '/',
                [AdminFinalOperationsApiController::class, 'cashTransactions']
            );
            Route::post(
                '{id}/process',
                [AdminFinalOperationsApiController::class, 'processCashTransaction']
            );
        });

        Route::get(
            'feedback',
            [AdminFinalOperationsApiController::class, 'feedback']
        );
        Route::post(
            'feedback/sellers/{id}/moderate',
            [AdminFinalOperationsApiController::class, 'moderateSellerFeedback']
        );
        Route::post(
            'feedback/delivery/{id}/moderate',
            [AdminFinalOperationsApiController::class, 'moderateDeliveryFeedback']
        );

        Route::get(
            'pos/dashboard',
            [AdminFinalOperationsApiController::class, 'posDashboard']
        );

        Route::get(
            'payment-operations',
            [AdminFinalOperationsApiController::class, 'paymentOperations']
        );

        Route::prefix('bulk-uploads')->group(function (): void {
            Route::get(
                '/',
                [AdminBulkUploadApiController::class, 'index']
            );
            Route::get(
                'templates/{type}',
                [AdminBulkUploadApiController::class, 'template']
            );
            Route::post(
                'import',
                [AdminBulkUploadApiController::class, 'import']
            )->middleware('throttle:10,1');
            Route::post(
                'export',
                [AdminBulkUploadApiController::class, 'export']
            )->middleware('throttle:10,1');
            Route::post(
                '{uuid}/process',
                [AdminBulkUploadApiController::class, 'process']
            );
            Route::get(
                '{uuid}',
                [AdminBulkUploadApiController::class, 'show']
            );
            Route::get(
                '{uuid}/download',
                [AdminBulkUploadApiController::class, 'download']
            );
            Route::get(
                '{uuid}/errors',
                [AdminBulkUploadApiController::class, 'downloadErrors']
            );
        });

        Route::get(
            'command-runs',
            [AdminFinalOperationsApiController::class, 'commandLogs']
        );
        Route::post(
            'command-runs',
            [AdminFinalOperationsApiController::class, 'runCommand']
        )->middleware('throttle:5,1');

        Route::get(
            'system-releases',
            [AdminFinalOperationsApiController::class, 'releases']
        );
        Route::post(
            'system-releases',
            [AdminFinalOperationsApiController::class, 'saveRelease']
        );
    });
});
