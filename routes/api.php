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
use App\Http\Controllers\OrderPaymentController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('product-types', ProductTypeController::class);
Route::apiResource('products', ProductController::class);

Route::get('menu', [MenuController::class, 'index']);


Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);

    Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
});
Route::post('/orders/{order}/bakong/intent', [BakongPaymentController::class, 'intent']);
Route::post('/orders/{order}/bakong/khqr', [OrderPaymentController::class, 'khqr']);
Route::get('/orders/{order}/bakong/status', [BakongPaymentController::class, 'status']);
