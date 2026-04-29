<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;

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

use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\BakongPaymentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('product-types', ProductTypeController::class);
Route::apiResource('products', ProductController::class);

Route::get('menu', [MenuController::class, 'index']);

// Public auth routes
Route::post('/login', [AuthController::class, 'login']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Admin-only routes
Route::middleware('auth:sanctum', 'role:admin')->group(function () {
    Route::get('/reports', [ReportsController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});

// Staff and Admin routes
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
});

Route::post('/orders/{order}/bakong/intent', [BakongPaymentController::class, 'intent']);
Route::post('/orders/{order}/bakong/khqr', [OrderPaymentController::class, 'khqr']);
Route::get('/orders/{order}/bakong/status', [BakongPaymentController::class, 'status']);
