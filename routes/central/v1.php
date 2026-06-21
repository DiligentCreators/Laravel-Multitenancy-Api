<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Api\V1\ActivityLogController;
use App\Http\Controllers\Central\Api\V1\AdminAuditLogController;
use App\Http\Controllers\Central\Api\V1\AnnouncementController;
use App\Http\Controllers\Central\Api\V1\ApiKeyController;
use App\Http\Controllers\Central\Api\V1\AuditLogController;
use App\Http\Controllers\Central\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Central\Api\V1\Auth\LoginController;
use App\Http\Controllers\Central\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Central\Api\V1\Billing\BillingPortalController;
use App\Http\Controllers\Central\Api\V1\CouponController;
use App\Http\Controllers\Central\Api\V1\DashboardController;
use App\Http\Controllers\Central\Api\V1\EmailTemplateController;
use App\Http\Controllers\Central\Api\V1\FeatureController;
use App\Http\Controllers\Central\Api\V1\ImpersonationController;
use App\Http\Controllers\Central\Api\V1\InvoiceController;
use App\Http\Controllers\Central\Api\V1\InvoicePdfController;
use App\Http\Controllers\Central\Api\V1\ModuleController;
use App\Http\Controllers\Central\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Central\Api\V1\OverageChargeController;
use App\Http\Controllers\Central\Api\V1\PaymentController;
use App\Http\Controllers\Central\Api\V1\PlanController;
use App\Http\Controllers\Central\Api\V1\PlanFeatureController;
use App\Http\Controllers\Central\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Central\Api\V1\RoleController;
use App\Http\Controllers\Central\Api\V1\SettingController;
use App\Http\Controllers\Central\Api\V1\SettingDefinitionController;
use App\Http\Controllers\Central\Api\V1\SettingGroupController;
use App\Http\Controllers\Central\Api\V1\SmsTemplateController;
use App\Http\Controllers\Central\Api\V1\SubscriptionController;
use App\Http\Controllers\Central\Api\V1\TaxRateController;
use App\Http\Controllers\Central\Api\V1\TaxRegionController;
use App\Http\Controllers\Central\Api\V1\TenantController;
use App\Http\Controllers\Central\Api\V1\TenantExportController;
use App\Http\Controllers\Central\Api\V1\TenantSettingController;
use App\Http\Controllers\Central\Api\V1\TicketController;
use App\Http\Controllers\Central\Api\V1\UsageController;
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
 |   - can:{permission}          Spatie gate/ability check
 |   - central.domain            Blocks tenant-domain access
 |
 | Authorization uses Spatie Laravel Permission via policies and gates.
 | Sanctum token abilities are NOT used. See app/Policies/ for policy definitions.
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
        ->middleware('throttle:auth-login')
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

