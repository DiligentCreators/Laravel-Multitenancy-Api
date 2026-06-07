<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Central\Api\V1\Auth\LoginController;
use App\Http\Controllers\Central\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Central\Api\V1\DashboardController;
use App\Http\Controllers\Central\Api\V1\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API — Version 1
|--------------------------------------------------------------------------
|
| Final URL: /api/v1/...
|
| Examples:
|   POST /api/v1/auth/login               (login)
|   POST /api/v1/auth/forgot-password      (forgot password)
|   POST /api/v1/auth/reset-password       (reset password)
|   GET  /api/v1/me                        (current user)
|   POST /api/v1/logout                    (logout)
|   GET  /api/v1/dashboard                 (admin dashboard)
|   GET  /api/v1/tenants                   (tenant management)
|   GET  /api/v1/settings                  (system settings)
|
| Middleware applied (from routes/api.php):
|   - api             Laravel API middleware group
|   - central.domain  Blocks tenant-domain access
|
| Controller namespace: App\Http\Controllers\Central\Api\V1
|
| Auth guard: auth:central-api (authenticates App\Models\CentralUser)
|
| Available middleware:
|   - auth:central-api          Sanctum auth (central guard)
|   - abilities:{ability}       Sanctum specific ability check
|   - ability:{a},{b}           Sanctum any-ability check
|   - can:{permission}          Spatie gate/ability check
|   - central.domain            Blocks tenant-domain access
|
|--------------------------------------------------------------------------
| Central vs Tenant Permission Isolation
|--------------------------------------------------------------------------
|
| Central roles use guard_name = 'central-api'. Spatie auto-scopes
| permission lookups by guard. Central routes never initialize
| tenancy, so TenantScope is never applied.
|
| See config/permission.php for the full strategy.
|
*/

/*
|--------------------------------------------------------------------------
| Authentication (Unauthenticated)
|--------------------------------------------------------------------------
|
| Controller namespace: App\Http\Controllers\Central\Api\V1\Auth
|
| Note: No register endpoint. Central users are created by admins.
|
*/

Route::prefix('auth')->name('auth.')->group(function () {

    // POST /api/v1/auth/login
    Route::post('login', LoginController::class);

    // POST /api/v1/auth/forgot-password
    Route::post('forgot-password', ForgotPasswordController::class);

    // POST /api/v1/auth/reset-password
    // Body: email, token, password, password_confirmation
    // Response: { message }
    Route::post('reset-password', ResetPasswordController::class);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| Controller namespace: App\Http\Controllers\Central\Api\V1
|
*/

Route::middleware('auth:central-api')->group(function () {

    Route::prefix('me')->name('me.')->group(function () {
        // GET /api/v1/me
        Route::get('/', [ProfileController::class, 'me']);

        // Post /api/v1/me
        Route::post('/', [ProfileController::class, 'update']);

        // Post /api/v1/me/password
        Route::post('password', [ProfileController::class, 'changePassword']);

        // POST /api/v1/me/logout
        Route::post('logout', [ProfileController::class, 'logout']);
    });

    // GET  /api/v1/dashboard
    Route::get('dashboard', DashboardController::class);
});
