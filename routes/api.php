<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes
|--------------------------------------------------------------------------
|
| Entry point for all central (non-tenant) API routes.
| Handles platform-wide operations:
|   - Super Admin APIs
|   - Tenant Management
|   - Billing & Subscription Management
|   - Central Authentication
|   - System Settings
|   - Platform-wide Resources
|
| Controller namespace: App\Http\Controllers\Central\Api
|
|--------------------------------------------------------------------------
| API Versioning Strategy
|--------------------------------------------------------------------------
|
| URL-based versioning. Each version is a separate route file.
|
|   v1 → routes/central/v1.php
|   v2 → routes/central/v2.php  (future)
|   v3 → routes/central/v3.php  (future)
|
| To add a new version, create the file and add a Route::prefix('v{x}')
| group below. Maintain backward compatibility as needed.
|
| For header/content-negotiation versioning in the future:
| Implement a VersioningMiddleware that inspects the Accept header
| and maps to the correct route file.
|
*/

Route::middleware(['central.domain'])
    ->prefix('v1')
    ->name('central.')
    ->group(base_path('routes/central/v1.php'));

// Future versions:
// Route::prefix('v2')->name('central.')->group(base_path('routes/central/v2.php'));
// Route::prefix('v3')->name('central.')->group(base_path('routes/central/v3.php'));
