<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;

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

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Product management (Admin)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // User management (Admin)
    Route::get('/users', [UserController::class, 'index']);

    // News management (Admin)
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Order management (Admin)
    Route::get('/admin/orders', [OrderController::class, 'getAllOrders']);
    Route::get('/admin/orders/{order_number}', [OrderController::class, 'getAdminOrderDetail']);
    Route::put('/admin/orders/{order_number}/status', [OrderController::class, 'updateOrderStatus']);

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
});

 