<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware(ThrottleRegistration::class)->middleware('domain.whitelist');

Route::post('/login', [AuthController::class, 'login']);

// Midtrans webhook (tidak pakai auth, pakai signature verification)
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);
 
// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
 
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout/all-devices', [AuthController::class, 'logoutAllDevices']);
 
    // Permission check (digunakan oleh frontend sebelum render fitur)
    Route::get('/permission/check', [PermissionController::class, 'check']);
 
    // Payment
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payment/history',   [PaymentController::class, 'history']);
 
    // Pro-only routes (pakai middleware feature)
    Route::middleware('feature:upload_3d_asset')->group(function () {
        Route::post('/assets',       [AssetController::class, 'store']);
        Route::post('/generate-qr',  [QRController::class, 'generate']);
    });
 
    Route::middleware('feature:create_class')->group(function () {
        Route::post('/classes', [ClassController::class, 'store']);
    });
 
    Route::middleware('feature:analytics')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });
 
    // Admin routes
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/audit-logs',             [AuditLogController::class, 'index']);
        Route::get('/audit-logs/user/{id}',   [AuditLogController::class, 'forUser']);
        Route::get('/subscriptions',          [SubscriptionController::class, 'index']);
        Route::post('/domain-whitelist',      [DomainWhitelistController::class, 'store']);
    });
});