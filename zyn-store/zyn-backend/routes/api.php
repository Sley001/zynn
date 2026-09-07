<?php

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CheckoutPaymentController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentQrController as AdminPaymentQrController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\StoreSettingController as AdminStoreSettingController;
use App\Http\Controllers\Api\Admin\TelegramPaymentAlertController as AdminTelegramPaymentAlertController;
use App\Http\Controllers\Api\CheckoutSessionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\Telegram\PaymentWebhookController as TelegramPaymentWebhookController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('select 1');

    return response()->json([
        'status' => 'ok',
        'database' => DB::getDriverName(),
        'time' => now()->toISOString(),
    ]);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/store-settings', [StoreSettingController::class, 'index']);
Route::post('/checkout/sessions', [CheckoutSessionController::class, 'store'])
    ->middleware('throttle:checkout-sessions');
Route::get('/checkout/sessions/{checkoutSession:token}/status', [CheckoutSessionController::class, 'status']);
Route::post('/checkout/sessions/{checkoutSession:token}/claim-paid', [CheckoutSessionController::class, 'claimPaid'])
    ->middleware('throttle:checkout-claims');
Route::post('/checkout/sessions/{checkoutSession:token}/cancel', [CheckoutSessionController::class, 'cancel'])
    ->middleware('throttle:checkout-claims');
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
Route::post('/telegram/payments/webhook', TelegramPaymentWebhookController::class)
    ->middleware('throttle:telegram-webhook');

Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login');

Route::prefix('admin')->middleware(['auth:sanctum', 'abilities:admin'])->group(function (): void {
    Route::get('/payment-reviews', [CheckoutPaymentController::class, 'index']);
    Route::get('/checkout/sessions/{checkoutSession:token}/receipt', [CheckoutPaymentController::class, 'receipt']);
    Route::post('/checkout/sessions/{checkoutSession:token}/reject', [CheckoutPaymentController::class, 'reject']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::apiResource('products', AdminProductController::class);
    Route::apiResource('payment-qrs', AdminPaymentQrController::class)
        ->parameters(['payment-qrs' => 'paymentQr']);
    Route::get('/store-settings', [AdminStoreSettingController::class, 'index']);
    Route::patch('/store-settings', [AdminStoreSettingController::class, 'update']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update']);
    Route::get('/payment-alerts', [AdminTelegramPaymentAlertController::class, 'index']);
});
