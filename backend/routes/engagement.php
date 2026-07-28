<?php

use App\Http\Controllers\Api\Admin\AdminGrowthApiController;
use App\Http\Controllers\Api\Admin\AdminSupportPharmacyApiController;
use App\Http\Controllers\Api\GrowthPublicApiController;
use App\Http\Controllers\Api\Seller\SellerEngagementApiController;
use App\Http\Controllers\Api\User\UserEngagementApiController;
use Illuminate\Support\Facades\Route;

Route::get('banners', [GrowthPublicApiController::class, 'banners']);
Route::get('featured-sections', [GrowthPublicApiController::class, 'featuredSections']);
Route::get(
    'featured-sections/{slug}',
    [GrowthPublicApiController::class, 'featuredSection']
);
Route::get('faqs', [GrowthPublicApiController::class, 'faqs']);
Route::get(
    'products/{slug}/faqs',
    [GrowthPublicApiController::class, 'productFaqs']
);
Route::get(
    'products/{slug}/reviews',
    [GrowthPublicApiController::class, 'productReviews']
);
Route::post(
    'gift-cards/validate',
    [GrowthPublicApiController::class, 'validateGiftCard']
)->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('user')->group(function (): void {
        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [UserEngagementApiController::class, 'notifications']);
            Route::get(
                'unread-count',
                [UserEngagementApiController::class, 'unreadCount']
            );
            Route::post(
                'mark-all-read',
                [UserEngagementApiController::class, 'markAllRead']
            );
            Route::post(
                '{id}/state',
                [UserEngagementApiController::class, 'markNotification']
            );
        });

        Route::prefix('reviews')->group(function (): void {
            Route::get(
                'available',
                [UserEngagementApiController::class, 'availableReviews']
            );
            Route::post(
                '/',
                [UserEngagementApiController::class, 'createReview']
            )->middleware('throttle:10,1');
            Route::put(
                '{id}',
                [UserEngagementApiController::class, 'updateReview']
            );
            Route::delete(
                '{id}',
                [UserEngagementApiController::class, 'deleteReview']
            );
        });

        Route::post(
            'products/{slug}/questions',
            [UserEngagementApiController::class, 'askProductQuestion']
        )->middleware('throttle:10,1');

        Route::get(
            'support-ticket-types',
            [UserEngagementApiController::class, 'supportTypes']
        );

        Route::prefix('support-tickets')->group(function (): void {
            Route::get(
                '/',
                [UserEngagementApiController::class, 'supportTickets']
            );
            Route::post(
                '/',
                [UserEngagementApiController::class, 'createSupportTicket']
            )->middleware('throttle:10,1');
            Route::get(
                '{uuid}',
                [UserEngagementApiController::class, 'supportTicket']
            );
            Route::post(
                '{uuid}/reply',
                [UserEngagementApiController::class, 'replySupportTicket']
            )->middleware('throttle:20,1');
            Route::post(
                '{uuid}/close',
                [UserEngagementApiController::class, 'closeSupportTicket']
            );
        });

        Route::prefix('prescriptions')->group(function (): void {
            Route::get(
                '/',
                [UserEngagementApiController::class, 'prescriptions']
            );
            Route::post(
                '/',
                [UserEngagementApiController::class, 'createPrescription']
            )->middleware('throttle:6,1');
            Route::get(
                '{uuid}',
                [UserEngagementApiController::class, 'prescription']
            );
            Route::post(
                '{uuid}/cancel',
                [UserEngagementApiController::class, 'cancelPrescription']
            );
        });

        Route::prefix('referral')->group(function (): void {
            Route::get(
                '/',
                [UserEngagementApiController::class, 'referralInfo']
            );
            Route::post(
                'submit',
                [UserEngagementApiController::class, 'submitReferral']
            )->middleware('throttle:5,1');
            Route::get(
                'earnings',
                [UserEngagementApiController::class, 'referralEarnings']
            );
            Route::post(
                'dismiss-prompt',
                [UserEngagementApiController::class, 'dismissReferralPrompt']
            );
        });

        Route::prefix('gift-cards')->group(function (): void {
            Route::post(
                'redeem',
                [UserEngagementApiController::class, 'redeemGiftCard']
            )->middleware('throttle:5,1');
            Route::get(
                'history',
                [UserEngagementApiController::class, 'giftCardHistory']
            );
        });
    });

    Route::prefix('seller')->group(function (): void {
        Route::prefix('notifications')->group(function (): void {
            Route::get(
                '/',
                [SellerEngagementApiController::class, 'notifications']
            );
            Route::get(
                'unread-count',
                [SellerEngagementApiController::class, 'notificationCount']
            );
            Route::post(
                'mark-all-read',
                [SellerEngagementApiController::class, 'markAllNotificationsRead']
            );
            Route::post(
                '{id}/state',
                [SellerEngagementApiController::class, 'markNotification']
            );
        });

        Route::get(
            'reviews',
            [SellerEngagementApiController::class, 'reviews']
        );
        Route::post(
            'reviews/{id}/reply',
            [SellerEngagementApiController::class, 'replyReview']
        );

        Route::prefix('product-faqs')->group(function (): void {
            Route::get(
                '/',
                [SellerEngagementApiController::class, 'productFaqs']
            );
            Route::post(
                '/',
                [SellerEngagementApiController::class, 'createProductFaq']
            );
            Route::post(
                '{id}/answer',
                [SellerEngagementApiController::class, 'answerProductFaq']
            );
            Route::delete(
                '{id}',
                [SellerEngagementApiController::class, 'deleteProductFaq']
            );
        });

        Route::get(
            'prescriptions',
            [SellerEngagementApiController::class, 'prescriptions']
        );
        Route::post(
            'prescriptions/{id}/fulfill',
            [SellerEngagementApiController::class, 'fulfillPrescription']
        );
    });

    Route::prefix('admin')->group(function (): void {
        Route::get(
            'engagement/dashboard',
            [AdminGrowthApiController::class, 'dashboard']
        );
        Route::get(
            'support-pharmacy/dashboard',
            [AdminSupportPharmacyApiController::class, 'dashboard']
        );

        Route::prefix('banners')->group(function (): void {
            Route::get(
                '/',
                [AdminGrowthApiController::class, 'banners']
            );
            Route::post(
                '/',
                [AdminGrowthApiController::class, 'createBanner']
            );
            Route::post(
                '{id}',
                [AdminGrowthApiController::class, 'updateBanner']
            );
            Route::delete(
                '{id}',
                [AdminGrowthApiController::class, 'deleteBanner']
            );
        });

        Route::prefix('featured-sections')->group(function (): void {
            Route::get(
                '/',
                [AdminGrowthApiController::class, 'featuredSections']
            );
            Route::post(
                '/',
                [AdminGrowthApiController::class, 'createFeaturedSection']
            );
            Route::post(
                '{id}',
                [AdminGrowthApiController::class, 'updateFeaturedSection']
            );
            Route::delete(
                '{id}',
                [AdminGrowthApiController::class, 'deleteFeaturedSection']
            );
        });

        Route::prefix('faqs')->group(function (): void {
            Route::get(
                '/',
                [AdminGrowthApiController::class, 'faqs']
            );
            Route::post(
                '/',
                [AdminGrowthApiController::class, 'createFaq']
            );
            Route::put(
                '{id}',
                [AdminGrowthApiController::class, 'updateFaq']
            );
            Route::delete(
                '{id}',
                [AdminGrowthApiController::class, 'deleteFaq']
            );
        });

        Route::get(
            'notification-campaigns',
            [AdminGrowthApiController::class, 'campaigns']
        );
        Route::post(
            'notification-campaigns/broadcast',
            [AdminGrowthApiController::class, 'broadcast']
        )->middleware('throttle:5,1');

        Route::prefix('gift-cards')->group(function (): void {
            Route::get(
                '/',
                [AdminGrowthApiController::class, 'giftCards']
            );
            Route::post(
                '/',
                [AdminGrowthApiController::class, 'createGiftCard']
            );
            Route::put(
                '{id}',
                [AdminGrowthApiController::class, 'updateGiftCard']
            );
        });

        Route::prefix('support-ticket-types')->group(function (): void {
            Route::get(
                '/',
                [AdminSupportPharmacyApiController::class, 'supportTypes']
            );
            Route::post(
                '/',
                [AdminSupportPharmacyApiController::class, 'createSupportType']
            );
            Route::put(
                '{id}',
                [AdminSupportPharmacyApiController::class, 'updateSupportType']
            );
        });

        Route::prefix('support-tickets')->group(function (): void {
            Route::get(
                '/',
                [AdminSupportPharmacyApiController::class, 'supportTickets']
            );
            Route::get(
                '{id}',
                [AdminSupportPharmacyApiController::class, 'supportTicket']
            );
            Route::post(
                '{id}/reply',
                [AdminSupportPharmacyApiController::class, 'replySupportTicket']
            );
            Route::put(
                '{id}/status',
                [AdminSupportPharmacyApiController::class, 'updateSupportTicket']
            );
        });

        Route::prefix('prescriptions')->group(function (): void {
            Route::get(
                '/',
                [AdminSupportPharmacyApiController::class, 'prescriptions']
            );
            Route::get(
                '{id}',
                [AdminSupportPharmacyApiController::class, 'prescription']
            );
            Route::post(
                '{id}/review',
                [AdminSupportPharmacyApiController::class, 'reviewPrescription']
            );
        });

        Route::prefix('reviews')->group(function (): void {
            Route::get(
                '/',
                [AdminSupportPharmacyApiController::class, 'reviews']
            );
            Route::post(
                '{id}/moderate',
                [AdminSupportPharmacyApiController::class, 'moderateReview']
            );
            Route::delete(
                '{id}',
                [AdminSupportPharmacyApiController::class, 'deleteReview']
            );
        });

        Route::prefix('product-faqs')->group(function (): void {
            Route::get(
                '/',
                [AdminSupportPharmacyApiController::class, 'productFaqs']
            );
            Route::post(
                '{id}/moderate',
                [AdminSupportPharmacyApiController::class, 'moderateProductFaq']
            );
        });

        Route::get(
            'referrals',
            [AdminSupportPharmacyApiController::class, 'referrals']
        );
        Route::get(
            'referral-earnings',
            [AdminSupportPharmacyApiController::class, 'referralEarnings']
        );
        Route::post(
            'referrals/sync',
            [AdminSupportPharmacyApiController::class, 'syncReferrals']
        );

        Route::get(
            'audit-logs',
            [AdminSupportPharmacyApiController::class, 'auditLogs']
        );
    });
});
