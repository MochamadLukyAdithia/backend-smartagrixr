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
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubmissionController;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register'])->middleware(ThrottleRegistration::class)->middleware('domain.whitelist');
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

Route::post('/login', [AuthController::class, 'login']);

// Midtrans webhook (tidak pakai auth, pakai signature verification)
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// Deep link join
Route::get('/join/{code}', [InviteController::class, 'join']);

// Show Plans and detail
Route::get('/plans', [PaymentController::class, 'showPlans']);
Route::get('/plans/{slug}', [PaymentController::class, 'detailPlan']);

// Public (cek kode tanpa login)
Route::get('/classrooms/resolve/{code}', [InviteController::class, 'resolve']);
 
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
        // Route::post('/classrooms/{id}/invite-code/regenerate', [InviteController::class, 'regenerate']);
    });

    Route::get('/classrooms',                              [ClassroomController::class, 'index']);
    Route::get('/classrooms/{id}',                         [ClassroomController::class, 'show']);
    Route::get('/classrooms/{id}/members',                 [ClassroomController::class, 'members']);
    Route::get('/classrooms/{id}/feed',                    [PostController::class, 'feed']);
    Route::get('/posts/{id}/comments',                     [CommentController::class, 'index']);
    Route::get('/classrooms/{id}/invite-code',             [InviteController::class, 'show']);
    Route::post('/classrooms/join/{code}',                 [InviteController::class, 'joinByCode']);
    Route::post('/classrooms/{id}/invite-code/regenerate', [InviteController::class, 'regenerate']);

    Route::post('/classrooms/{id}/enroll',             [ClassroomController::class, 'enroll']);
    Route::delete('/classrooms/{id}/unenroll',         [ClassroomController::class, 'unenroll']);
    Route::post('/assignments/{id}/submit',            [SubmissionController::class, 'submit']);
    Route::get('/assignments/{id}/submissions',        [SubmissionController::class, 'index']);
    Route::post('/posts/{id}/comments',                [CommentController::class, 'store']);
    Route::post('/comments/{id}/reply',                [CommentController::class, 'reply']);
    Route::delete('/comments/{id}',                    [CommentController::class, 'destroy']);
    Route::post('/posts/{id}/like',                    [LikeController::class, 'togglePost']);
    Route::post('/comments/{id}/like',                 [LikeController::class, 'toggleComment']);    

    Route::get('/projects',                     [ProjectController::class, 'index']);
    Route::post('/projects',                    [ProjectController::class, 'store']);
    Route::get('/projects/{id}/editor',         [ProjectController::class, 'loadEditor']);
    Route::put('/projects/{id}/scene',          [ProjectController::class, 'saveScene']);
    Route::put('/projects/{id}/publish',        [ProjectController::class, 'publish']);
    Route::put('/projects/{id}/unpublish',      [ProjectController::class, 'unpublish']);
    Route::delete('/projects/{id}',             [ProjectController::class, 'destroy']);

    Route::get('/assets',                       [AssetController::class, 'index']);
    Route::get('/assets/{id}/url',              [AssetController::class, 'getUrl']);
    Route::post('/assets/upload',               [AssetController::class, 'upload']);
    Route::delete('/assets/{id}',               [AssetController::class, 'destroy']);

    Route::get('/asset-categories',             [AssetCategoryController::class, 'index']);
    Route::post('/asset-categories',            [AssetCategoryController::class, 'store']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::put('/asset-categories/{id}',    [AssetCategoryController::class, 'update']);
        Route::delete('/asset-categories/{id}', [AssetCategoryController::class, 'destroy']);
    });
});

Route::get('/ar/project/{id}', function (int $id) {
    $project = \App\Models\Project::where('id', $id)
        ->where('status', 'published')
        ->firstOrFail();

    $storageService = app(\App\Services\StorageService::class);

    $objects = collect($project->scene_data['objects'] ?? [])
        ->map(function ($obj) use ($storageService) {
            $asset = \App\Models\Asset::find($obj['asset_id']);
            if (!$asset) return null;

            return array_merge($obj, [
                'file_url' => $storageService->temporaryUrl($asset->file_path, 60),
            ]);
        })
        ->filter()
        ->values();

    return response()->json([
        'project'    => ['id' => $project->id, 'title' => $project->title],
        'scene_data' => array_merge($project->scene_data, ['objects' => $objects]),
    ]);
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

    // Login sebagai admin test
    Route::get('/dev/login-admin', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@test.local'],
            [
                'name'             => 'Test Admin',
                'password'         => bcrypt('password'),
                'unej_role'        => 'admin',
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

Route::get('/debug-php-config', function () {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size'       => ini_get('post_max_size'),
    ]);
});