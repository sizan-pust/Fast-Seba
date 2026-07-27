<?php
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Seller\SellerAuthApiController;
use App\Http\Controllers\Api\Seller\SellerOrderApiController;
use App\Http\Controllers\Api\User\OrderApiController;
use App\Http\Controllers\Api\User\WalletApiController;
use Illuminate\Support\Facades\Route;

Route::get('payment/variables',[PaymentController::class,'paymentVariables']);
Route::prefix('seller')->group(function():void{
    Route::post('login',[SellerAuthApiController::class,'login']);
});
Route::middleware('auth:sanctum')->group(function():void{
    Route::post('orders',[OrderApiController::class,'createOrder']);
    Route::prefix('user')->group(function():void{
        Route::prefix('orders')->group(function():void{
            Route::get('/',[OrderApiController::class,'getUserOrders']);
            Route::post('/',[OrderApiController::class,'createOrder']);
            Route::post('/items/{orderItemId}/cancel',[OrderApiController::class,'cancelOrderItem']);
            Route::post('/items/{orderItemId}/return',[OrderApiController::class,'returnOrderItem']);
            Route::post('/items/{orderItemId}/return-cancel',[OrderApiController::class,'cancelReturnRequest']);
            Route::post('/{orderId}/reorder',[OrderApiController::class,'reorder']);
            Route::get('/{orderSlug}/delivery-boy-location',[OrderApiController::class,'getOrderDeliveryBoyLocation']);
            Route::get('/{orderSlug}',[OrderApiController::class,'getOrder']);
        });
        Route::prefix('order-transactions')->group(function():void{
            Route::get('/',[OrderApiController::class,'getTransactions']);
            Route::get('/{id}',[OrderApiController::class,'getTransaction']);
        });
        Route::prefix('wallet')->group(function():void{
            Route::get('/',[WalletApiController::class,'getWallet']);
            Route::post('/prepare-wallet-recharge',[WalletApiController::class,'prepareWalletRecharge']);
            Route::post('/deduct-balance',[WalletApiController::class,'deductBalance']);
            Route::get('/transactions',[WalletApiController::class,'getTransactions']);
            Route::get('/transactions/{id}',[WalletApiController::class,'getTransaction']);
        });
    });
    Route::prefix('seller')->group(function():void{
        Route::post('logout',[SellerAuthApiController::class,'logout']);
        Route::get('orders',[SellerOrderApiController::class,'index']);
        Route::get('orders/enums',[SellerOrderApiController::class,'enums']);
        Route::get('orders/{id}',[SellerOrderApiController::class,'show']);
        Route::post('order-items/{itemId}/status',[SellerOrderApiController::class,'updateItemStatus']);
    });
});
