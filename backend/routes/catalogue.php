<?php

use App\Http\Controllers\Api\BrandApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductSidebarApiController;
use App\Http\Controllers\Api\StoreApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->name('categories.')->group(
    function (): void {
        Route::get('/', [CategoryApiController::class, 'index']);
        Route::get(
            'sub-categories',
            [CategoryApiController::class, 'subCategories']
        );
        Route::get(
            'sidebar',
            [CategoryApiController::class, 'sidebar']
        );
        Route::get(
            'search',
            [CategoryApiController::class, 'search']
        )->name('search');
    }
);

Route::prefix('brands')->name('brands.')->group(
    function (): void {
        Route::get('/', [BrandApiController::class, 'index']);
        Route::get(
            'sidebar',
            [BrandApiController::class, 'sidebar']
        );
        Route::get(
            'search',
            [BrandApiController::class, 'search']
        )->name('search');
    }
);

Route::prefix('products')->name('products.')->group(
    function (): void {
        Route::get(
            'sidebar-filters',
            [ProductSidebarApiController::class, 'filters']
        );
        Route::get(
            'get-types',
            [ProductSidebarApiController::class, 'getTypes']
        );
        Route::get(
            'search-by-keywords',
            [ProductApiController::class, 'searchByKeywords']
        );
        Route::get(
            'store-wise',
            [ProductApiController::class, 'storeWise']
        );
        Route::get(
            'search',
            [ProductApiController::class, 'search']
        )->name('search');
        Route::get(
            '{slug}',
            [ProductApiController::class, 'show']
        );
    }
);

Route::prefix('stores')->name('stores.')->group(
    function (): void {
        Route::get('/', [StoreApiController::class, 'index']);
        Route::get(
            'search',
            [StoreApiController::class, 'search']
        )->name('search');
        Route::get(
            '{slug}',
            [StoreApiController::class, 'show']
        );
    }
);

Route::post(
    'stores/map',
    [StoreApiController::class, 'map']
);

Route::get(
    'delivery-zone/products',
    [ProductApiController::class, 'index']
);

Route::get(
    'delivery-zone/stores',
    [StoreApiController::class, 'location']
);