<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SubmissionController;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register'])->middleware(ThrottleRegistration::class)->middleware('domain.whitelist');

Route::post('/login', [AuthController::class, 'login']);

// Midtrans webhook (tidak pakai auth, pakai signature verification)
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// Deep link join
Route::get('/join/{code}', [InviteController::class, 'join']);

// Show Plans and detail
Route::get('/plans', [PaymentController::class, 'showPlans']);
Route::get('/plans/{slug}', [PaymentController::class, 'detailPlan']);
 
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

    Route::middleware('unej.dosen')->group(function () {
        Route::post('/classrooms',                         [ClassroomController::class, 'store']);
        Route::put('/classrooms/{id}',                     [ClassroomController::class, 'update']);
        Route::delete('/classrooms/{id}',                  [ClassroomController::class, 'destroy']);
        Route::delete('/classrooms/{id}/members/{userId}', [ClassroomController::class, 'kickMember']);
        Route::post('/classrooms/{id}/posts',              [PostController::class, 'store']);
        Route::delete('/posts/{id}',                       [PostController::class, 'destroy']);
        Route::post('/submissions/{id}/grade',             [SubmissionController::class, 'grade']);
        Route::post('/classrooms/{id}/invite-code/regenerate', [InviteController::class, 'regenerate']);
    });

    Route::get('/classrooms',                              [ClassroomController::class, 'index']);
    Route::get('/classrooms/{id}',                         [ClassroomController::class, 'show']);
    Route::get('/classrooms/{id}/members',                 [ClassroomController::class, 'members']);
    Route::get('/classrooms/{id}/feed',                    [PostController::class, 'feed']);
    Route::get('/posts/{id}/comments',                     [CommentController::class, 'index']);
    Route::get('/classrooms/{id}/invite-code',            [InviteController::class, 'show']);

    Route::post('/classrooms/{id}/enroll',             [ClassroomController::class, 'enroll']);
    Route::delete('/classrooms/{id}/unenroll',         [ClassroomController::class, 'unenroll']);
    Route::post('/assignments/{id}/submit',            [SubmissionController::class, 'submit']);
    Route::get('/assignments/{id}/submissions',        [SubmissionController::class, 'index']);
    Route::post('/posts/{id}/comments',                [CommentController::class, 'store']);
    Route::post('/comments/{id}/reply',                [CommentController::class, 'reply']);
    Route::delete('/comments/{id}',                    [CommentController::class, 'destroy']);
    Route::post('/posts/{id}/like',                    [LikeController::class, 'togglePost']);
    Route::post('/comments/{id}/like',                 [LikeController::class, 'toggleComment']);

});

// if (app()->environment('local')) {

    Route::get('/dev/login-as/{userId}', function (int $userId) {
        $user  = \App\Models\User::findOrFail($userId);
        $token = $user->createToken('dev_token')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'user'     => $user,
            'unej'     => [
                'role'             => $user->unej_role,
                'is_verified'      => $user->is_unej_verified,
                'can_create_class' => $user->isDosen(),
            ],
        ]);
    });

    // Login sebagai dosen test
    Route::get('/dev/login-dosen', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => '198410082008121002@mail.unej.ac.id'],
            [
                'name'             => 'Dr. Test Dosen',
                'password'         => bcrypt('password'),
                'unej_role'        => 'dosen',
                'is_unej_verified' => true,
                'status'           => 'active',
                'email_verified_at'=> now(),
            ]
        );

        if ($user->wasRecentlyCreated) {
            app(\App\Services\SubscriptionService::class)->assignOnRegister($user);
        }

        $token = $user->createToken('dev_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    });

    // Login sebagai mahasiswa test
    Route::get('/dev/login-mahasiswa', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => '232410102065@mail.unej.ac.id'],
            [
                'name'             => 'Test Mahasiswa',
                'password'         => bcrypt('password'),
                'unej_role'        => 'mahasiswa',
                'is_unej_verified' => true,
                'status'           => 'active',
                'email_verified_at'=> now(),
            ]
        );

        if ($user->wasRecentlyCreated) {
            app(\App\Services\SubscriptionService::class)->assignOnRegister($user);
        }

        $token = $user->createToken('dev_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    });

    // Login sebagai user umum test
    Route::get('/dev/login-umum', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'test@gmail.com'],
            [
                'name'             => 'Test User Umum',
                'password'         => bcrypt('password'),
                'unej_role'        => 'umum',
                'is_unej_verified' => false,
                'status'           => 'active',
                'email_verified_at'=> now(),
            ]
        );

        if ($user->wasRecentlyCreated) {
            app(\App\Services\SubscriptionService::class)->assignOnRegister($user);
        }

        $token = $user->createToken('dev_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    });
// }