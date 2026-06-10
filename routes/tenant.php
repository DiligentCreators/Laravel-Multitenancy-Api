<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| These routes are accessible from BOTH tenant domains and the central
| domain. Tenant resolution is handled by the InitializeTenancy
| middleware which supports domain, header, and request input strategies.
|
|   From tenant domain:
|     https://tenant1.domain.com/api/v1/tenant/...
|
|   From central domain (with X-Tenant-Domain header):
|     https://domain.com/api/v1/tenant/...
|
| Route prefix: /api/v1/tenant
|   (added below, so tenant/v1.php routes are relative to this)
|
| Controller namespace: App\Http\Controllers\Tenant\Api
|
|--------------------------------------------------------------------------
| Middleware Stack
|--------------------------------------------------------------------------
|
|   1. api               — Laravel API middleware group
|   2. tenancy           — Custom tenant resolution (domain → header → input)
|
| Add auth:tenant-api within individual route groups as needed.
|
| Note: PreventAccessFromCentralDomains is intentionally NOT applied here
| since these routes must work from both central and tenant domains.
|
|--------------------------------------------------------------------------
| Versioning Strategy
|--------------------------------------------------------------------------
|
|   v1 → routes/tenant/v1.php
|   v2 → routes/tenant/v2.php  (future)
|   v3 → routes/tenant/v3.php  (future)
|
*/

Route::middleware([
    'api',
    'tenancy',
])->group(function () {
    Route::prefix('api/tenant/v1')
        ->name('tenant.')
        ->group(base_path('routes/tenant/v1.php'));

    // Future versions:
    // Route::prefix('api/v2')->name('tenant.')->group(base_path('routes/tenant/v2.php'));
    // Route::prefix('api/v3')->name('tenant.')->group(base_path('routes/tenant/v3.php'));
});
