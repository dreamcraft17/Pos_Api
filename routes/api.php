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
use App\Http\Controllers\Api\OpenBillController;
use App\Models\PaymentMethod;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\StockRequestController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

Route::get('/health', [HealthController::class, 'health']);
Route::get('/db-check', [HealthController::class, 'dbCheck']);

// Alias untuk kompatibilitas dengan client lama:
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/logout',   [AuthController::class, 'logout']);
Route::get('/auth/me',        [AuthController::class, 'me']);


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

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);

Route::get('/stock-moves', [StockController::class, 'index']);


Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
Route::patch('/payment-methods/{code}', [PaymentMethodController::class, 'update']); 
Route::delete('/payment-methods/{code}', [PaymentMethodController::class, 'destroy']);

Route::post('/stock-request', [StockRequestController::class, 'send']);
Route::get('/stock-requests', [StockRequestController::class, 'index']);
Route::get('/stock-requests/{id}', [StockRequestController::class, 'show']);

// Route::get('/products', [ProductController::class, 'indexForAbsensi']);
// Route::get('/products/{sku}', [ProductController::class, 'showForAbsensi']);
// Route::post('/products/{sku}/adjust-stock', [ProductController::class, 'adjustStockForAbsensi']);
// Route::get('/stock-moves', [StockController::class, 'indexForAbsensi']);



Route::get('/open-bills',  [OpenBillController::class, 'index']);
Route::post('/open-bills', [OpenBillController::class, 'store']);
Route::delete('/open-bills/{id}', [OpenBillController::class, 'destroy']);

Route::post('/orders/{id}/refund', [OrderController::class, 'refund']);
Route::get('/orders/{id}/refunds', [OrderController::class, 'refunds']);

Route::post ('/stock-requests/send',        [StockRequestController::class, 'send']);     // versi rapi
Route::patch('/stock-requests/{id}/status', [StockRequestController::class, 'updateStatus']); 
Route::post ('/stock-requests/{id}/status', [StockRequestController::class, 'updateStatus']);
Route::post('/shifts/start', [ShiftController::class, 'start']);
Route::post('/shifts/{id}/close', [ShiftController::class, 'close']);
Route::get('/shifts/active-summary', [\App\Http\Controllers\Api\ShiftController::class, 'activeSummary']);
// routes/api.php
Route::get('/shifts/active', [\App\Http\Controllers\Api\ShiftController::class, 'active']);
// Public read-only: list riwayat shift untuk generate di app tanpa login
Route::get('/shifts/public', [\App\Http\Controllers\Api\ShiftController::class, 'publicIndex']);

// routes/api.php
Route::patch('/stock-requests/{id}/approval', [StockRequestController::class, 'updateApproval']);

Route::post('/stock-requests/upload-proof', [\App\Http\Controllers\Api\StockRequestController::class, 'uploadProof']);
Route::get('/stock-requests/proof-file/{filename}', function ($filename) {
    // amankan nama file (tanpa path traversal)
    $safe = basename($filename);
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $safe)) {
        abort(400, 'Invalid filename');
    }

    $path = base_path('upload_proof/'.$safe);   // <— di root project
    if (!File::exists($path)) {
        abort(404, 'File not found.');
    }

    $mime = File::mimeType($path) ?: 'application/octet-stream';
    return Response::file($path, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::post('/shifts', [ShiftController::class, 'store']);
    Route::get('/shifts/{id}', [ShiftController::class, 'show']);
    Route::delete('/shifts/{id}', [ShiftController::class, 'destroy']);
    
});

Route::prefix('bundle-menus')->group(function () {
    Route::get('/', [BundleMenuController::class, 'index']);
    Route::post('/', [BundleMenuController::class, 'store']);
    Route::patch('/{code}', [BundleMenuController::class, 'update']);
    Route::delete('/{code}', [BundleMenuController::class, 'destroy']);
});


