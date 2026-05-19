<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\OrderTypeController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StockController;

Route::get('/health', [HealthController::class, 'health']);
Route::get('/db-check', [HealthController::class, 'dbCheck']);

// Route::post('/auth/register', [AuthController::class, 'register']);
// Route::post('/auth/login',    [AuthController::class, 'login']);
// Route::post('/auth/logout',   [AuthController::class, 'logout']);

// --- Auth
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // opsional
Route::post('/logout',   [AuthController::class, 'logout']);
Route::get('/me',        [AuthController::class, 'me']);

Route::get('/auth/me', [AuthController::class, 'me']); // CookieUser sudah global di group api

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::patch('/products/{sku}', [ProductController::class, 'update']);
Route::post('/products/{sku}/stock', [ProductController::class, 'adjustStock']);
Route::post('/products/{sku}/delete', [ProductController::class, 'softDelete']);

Route::get('/discounts', [DiscountController::class, 'index']);
Route::post('/discounts', [DiscountController::class, 'store']);
Route::patch('/discounts/{code}', [DiscountController::class, 'update']);
Route::delete('/discounts/{code}', [DiscountController::class, 'destroy']);

Route::get('/order-types', [OrderTypeController::class, 'index']);
Route::post('/order-types', [OrderTypeController::class, 'store']);
Route::patch('/order-types/{code}', [OrderTypeController::class, 'update']);
Route::delete('/order-types/{code}', [OrderTypeController::class, 'destroy']);

Route::get('/menus', [MenuController::class, 'index']);
Route::post('/menus', [MenuController::class, 'store']);
Route::patch('/menus/{code}', [MenuController::class, 'update']);
Route::delete('/menus/{code}', [MenuController::class, 'destroy']);

// Route::post('/orders', [OrderController::class, 'store']);
// Route::get('/orders', [OrderController::class, 'index']);
// Route::get('/orders/{id}', [OrderController::class, 'show']);

Route::get('/orders',        [OrderController::class, 'index']);
Route::get('/orders/{id}',   [OrderController::class, 'show']);

Route::get('/stock-moves', [StockController::class, 'index']);
