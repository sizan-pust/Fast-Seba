<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get(
            'login',
            [AdminAuthController::class, 'showLogin']
        )->name('login');

        Route::post(
            'login',
            [AdminAuthController::class, 'login']
        )->middleware('throttle:8,1')
            ->name('login.store');

        Route::get(
            'forgot-password',
            [
                AdminAuthController::class,
                'showForgotPassword',
            ]
        )->name('password.request');

        Route::post(
            'forgot-password',
            [
                AdminAuthController::class,
                'sendResetLink',
            ]
        )->middleware('throttle:4,1')
            ->name('password.email');

        Route::get(
            'reset-password/{token}',
            [
                AdminAuthController::class,
                'showResetPassword',
            ]
        )->name('password.reset');

        Route::post(
            'reset-password',
            [
                AdminAuthController::class,
                'resetPassword',
            ]
        )->name('password.update');

        Route::middleware(
            EnsureAdminSession::class
        )->group(function (): void {
            Route::get(
                '/',
                fn () => redirect()
                    ->route('admin.dashboard')
            )->name('home');

            Route::get(
                'dashboard',
                [
                    AdminDashboardController::class,
                    'index',
                ]
            )->name('dashboard');

            Route::get(
                'profile',
                [
                    AdminProfileController::class,
                    'edit',
                ]
            )->name('profile.edit');

            Route::put(
                'profile',
                [
                    AdminProfileController::class,
                    'update',
                ]
            )->name('profile.update');

            Route::put(
                'profile/password',
                [
                    AdminProfileController::class,
                    'updatePassword',
                ]
            )->name('profile.password');

            Route::post(
                'logout',
                [
                    AdminAuthController::class,
                    'logout',
                ]
            )->name('logout');

            Route::get(
                'module/{module}',
                [
                    AdminModuleController::class,
                    'show',
                ]
            )->where(
                'module',
                '[a-z0-9\-]+'
            )->name('module');
        });
    });
