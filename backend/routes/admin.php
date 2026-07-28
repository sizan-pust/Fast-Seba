<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminCoreResourceController;
use App\Http\Controllers\Admin\AdminEngagementFinanceController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Middleware\EnsureAdminSession;
use App\Services\Admin\AdminCoreResourceService;
use App\Services\Admin\AdminEngagementFinanceService;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');
    Route::get('forgot-password', [AdminAuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('forgot-password', [AdminAuthController::class, 'sendResetLink'])->middleware('throttle:4,1')->name('password.email');
    Route::get('reset-password/{token}', [AdminAuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])->name('password.update');

    Route::middleware(EnsureAdminSession::class)->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

        foreach (AdminCoreResourceService::LIVE_MODULES as $module) {
            Route::get($module, [AdminCoreResourceController::class, 'index'])
                ->defaults('module', $module)
                ->name('core.'.$module.'.index');
            Route::get($module.'/{id}', [AdminCoreResourceController::class, 'show'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('core.'.$module.'.show');
            Route::post($module.'/{id}/state', [AdminCoreResourceController::class, 'updateState'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('core.'.$module.'.state');
        }

        Route::post('orders/{id}/assign-delivery-partner', [AdminCoreResourceController::class, 'assignOrder'])
            ->whereNumber('id')->name('orders.assign-rider');
        Route::post('returns/{id}/assign-delivery-partner', [AdminCoreResourceController::class, 'assignReturn'])
            ->whereNumber('id')->name('returns.assign-rider');
        Route::post('returns/{id}/refund', [AdminCoreResourceController::class, 'refundReturn'])
            ->whereNumber('id')->name('returns.refund');
        Route::post('prescriptions/{id}/review', [AdminCoreResourceController::class, 'reviewPrescription'])
            ->whereNumber('id')->name('prescriptions.review');

        foreach (AdminEngagementFinanceService::LIVE_MODULES as $module) {
            Route::get($module, [AdminEngagementFinanceController::class, 'index'])
                ->defaults('module', $module)
                ->name('manage.'.$module.'.index');
            Route::get($module.'/create', [AdminEngagementFinanceController::class, 'create'])
                ->defaults('module', $module)
                ->name('manage.'.$module.'.create');
            Route::post($module, [AdminEngagementFinanceController::class, 'store'])
                ->defaults('module', $module)
                ->name('manage.'.$module.'.store');
            Route::post($module.'/page-action', [AdminEngagementFinanceController::class, 'pageAction'])
                ->defaults('module', $module)
                ->name('manage.'.$module.'.page-action');
            Route::get($module.'/{id}', [AdminEngagementFinanceController::class, 'show'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('manage.'.$module.'.show');
            Route::get($module.'/{id}/edit', [AdminEngagementFinanceController::class, 'edit'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('manage.'.$module.'.edit');
            Route::put($module.'/{id}', [AdminEngagementFinanceController::class, 'update'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('manage.'.$module.'.update');
            Route::delete($module.'/{id}', [AdminEngagementFinanceController::class, 'destroy'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('manage.'.$module.'.destroy');
            Route::post($module.'/{id}/action', [AdminEngagementFinanceController::class, 'action'])
                ->defaults('module', $module)
                ->whereNumber('id')
                ->name('manage.'.$module.'.action');
        }

        Route::get('module/{module}', [AdminModuleController::class, 'show'])
            ->where('module', '[a-z0-9\-]+')
            ->name('module');
    });
});
