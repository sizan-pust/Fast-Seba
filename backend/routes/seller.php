<?php

use App\Http\Controllers\Seller\SellerAuthController;
use App\Http\Controllers\Seller\SellerDashboardController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\SellerResourceController;
use App\Http\Middleware\EnsureSellerSession;
use App\Services\Seller\SellerPanelResourceService;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->group(function (): void {
    Route::get('login', [SellerAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [SellerAuthController::class, 'login'])->name('login.attempt');
    Route::get('register', [SellerAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [SellerAuthController::class, 'register'])->name('register.store');
    Route::get('forgot-password', [SellerAuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('forgot-password', [SellerAuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [SellerAuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('reset-password', [SellerAuthController::class, 'resetPassword'])->name('password.update');

    Route::middleware(EnsureSellerSession::class)->group(function (): void {
        Route::get('/', fn () => redirect()->route('seller.dashboard'))->name('home');
        Route::get('dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [SellerProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [SellerProfileController::class, 'update'])->name('profile.update');
        Route::put('password', [SellerProfileController::class, 'updatePassword'])->name('password.change');
        Route::post('logout', [SellerAuthController::class, 'logout'])->name('logout');

        foreach (SellerPanelResourceService::LIVE_MODULES as $module) {
            Route::prefix($module)
                ->name('resource.'.$module.'.')
                ->group(function () use ($module): void {
                    Route::get('/', [SellerResourceController::class, 'index'])->name('index')->defaults('module', $module);
                    Route::get('create', [SellerResourceController::class, 'create'])->name('create')->defaults('module', $module);
                    Route::post('/', [SellerResourceController::class, 'store'])->name('store')->defaults('module', $module);
                    Route::post('page-action', [SellerResourceController::class, 'pageAction'])->name('page-action')->defaults('module', $module);
                    Route::get('{id}', [SellerResourceController::class, 'show'])->name('show')->defaults('module', $module);
                    Route::get('{id}/edit', [SellerResourceController::class, 'edit'])->name('edit')->defaults('module', $module);
                    Route::put('{id}', [SellerResourceController::class, 'update'])->name('update')->defaults('module', $module);
                    Route::delete('{id}', [SellerResourceController::class, 'destroy'])->name('destroy')->defaults('module', $module);
                    Route::post('{id}/action', [SellerResourceController::class, 'action'])->name('action')->defaults('module', $module);
                });
        }
    });
});
