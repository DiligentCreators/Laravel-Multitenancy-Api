<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Tenant\Api\V1\Auth\LoginController;
use App\Http\Controllers\Tenant\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Tenant\Api\V1\DashboardController;
use App\Http\Controllers\Tenant\Api\V1\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API — Version 1
|--------------------------------------------------------------------------
|
| Final URL: /api/tenant/v1/...
|
| Examples:
|   POST /api/tenant/v1/auth/login             (login)
|   POST /api/tenant/v1/auth/register           (register)
|   POST /api/tenant/v1/auth/forgot-password    (forgot password)
|   POST /api/tenant/v1/auth/reset-password     (reset password)
|   GET  /api/tenant/v1/me                      (current user)
|   POST /api/tenant/v1/logout                  (logout)
|   GET  /api/tenant/v1/dashboard               (dashboard)
|
| Middleware applied (from routtenant/es.php):
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
 |   - can:{permission}          Spatie gate/ability check
 |   - subscription              Ensures tenant has active subscription
 |   - feature:{slug}            Ensures plan has a specific feature
 |
 | Authorization uses Spatie Laravel Permission via policies and gates.
 | Sanctum token abilities are NOT used. See app/Policies/ for policy definitions.
 |
 | Examples:
 |   Route::middleware(['auth:tenant-api', 'subscription'])->group(function () {
 |       // Protected by active subscription
 |   });
 |
 |   Route::middleware(['auth:tenant-api', 'subscription', 'feature:users'])->group(function () {
 |       // Protected by active subscription + 'users' plan feature
 |   });
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

    // POST /api/v1/auth/login
    Route::post('login', LoginController::class)
        ->middleware('throttle:auth-login')
        ->name('login');

    // POST /api/v1/auth/forgot-password
    Route::post('forgot-password', ForgotPasswordController::class)->name('forgot-password');

    // POST /api/v1/auth/reset-password
    // Body: email, token, password, password_confirmation
    // Response: { message }
    Route::post('reset-password', ResetPasswordController::class)->name('reset-password');
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

    Route::prefix('me')->name('me.')->group(function () {
        // GET /api/v1/me
        Route::get('/', [ProfileController::class, 'me']);

        // Post /api/v1/me
        Route::post('/', [ProfileController::class, 'update'])->name('update-profile');

        // Post /api/v1/me/password
        Route::post('change-password', [ProfileController::class, 'changePassword'])->name('change-password');

        // POST /api/v1/me/logout
        Route::post('logout', [ProfileController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Subscription-Protected Routes
|--------------------------------------------------------------------------
|
| The 'subscription' middleware ensures the tenant has a valid, non-expired,
| non-suspended subscription before allowing access.
|
| The 'feature:{slug}' middleware gates access based on the tenant's plan.
|
| Order: auth -> subscription -> feature (if needed)
|
*/
// GET  /api/tenant/v1/dashboard
Route::get('dashboard', DashboardController::class)
    ->middleware(['auth:tenant-api', 'subscription'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| CRM Core Routes
|--------------------------------------------------------------------------
|
| All CRM routes are protected by auth:tenant-api and subscription.
| Individual feature gating is handled within each endpoint.
|
*/
Route::middleware(['auth:tenant-api', 'subscription'])->group(function () {
    require base_path('routes/tenant/crm-v1.php');
});

/*
|--------------------------------------------------------------------------
| Portal Routes
|--------------------------------------------------------------------------
|
| Portal routes handle both unauthenticated (auth) and authenticated
| (auth:portal-api) requests for the client portal.
|
*/
require base_path('routes/tenant/portal-v1.php');

/*
| These routes are gated by both an active subscription AND a specific
| plan feature. Uncomment and adapt when the corresponding controllers exist.
|
| Route::middleware(['auth:tenant-api', 'subscription', 'feature:users'])->group(function () {
|     Route::apiResource('users', UserController::class);
| });
|
| Route::middleware(['auth:tenant-api', 'subscription', 'feature:contacts'])->prefix('contacts')->group(function () {
|     Route::apiResource('/', ContactController::class);
| });
|
| Route::middleware(['auth:tenant-api', 'subscription', 'feature:reports'])->prefix('reports')->group(function () {
|     Route::get('/', [ReportController::class, 'index']);
| });
 */
