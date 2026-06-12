<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Central\Api\V1\Auth\LoginController;
use App\Http\Controllers\Central\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Central\Api\V1\DashboardController;
use App\Http\Controllers\Central\Api\V1\PlanController;
use App\Http\Controllers\Central\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Central\Api\V1\RoleController;
use App\Http\Controllers\Central\Api\V1\TenantController;
use App\Http\Controllers\Central\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API — Version 1
|--------------------------------------------------------------------------
|
| Final URL: /api/central/v1/...
|
| Examples:
|   POST /api/central/v1/auth/login               (login)
|   POST /api/central/v1/auth/forgot-password      (forgot password)
|   POST /api/central/v1/auth/reset-password       (reset password)
|   GET  /api/central/v1/me                        (current user)
|   POST /api/central/v1/logout                    (logout)
|   GET  /api/central/v1/dashboard                 (admin dashboard)
|   GET  /api/central/v1/tenants                   (tenant management)
|   GET  /api/central/v1/settings                  (system settings)
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

    // POST /api/central/v1/auth/login
    Route::post('login', LoginController::class)
        ->name('login');

    // POST /api/central/v1/auth/forgot-password
    Route::post('forgot-password', ForgotPasswordController::class)
        ->name('forgot-password');

    // POST /api/central/v1/auth/reset-password
    // Body: email, token, password, password_confirmation
    // Response: { message }
    Route::post('reset-password', ResetPasswordController::class)
        ->name('reset-password');
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
        // GET /api/central/v1/me
        Route::get('/', [ProfileController::class, 'me']);

        // Post /api/central/v1/me
        Route::post('/', [ProfileController::class, 'update'])
            ->name('update-profile');

        // Post /api/central/v1/me/password
        Route::post('change-password', [ProfileController::class, 'changePassword'])
            ->name('change-password');

        // POST /api/central/v1/me/logout
        Route::post('logout', [ProfileController::class, 'logout'])
            ->name('logout');
    });

    // GET  /api/central/v1/dashboard
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // GET      /api/central/v1/tenants
    // POST     /api/central/v1/tenants
    // GET      /api/central/v1/tenants/{tenant}
    // PUT      /api/central/v1/tenants/{tenant}
    // DELETE   /api/central/v1/tenants/{tenant}
    // POST     /api/central/v1/tenants/{tenant}/restore
    // DELETE   /api/central/v1/tenants/{tenant}/force
    Route::apiResource('tenants', TenantController::class);
    Route::prefix('tenants/{tenant}')->name('tenants.')->group(function () {
        Route::post('restore', [TenantController::class, 'restore'])
            ->name('restore');

        Route::delete('force', [TenantController::class, 'forceDelete'])
            ->name('force-delete');
    });

    // GET      /api/central/v1/roles
    // POST     /api/central/v1/roles
    // GET      /api/central/v1/roles/{role}
    // PUT      /api/central/v1/roles/{role}
    Route::apiResource('roles', RoleController::class);

    // GET      /api/central/v1/users
    // POST     /api/central/v1/users
    // GET      /api/central/v1/users/{user}
    // PUT      /api/central/v1/users/{user}
    // DELETE   /api/central/v1/users/{user}
    // POST     /api/central/v1/users/{user}/change-password
    // POST     /api/central/v1/users/{user}/suspend
    // POST     /api/central/v1/users/{user}/unsuspend
    // POST     /api/central/v1/users/{user}/restore
    // DELETE   /api/central/v1/users/{user}/force
    Route::apiResource('users', UserController::class);
    Route::prefix('users/{user}')
        ->name('users.')
        ->group(function () {

            Route::post('restore', [UserController::class, 'restore'])
                ->name('restore');

            Route::delete('force', [UserController::class, 'forceDelete'])
                ->name('force-delete');

            Route::post('suspend', [UserController::class, 'suspend'])
                ->name('suspend');

            Route::post('unsuspend', [UserController::class, 'unsuspend'])
                ->name('unsuspend');

            Route::post('change-password', [UserController::class, 'changePassword'])
                ->name('change-password');
        });

    // GET      /api/central/v1/plans
    // POST     /api/central/v1/plans
    // GET      /api/central/v1/plans/{plan}
    // PUT      /api/central/v1/plans/{plan}
    // DELETE   /api/central/v1/plans/{plan}
    // POST     /api/central/v1/plans/{plan}/restore
    // DELETE   /api/central/v1/plans/{plan}/force
    Route::apiResource('plans', PlanController::class);
    Route::prefix('plans/{plan}')
        ->name('plans.')
        ->group(function () {

            Route::post('restore', [PlanController::class, 'restore'])
                ->name('restore');

            Route::delete('force', [PlanController::class, 'forceDelete'])
                ->name('force-delete');
        });
});