/*
|--------------------------------------------------------------------------
| Stripe Webhook (Unauthenticated)
|--------------------------------------------------------------------------
|
| Stripe sends webhooks without auth headers. CSRF exempt.
|
*/
use App\Http\Controllers\Central\Api\V1\Billing\StripeWebhookController;
use App\Http\Middleware\EnsureCentralDomain;

Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook')
    ->withoutMiddleware([EnsureCentralDomain::class]);

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

    /*
    |--------------------------------------------------------------------------
    | Tenant Settings (Admin)
    |--------------------------------------------------------------------------
    */
    Route::prefix('tenants/{tenant}/settings')->name('tenant-settings.')->group(function () {
        Route::get('/', [TenantSettingController::class, 'index'])->name('index');
        Route::put('/', [TenantSettingController::class, 'update'])->name('update');
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

            Route::get('features', [PlanFeatureController::class, 'index'])
                ->name('features.index');

            Route::post('features', [PlanFeatureController::class, 'store'])
                ->name('features.store');

            Route::put('features/{feature}', [PlanFeatureController::class, 'update'])
                ->name('features.update');

            Route::delete('features/{feature}', [PlanFeatureController::class, 'destroy'])
                ->name('features.destroy');
        });

    // GET      /api/central/v1/subscriptions
    // POST     /api/central/v1/subscriptions
    // GET      /api/central/v1/subscriptions/{subscription}
    // PUT      /api/central/v1/subscriptions/{subscription}
    // DELETE   /api/central/v1/subscriptions/{subscription}
    // POST     /api/central/v1/subscriptions/{subscription}/restore
    // DELETE   /api/central/v1/subscriptions/{subscription}/force
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::prefix('subscriptions/{subscription}')
        ->name('subscriptions.')
        ->group(function () {

            Route::post('restore', [SubscriptionController::class, 'restore'])
                ->name('restore');

            Route::delete('force', [SubscriptionController::class, 'forceDelete'])
                ->name('force-delete');
        });

    // GET      /api/central/v1/features
    // POST     /api/central/v1/features
    // GET      /api/central/v1/features/{feature}
    // PUT      /api/central/v1/features/{feature}
    // DELETE   /api/central/v1/features/{feature}
    // POST     /api/central/v1/features/{feature}/restore
    // DELETE   /api/central/v1/features/{feature}/force
    Route::apiResource('features', FeatureController::class);
    Route::prefix('features/{feature}')
        ->name('features.')
        ->group(function () {

            Route::post('restore', [FeatureController::class, 'restore'])
                ->name('restore');

            Route::delete('force', [FeatureController::class, 'forceDelete'])
                ->name('force-delete');

            Route::post('active', [FeatureController::class, 'active'])
                ->name('active');

            Route::post('inactive', [FeatureController::class, 'inactive'])
                ->name('inactive');
        });

    // GET      /api/central/v1/setting-definitions
    // POST     /api/central/v1/setting-definitions
    // PUT      /api/central/v1/setting-definitions/{setting-definition}
    Route::apiResource('setting-definitions', SettingDefinitionController::class)
        ->only(['index', 'store', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    Route::apiResource('invoices', InvoiceController::class);
    Route::prefix('invoices/{invoice}')->name('invoices.')->group(function () {
        Route::post('restore', [InvoiceController::class, 'restore'])->name('restore');
        Route::delete('force', [InvoiceController::class, 'forceDelete'])->name('force-delete');
        Route::post('mark-paid', [InvoiceController::class, 'markPaid'])->name('mark-paid');
        Route::post('mark-overdue', [InvoiceController::class, 'markOverdue'])->name('mark-overdue');
    });

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */
    Route::apiResource('payments', PaymentController::class);
    Route::prefix('payments/{payment}')->name('payments.')->group(function () {
        Route::post('restore', [PaymentController::class, 'restore'])->name('restore');
        Route::delete('force', [PaymentController::class, 'forceDelete'])->name('force-delete');
        Route::post('complete', [PaymentController::class, 'markCompleted'])->name('complete');
        Route::post('fail', [PaymentController::class, 'markFailed'])->name('fail');
        Route::post('refund', [PaymentController::class, 'markRefunded'])->name('refund');
    });

    /*
    |--------------------------------------------------------------------------
    | Coupons
    |--------------------------------------------------------------------------
    */
    Route::apiResource('coupons', CouponController::class);
    Route::prefix('coupons/{coupon}')->name('coupons.')->group(function () {
        Route::post('restore', [CouponController::class, 'restore'])->name('restore');
        Route::delete('force', [CouponController::class, 'forceDelete'])->name('force-delete');
    });
    Route::post('coupons/validate', [CouponController::class, 'validateCoupon'])->name('coupons.validate');
    Route::post('coupons/apply', [CouponController::class, 'apply'])->name('coupons.apply');

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    */
    Route::apiResource('announcements', AnnouncementController::class);
    Route::prefix('announcements/{announcement}')->name('announcements.')->group(function () {
        Route::post('restore', [AnnouncementController::class, 'restore'])->name('restore');
        Route::delete('force', [AnnouncementController::class, 'forceDelete'])->name('force-delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Support Tickets
    |--------------------------------------------------------------------------
    */
    Route::apiResource('tickets', TicketController::class);
    Route::prefix('tickets/{ticket}')->name('tickets.')->group(function () {
        Route::post('restore', [TicketController::class, 'restore'])->name('restore');
        Route::delete('force', [TicketController::class, 'forceDelete'])->name('force-delete');
        Route::post('assign', [TicketController::class, 'assign'])->name('assign');
        Route::post('replies', [TicketController::class, 'addReply'])->name('replies.store');
    });

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    */
    Route::apiResource('api-keys', ApiKeyController::class);
    Route::prefix('api-keys/{api_key}')->name('api-keys.')->group(function () {
        Route::post('restore', [ApiKeyController::class, 'restore'])->name('restore');
        Route::delete('force', [ApiKeyController::class, 'forceDelete'])->name('force-delete');
        Route::post('regenerate', [ApiKeyController::class, 'regenerate'])->name('regenerate');
        Route::post('revoke', [ApiKeyController::class, 'revoke'])->name('revoke');
    });

    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */
    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('{id}', [ActivityLogController::class, 'show'])->name('show');
    });

    /*
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    */
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
    });

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */
    Route::prefix('modules')->name('modules.')->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('{module}', [ModuleController::class, 'show'])->name('show');
        Route::post('seed', [ModuleController::class, 'seed'])->name('seed');
        Route::post('{module}/enable', [ModuleController::class, 'enable'])->name('enable');
        Route::post('{module}/disable', [ModuleController::class, 'disable'])->name('disable');
        Route::post('{module}/enable-for-tenant', [ModuleController::class, 'enableForTenant'])->name('enable-for-tenant');
        Route::post('{module}/disable-for-tenant', [ModuleController::class, 'disableForTenant'])->name('disable-for-tenant');
    });

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    */
    Route::prefix('impersonation')->name('impersonation.')->group(function () {
        Route::post('start/{tenant}', [ImpersonationController::class, 'start'])->name('start');
        Route::post('stop', [ImpersonationController::class, 'stop'])->name('stop');
    });

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    */
    Route::apiResource('settings-groups', SettingGroupController::class);
    Route::apiResource('system-settings', SettingController::class);

    /*
    |--------------------------------------------------------------------------
    | Email Templates
    |--------------------------------------------------------------------------
    */
    Route::apiResource('email-templates', EmailTemplateController::class);
    Route::prefix('email-templates/{email_template}')->name('email-templates.')->group(function () {
        Route::post('preview', [EmailTemplateController::class, 'preview'])->name('preview');
        Route::post('send-test', [EmailTemplateController::class, 'sendTest'])->name('send-test');
        Route::post('duplicate', [EmailTemplateController::class, 'duplicate'])->name('duplicate');
        Route::get('versions', [EmailTemplateController::class, 'versions'])->name('versions');
    });

    /*
    |--------------------------------------------------------------------------
    | SMS Templates
    |--------------------------------------------------------------------------
    */
    Route::apiResource('sms-templates', SmsTemplateController::class);

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    */
    Route::apiResource('notification-templates', NotificationTemplateController::class);

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking
    |--------------------------------------------------------------------------
    */
    Route::prefix('tenants/{tenant}/usage/{feature}')->name('usage.')->group(function () {
        Route::get('/', [UsageController::class, 'show'])->name('show');
        Route::post('reset', [UsageController::class, 'reset'])->name('reset');
    });

    /*
    |--------------------------------------------------------------------------
    | Overage Charges
    |--------------------------------------------------------------------------
    */
    Route::apiResource('overage-charges', OverageChargeController::class)->only(['index', 'show', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Tax Regions & Rates
    |--------------------------------------------------------------------------
    */
    Route::apiResource('tax-regions', TaxRegionController::class);
    Route::apiResource('tax-rates', TaxRateController::class);

    /*
    |--------------------------------------------------------------------------
    | Billing Portal & Stripe Integration
    |--------------------------------------------------------------------------
    */
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::post('portal/{tenant}', [BillingPortalController::class, 'createPortalSession'])->name('portal');
        Route::post('checkout', [BillingPortalController::class, 'createCheckoutSession'])->name('checkout');
        Route::get('payment-methods/{tenant}', [BillingPortalController::class, 'getPaymentMethods'])->name('payment-methods');
        Route::get('invoices/{tenant}', [BillingPortalController::class, 'getInvoices'])->name('invoices');
    });

    /*
    |--------------------------------------------------------------------------
    | Invoice PDF
    |--------------------------------------------------------------------------
    */
    Route::prefix('invoices/{invoice}/pdf')->name('invoices.pdf.')->group(function () {
        Route::get('download', [InvoicePdfController::class, 'download'])->name('download');
        Route::get('stream', [InvoicePdfController::class, 'stream'])->name('stream');
        Route::post('generate', [InvoicePdfController::class, 'generate'])->name('generate');
    });

    /*
    |--------------------------------------------------------------------------
    | Tenant Data Export
    |--------------------------------------------------------------------------
    */
    Route::prefix('tenants/{tenant}/exports')->name('tenants.exports.')->group(function () {
        Route::post('/', [TenantExportController::class, 'export'])->name('export');
        Route::get('/', [TenantExportController::class, 'history'])->name('history');
    });
    Route::get('exports/{tenant_export_record}/download', [TenantExportController::class, 'download'])
        ->name('exports.download');

    /*
    |--------------------------------------------------------------------------
    | Admin Audit Logs
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin-audit-logs')->name('admin-audit-logs.')->group(function () {
        Route::get('/', [AdminAuditLogController::class, 'index'])->name('index');
    });
});
