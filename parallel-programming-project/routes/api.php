<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['throttle:6000', 'benchmark'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/productsredis/{product}', [ProductController::class, 'showRedis']);

    Route::get('/cart', [CartController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/cart', [CartController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/cart/{cart}', [CartController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/orders', [OrderController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/orders/cart', [OrderController::class, 'orderCart'])->middleware('auth:sanctum');
    Route::post('/orders/cartredis', [OrderController::class, 'orderCart'])->middleware('auth:sanctum');
    Route::post('/orders/cartseq', [OrderController::class, 'ordercartsequential'])->middleware('auth:sanctum');
    Route::post('/orders/cartenh', [OrderController::class, 'ordercartenhanced'])->middleware('auth:sanctum');
    Route::post('/orders/product', [OrderController::class, 'orderProduct'])->middleware('auth:sanctum');
    Route::post('/orders/productredis', [OrderController::class, 'orderProduct'])->middleware('auth:sanctum');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth:sanctum');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('auth:sanctum');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->middleware('auth:sanctum');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('auth:sanctum');

    Route::middleware('admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });
});
