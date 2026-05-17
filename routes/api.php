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
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryModuleController;
use App\Http\Controllers\DiningTableController;

// Product and category management: staff + admin
// Public: allow listing products for the customer menu (no auth required)
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// Product and category management: staff + admin
Route::middleware('auth:sanctum', 'permission:products')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('product-types', ProductTypeController::class);
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    // Promotions management (percent, single-product scope)
    Route::apiResource('promotions', App\Http\Controllers\Api\PromotionController::class);
});

Route::get('menu', [MenuController::class, 'index']);

// Public auth routes
Route::post('/login', [AuthController::class, 'login']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Admin-only routes
Route::middleware('auth:sanctum', 'permission:reports')->group(function () {
    Route::get('/reports', [ReportsController::class, 'index']);
    Route::get('/reports/profit', [ReportsController::class, 'profit']);
});

Route::middleware('auth:sanctum', 'permission:users')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});

Route::middleware('auth:sanctum', 'permission:tables')->group(function () {
    Route::apiResource('tables', DiningTableController::class);
});

Route::middleware('auth:sanctum', 'permission:inventory')->group(function () {

    Route::prefix('inventory')->group(function () {
        Route::get('/dashboard', [InventoryModuleController::class, 'dashboard']);
        Route::get('/items', [InventoryController::class, 'index']);
        Route::post('/items', [InventoryController::class, 'store']);
        Route::patch('/items/{item}', [InventoryController::class, 'updateSettings']);
        Route::delete('/items/{item}', [InventoryController::class, 'destroy']);
        Route::get('/items/{item}/movements', [InventoryController::class, 'movements']);
        Route::post('/movements', [InventoryController::class, 'moveStock']);

        Route::post('/stock-in', [InventoryModuleController::class, 'stockIn']);
        Route::get('/stock-in-records', [InventoryModuleController::class, 'stockInRecords']);
        Route::post('/waste', [InventoryModuleController::class, 'waste']);
        Route::get('/waste-records', [InventoryModuleController::class, 'wasteRecords']);
        Route::get('/movement-records', [InventoryModuleController::class, 'movementRecords']);
        Route::get('/availability', [InventoryModuleController::class, 'availability']);

        Route::get('/recipes', [InventoryModuleController::class, 'recipes']);
        Route::post('/recipes', [InventoryModuleController::class, 'storeRecipe']);
        Route::get('/recipes/{recipe}', [InventoryModuleController::class, 'showRecipe']);
        Route::patch('/recipes/{recipe}', [InventoryModuleController::class, 'updateRecipe']);
        Route::delete('/recipes/{recipe}', [InventoryModuleController::class, 'destroyRecipe']);

        Route::post('/stock-counts', [InventoryModuleController::class, 'stockCounts']);
    });
});

Route::middleware('auth:sanctum', 'permission:settings')->group(function () {
    Route::get('/role-permissions', [App\Http\Controllers\Api\RolePermissionController::class, 'index']);
    Route::post('/role-permissions', [App\Http\Controllers\Api\RolePermissionController::class, 'store']);
    Route::patch('/role-permissions/{role}', [App\Http\Controllers\Api\RolePermissionController::class, 'update']);
    Route::delete('/role-permissions/{role}', [App\Http\Controllers\Api\RolePermissionController::class, 'destroy']);
});

// Staff and Admin routes (orders + payments)
Route::middleware('auth:sanctum', 'permission:orders|pos')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
        Route::patch('/{order}/mark-paid', [OrderController::class, 'markAsPaid']);
    });

    Route::post('/orders/{order}/bakong/intent', [BakongPaymentController::class, 'intent']);
    Route::post('/orders/{order}/bakong/khqr', [OrderPaymentController::class, 'khqr']);
    Route::get('/orders/{order}/bakong/status', [BakongPaymentController::class, 'status']);
});
