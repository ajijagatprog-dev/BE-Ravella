<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\VoucherController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/register-b2b', [AuthController::class, 'registerB2b']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);

Route::post('/payments/webhook', [\App\Http\Controllers\Api\PaymentController::class, 'simulateWebhook']);

// Voucher validation (public)
Route::get('/vouchers/active', [VoucherController::class, 'active']);
Route::get('/vouchers/validate', [VoucherController::class, 'check']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Product management (Admin)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // User management (Admin)
    Route::get('/admin/users/stats', [UserController::class, 'getUserStats']);
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::get('/admin/users/{id}', [UserController::class, 'getUserDetail']);
    Route::put('/admin/users/{id}', [UserController::class, 'updateUser']);
    Route::get('/users', [UserController::class, 'index']);

    // News management (Admin)
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Order management (Admin)
    Route::get('/admin/orders/stats', [OrderController::class, 'getOrderStats']);
    Route::get('/admin/orders', [OrderController::class, 'getAllOrders']);
    Route::get('/admin/orders/{order_number}', [OrderController::class, 'getAdminOrderDetail']);
    Route::put('/admin/orders/{order_number}/status', [OrderController::class, 'updateOrderStatus']);

    // Admin Loyalty
    Route::get('/admin/loyalty', [LoyaltyController::class, 'getAdminLoyaltyData']);
    Route::get('/admin/loyalty/settings', [LoyaltyController::class, 'getSettings']);
    Route::put('/admin/loyalty/settings', [LoyaltyController::class, 'updateSettings']);
    Route::get('/admin/loyalty/tiers', [LoyaltyController::class, 'getTiers']);
    Route::put('/admin/loyalty/tiers', [LoyaltyController::class, 'updateTiers']);

    // Admin Dashboard
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);

    // Admin Reports
    Route::get('/admin/reports/sales', [ReportController::class, 'salesReport']);
    Route::get('/admin/reports/customers', [ReportController::class, 'customerReport']);
    Route::get('/admin/reports/stock', [ReportController::class, 'stockReport']);
    Route::get('/admin/reports/transactions', [ReportController::class, 'transactionReport']);

    // Voucher management (Admin)
    Route::get('/admin/vouchers', [VoucherController::class, 'index']);
    Route::post('/admin/vouchers', [VoucherController::class, 'store']);
    Route::put('/admin/vouchers/{id}', [VoucherController::class, 'update']);
    Route::delete('/admin/vouchers/{id}', [VoucherController::class, 'destroy']);

    // Export Routes
    Route::get('/admin/export/users', [ReportController::class, 'exportUsers']);
    Route::get('/admin/export/orders', [ReportController::class, 'exportOrders']);
    Route::get('/admin/export/products', [ReportController::class, 'exportProducts']);
    Route::get('/customer/export/orders', [ReportController::class, 'exportOrders']);

    // Customer Portal
    Route::get('/customer/profile', [CustomerController::class, 'getProfile']);
    Route::put('/customer/profile', [CustomerController::class, 'updateProfile']);

    Route::get('/customer/addresses', [CustomerController::class, 'getAddresses']);
    Route::post('/customer/addresses', [CustomerController::class, 'addAddress']);
    Route::put('/customer/addresses/{id}', [CustomerController::class, 'updateAddress']);
    Route::delete('/customer/addresses/{id}', [CustomerController::class, 'deleteAddress']);
    Route::put('/customer/addresses/{id}/primary', [CustomerController::class, 'setPrimaryAddress']);

    Route::get('/customer/orders', [OrderController::class, 'getUserOrders']);
    Route::post('/customer/orders', [OrderController::class, 'createOrder']);
    Route::get('/customer/orders/{order_number}', [OrderController::class, 'getOrderDetail']);

    // Customer Loyalty
    Route::get('/customer/loyalty', [LoyaltyController::class, 'getLoyaltyData']);
});

 