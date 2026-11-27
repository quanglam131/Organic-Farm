<?php

use App\Http\Controllers\Api\Admin\AlertController;
use App\Http\Controllers\Api\Admin\BatchController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\FarmLogController;
use App\Http\Controllers\Api\Admin\FarmPlotController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\SubscriptionPlanController as AdminSubscriptionPlanController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PointController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TraceController;
use Illuminate\Support\Facades\Route;

/*
| Access control (Tuần 2 actors, Tuần 7 endpoint audit)
|   public      catalogue, farm info, QR trace page, shipping estimate, payment callbacks
|   customer    cart, checkout, own orders, reviews, subscriptions, complaints   (any signed-in user)
|   staff       farm plots, farming diary, batches, QR codes, waste              (never orders/customers/revenue)
|   sales       orders, shipping status, complaints, subscription deliveries     (never creates batches or changes stock)
|   admin       everything above + reports, products, categories, staff accounts, settings
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// ----- Public -----
Route::get('farm', [FarmController::class, 'show']);
Route::get('categories', [CatalogController::class, 'categories']);
Route::get('products', [CatalogController::class, 'products']);
Route::get('products/{product:slug}', [CatalogController::class, 'show']);
Route::get('subscription-plans', [CatalogController::class, 'subscriptionPlans']);
Route::get('trace/{traceCode}', [TraceController::class, 'show']);
Route::get('shipping/estimate', [CheckoutController::class, 'estimate'])->middleware('throttle:30,1');

// Payment callbacks from VNPay (signature is verified in the controller).
Route::get('payments/vnpay/return', [PaymentController::class, 'vnpayReturn']);
Route::get('payments/vnpay/ipn', [PaymentController::class, 'vnpayIpn']);

Route::middleware('auth:sanctum')->group(function () {
    // ----- Any signed-in user (customers) -----
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::put('me', [AuthController::class, 'updateProfile']);

    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::patch('cart/{product}', [CartController::class, 'update']);
    Route::delete('cart/{product}', [CartController::class, 'destroy']);

    Route::post('checkout', [CheckoutController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{code}', [OrderController::class, 'show']);
    Route::post('orders/{code}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{code}/reviews', [ReviewController::class, 'store']);

    Route::get('payments/mock/{code}', [PaymentController::class, 'mockShow']);
    Route::post('payments/mock/{code}/complete', [PaymentController::class, 'mockComplete']);

    Route::get('points', [PointController::class, 'index']);

    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions/quote', [SubscriptionController::class, 'quote']);
    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update']);

    Route::get('complaints', [ComplaintController::class, 'index']);
    Route::post('complaints', [ComplaintController::class, 'store']);

    // ----- Back office -----
    Route::prefix('admin')->group(function () {
        // Staff, Sales and Admin
        Route::middleware('role:staff,sales')->group(function () {
            Route::get('alerts', [AlertController::class, 'index']);
            Route::get('products', [AdminProductController::class, 'index']);
        });

        // Farm staff (+ admin): crops, diary, batches, QR, waste
        Route::middleware('role:staff')->group(function () {
            Route::get('farm-plots', [FarmPlotController::class, 'index']);
            Route::get('farm-logs', [FarmLogController::class, 'index']);
            Route::post('farm-logs', [FarmLogController::class, 'store']);
            Route::delete('farm-logs/{farmLog}', [FarmLogController::class, 'destroy']);

            Route::get('batches', [BatchController::class, 'all']);
            Route::get('batches/{batch}', [BatchController::class, 'show'])->whereNumber('batch');
            Route::get('products/{product}/batches', [BatchController::class, 'index']);
            Route::post('products/{product}/batches', [BatchController::class, 'store']);
            Route::get('products/{product}/fefo-preview', [BatchController::class, 'fefoPreview']);
            Route::post('batches/{batch}/waste', [BatchController::class, 'waste']);
            Route::post('batches/expire-due', [BatchController::class, 'expireDue']);
            Route::get('batches/{batch}/qr', [BatchController::class, 'qr']);
        });

        // Sales / customer care (+ admin): orders, complaints, subscription deliveries
        Route::middleware('role:sales')->group(function () {
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

            Route::get('complaints', [AdminComplaintController::class, 'index']);
            Route::patch('complaints/{complaint}', [AdminComplaintController::class, 'update']);

            Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::get('delivery-schedules', [AdminSubscriptionController::class, 'schedules']);
            Route::post('delivery-schedules/{schedule}/retry', [AdminSubscriptionController::class, 'retry']);
        });

        // Administrators only
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'summary']);
            Route::get('reports/revenue', [DashboardController::class, 'revenue']);
            Route::get('reports/waste', [DashboardController::class, 'waste']);

            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            Route::apiResource('categories', CategoryController::class)->except(['show']);

            Route::apiResource('products', AdminProductController::class)->except(['index', 'show']);
            Route::get('products/{product}/images', [ProductImageController::class, 'index']);
            Route::post('products/{product}/images', [ProductImageController::class, 'store']);
            Route::patch('product-images/{image}', [ProductImageController::class, 'update']);
            Route::delete('product-images/{image}', [ProductImageController::class, 'destroy']);

            Route::post('farm-plots', [FarmPlotController::class, 'store']);
            Route::put('farm-plots/{farm_plot}', [FarmPlotController::class, 'update']);
            Route::patch('farm-plots/{farm_plot}', [FarmPlotController::class, 'update']);
            Route::delete('farm-plots/{farm_plot}', [FarmPlotController::class, 'destroy']);

            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);

            Route::apiResource('subscription-plans', AdminSubscriptionPlanController::class)->except(['show']);
        });
    });
});
