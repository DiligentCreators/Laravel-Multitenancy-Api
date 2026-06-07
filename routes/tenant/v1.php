<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API — Version 1
|--------------------------------------------------------------------------
|
| Final URL: /api/v1/tenant/...
|
| Examples:
|   POST /api/v1/tenant/auth/login             (login)
|   POST /api/v1/tenant/auth/register           (register)
|   POST /api/v1/tenant/auth/forgot-password    (forgot password)
|   POST /api/v1/tenant/auth/reset-password     (reset password)
|   GET  /api/v1/tenant/me                      (current user)
|   POST /api/v1/tenant/logout                  (logout)
|   GET  /api/v1/tenant/dashboard               (dashboard)
|
| Middleware applied (from routes/tenant.php):
|   - api         Laravel API middleware group
|   - tenancy     Custom tenant resolution (domain → header → input)
|
| Controller namespace: App\Http\Controllers\Tenant\Api\V1
|
| Tenancy is initialized before these routes execute.
| All Eloquent queries are auto-scoped to the current tenant
| via the BelongsToTenant trait + TenantScope.
|
| Auth guard: auth:tenant-api (authenticates App\Models\User)
|
| Available middleware:
|   - auth:tenant-api            Sanctum auth (tenant guard)
|   - abilities:{ability}       Sanctum specific ability check
|   - ability:{a},{b}           Sanctum any-ability check
|   - can:{permission}          Spatie gate/ability check
|
|--------------------------------------------------------------------------
| Tenant Permission Context
|--------------------------------------------------------------------------
|
| All roles and permissions use guard_name = 'tenant-api'. Spatie
| auto-scopes permission lookups by guard. The BelongsToTenant trait
| ensures data isolation at the query level via tenant_id.
|
| See config/permission.php for the full strategy.
|
*/

/*
|--------------------------------------------------------------------------
| Authentication (Unauthenticated)
|--------------------------------------------------------------------------
|
| Controller namespace: App\Http\Controllers\Tenant\Api\V1\Auth
|
*/

Route::prefix('auth')->name('auth.')->group(function () {

    // POST /api/v1/tenant/auth/register
    // Body: name, email, password, password_confirmation
    // Response: { token, user }
    // Route::post('register', [RegisterController::class, '__invoke']);

    // POST /api/v1/tenant/auth/login
    // Body: email, password
    // Response: { token, user }
    // Route::post('login', [LoginController::class, '__invoke']);

    // POST /api/v1/tenant/auth/forgot-password
    // Body: email
    // Response: { message }
    // Route::post('forgot-password', [ForgotPasswordController::class, '__invoke']);

    // POST /api/v1/tenant/auth/reset-password
    // Body: email, token, password, password_confirmation
    // Response: { message }
    // Route::post('reset-password', [ResetPasswordController::class, '__invoke']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| Controller namespace: App\Http\Controllers\Tenant\Api\V1
|
*/

Route::middleware('auth:tenant-api')->group(function () {

    // GET  /api/v1/tenant/me
    // Route::get('me', [ProfileController::class, 'show']);

    // POST /api/v1/tenant/logout
    // Route::post('logout', [Auth\LoginController::class, 'logout']);

    // GET  /api/v1/tenant/dashboard
    // Route::get('dashboard', [DashboardController::class, '__invoke']);

    // Future CRM resources:
    // Route::apiResource('contacts', ContactController::class);
    // Route::apiResource('leads', LeadController::class);
    // Route::apiResource('deals', DealController::class);
});
