<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(function () {

    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', 'register')->name('register');
            Route::post('login', 'login')->name('login');
            // Outside auth:api on purpose: an expired token may still be refreshed.
            Route::post('refresh', 'refresh')->name('refresh');
        });

        Route::middleware('auth:api')->group(function () {
            Route::get('me', 'me')->name('me');
            Route::post('logout', 'logout')->name('logout');
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::apiResource('orders', OrderController::class);

        Route::get('orders/{order}/payments', [OrderPaymentController::class, 'index'])->name('orders.payments.index');
        Route::post('orders/{order}/payments', [OrderPaymentController::class, 'store'])->name('orders.payments.store');

        Route::apiResource('payments', PaymentController::class)->only(['index', 'show']);

        Route::get('payment-methods', PaymentMethodController::class)->name('payment-methods.index');
    });
});
