<?php

use App\Http\Controllers\Api\DeliveryZoneApiController;
use App\Http\Controllers\Api\SettingApiController;
use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Controllers\Api\User\OtpApiController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthApiController::class, 'register'])
    ->name('register');

Route::post('login', [AuthApiController::class, 'login'])
    ->name('login');

Route::post(
    'forget-password',
    [AuthApiController::class, 'forgotPassword']
)->name('password');

Route::post(
    'verify-user',
    [AuthApiController::class, 'verifyUser']
);

Route::post(
    'auth/send-otp',
    [OtpApiController::class, 'sendOtp']
)->name('send-otp');

Route::post(
    'auth/verify-otp',
    [OtpApiController::class, 'verifyOtp']
)->name('verify-otp');

Route::post(
    'auth/google/callback',
    [AuthApiController::class, 'googleCallback']
)->name('google-callback');

Route::post(
    'auth/apple/callback',
    [AuthApiController::class, 'appleCallback']
)->name('apple-callback');

Route::post(
    'auth/phone/callback',
    [AuthApiController::class, 'phoneCallback']
)->name('phone-callback');

Route::prefix('settings')->name('api.')->group(function (): void {
    Route::get('/', [SettingApiController::class, 'index'])
        ->name('settings.index');

    Route::get(
        'firebase-config',
        [SettingApiController::class, 'firebaseConfig']
    )->name('settings.firebase-config');

    Route::get(
        'check-version',
        [SettingApiController::class, 'checkVersion']
    )->name('settings.check-version');

    Route::get(
        'variables',
        [SettingApiController::class, 'settingVariables']
    )->name('settings.variables');

    Route::get(
        '{setting}',
        [SettingApiController::class, 'show']
    )->name('settings.show');
});

require __DIR__.'/catalogue.php';

Route::prefix('delivery-zone')
    ->name('delivery_zone.')
    ->group(function (): void {
        Route::get('/', [DeliveryZoneApiController::class, 'index']);
        Route::get(
            'check',
            [DeliveryZoneApiController::class, 'checkDelivery']
        );
        Route::get(
            'search',
            [DeliveryZoneApiController::class, 'search']
        )->name('search');
        Route::get(
            '{id}',
            [DeliveryZoneApiController::class, 'show']
        );
    });

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthApiController::class, 'logout']);

    Route::post(
        'devices/sync',
        [DeviceTokenController::class, 'sync']
    );

    Route::delete(
        'devices',
        [DeviceTokenController::class, 'forget']
    );

    Route::prefix('user')->name('user.')->group(function (): void {
        Route::get(
            'profile',
            [UserApiController::class, 'getProfile']
        );

        Route::post(
            'profile',
            [UserApiController::class, 'updateProfile']
        );

        Route::post(
            'change-password',
            [UserApiController::class, 'changePassword']
        )->name('change-password');

        Route::post(
            'update-email',
            [UserApiController::class, 'updateEmail']
        )->name('update-email');

        Route::post(
            'email/verification-notification',
            [UserApiController::class, 'resendEmailVerification']
        )->middleware('throttle:6,1')
            ->name('verification.send');

        Route::delete(
            'delete-account',
            [UserApiController::class, 'deleteAccount']
        )->name('delete-account');
    });
});