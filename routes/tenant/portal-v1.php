<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Tenant\Api\V1\Portal\PortalCalendarEventController;
use App\Http\Controllers\Tenant\Api\V1\Portal\PortalConversationController;
use App\Http\Controllers\Tenant\Api\V1\Portal\PortalDocumentController;
use App\Http\Controllers\Tenant\Api\V1\Portal\PortalMessageController;
use App\Http\Controllers\Tenant\Api\V1\Portal\PortalTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal API — Version 1
|--------------------------------------------------------------------------
|
| URL: /api/tenant/v1/portal/...
|
| Two authentication systems exist:
|   1. auth:tenant-api — Internal CRM users (User model)
|   2. auth:portal-api — Portal users (PortalUser model)
|
| Admin routes (auth:tenant-api) are defined in crm-v1.php.
| Portal user routes (auth:portal-api) are defined below.
|
*/

Route::prefix('portal')->name('portal.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Portal Authentication (Unauthenticated)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [PortalAuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [PortalAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [PortalAuthController::class, 'resetPassword'])->name('reset-password');
    });

    /*
    |--------------------------------------------------------------------------
    | Portal Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:portal-api')->group(function () {
        Route::post('auth/logout', [PortalAuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/change-password', [PortalAuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('auth/me', [PortalAuthController::class, 'me'])->name('auth.me');

        Route::get('documents', [PortalDocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}', [PortalDocumentController::class, 'show'])->name('documents.show');

        Route::get('conversations', [PortalConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{conversation}', [PortalConversationController::class, 'show'])->name('conversations.show');

        Route::get('messages', [PortalMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [PortalMessageController::class, 'show'])->name('messages.show');

        Route::get('tasks', [PortalTaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [PortalTaskController::class, 'show'])->name('tasks.show');

        Route::get('calendar-events', [PortalCalendarEventController::class, 'index'])->name('calendar-events.index');
        Route::get('calendar-events/{calendar_event}', [PortalCalendarEventController::class, 'show'])->name('calendar-events.show');
    });
});
