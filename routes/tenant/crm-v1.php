<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Api\V1\Crm\ActivityController;
use App\Http\Controllers\Tenant\Api\V1\Crm\AddressController;
use App\Http\Controllers\Tenant\Api\V1\Crm\CalendarEventController;
use App\Http\Controllers\Tenant\Api\V1\Crm\CommentController;
use App\Http\Controllers\Tenant\Api\V1\Crm\ConversationController;
use App\Http\Controllers\Tenant\Api\V1\Crm\CustomFieldController;
use App\Http\Controllers\Tenant\Api\V1\Crm\DocumentController;
use App\Http\Controllers\Tenant\Api\V1\Crm\DocumentFolderController;
use App\Http\Controllers\Tenant\Api\V1\Crm\DocumentShareController;
use App\Http\Controllers\Tenant\Api\V1\Crm\DocumentVersionController;
use App\Http\Controllers\Tenant\Api\V1\Crm\FeatureDefinitionController;
use App\Http\Controllers\Tenant\Api\V1\Crm\LeadController;
use App\Http\Controllers\Tenant\Api\V1\Crm\MessageController;
use App\Http\Controllers\Tenant\Api\V1\Crm\MessageTemplateController;
use App\Http\Controllers\Tenant\Api\V1\Crm\NoteController;
use App\Http\Controllers\Tenant\Api\V1\Crm\OrganizationController;
use App\Http\Controllers\Tenant\Api\V1\Crm\OrganizationPersonController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PersonController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PipelineController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PipelineStageController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PortalPersonLinkController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PortalUserController;
use App\Http\Controllers\Tenant\Api\V1\Crm\PublicDocumentController;
use App\Http\Controllers\Tenant\Api\V1\Crm\SourceController;
use App\Http\Controllers\Tenant\Api\V1\Crm\StatusController;
use App\Http\Controllers\Tenant\Api\V1\Crm\StatusTypeController;
use App\Http\Controllers\Tenant\Api\V1\Crm\TagController;
use App\Http\Controllers\Tenant\Api\V1\Crm\TaskCommentController;
use App\Http\Controllers\Tenant\Api\V1\Crm\TaskController;
use App\Http\Controllers\Tenant\Api\V1\Crm\TaskReminderController;
use App\Http\Controllers\Tenant\Api\V1\Crm\TimelineController;
use App\Http\Controllers\Tenant\Api\V1\Crm\WhatsAppAccountController;
use App\Http\Controllers\Tenant\Api\V1\Crm\WhatsAppMessageController;
use App\Http\Controllers\Tenant\Api\V1\Crm\WhatsAppWebhookController;
use App\Http\Controllers\Tenant\Api\V1\Crm\WorkflowDefinitionController;
use App\Models\Crm\Task;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant CRM API — Version 1
|--------------------------------------------------------------------------
|
| Final URL: /api/tenant/v1/crm/...
|
| All routes require auth:tenant-api
|
|--------------------------------------------------------------------------
*/

Route::middleware('auth:tenant-api')->prefix('crm')->name('crm.')->group(function () {

    // Feature Definitions (read-only for tenant users)
    Route::get('features', [FeatureDefinitionController::class, 'index'])->name('features.index');
    Route::get('features/{featureDefinition}', [FeatureDefinitionController::class, 'show'])->name('features.show');

    // Status Types
    Route::apiResource('status-types', StatusTypeController::class)->names('status-types');

    // Statuses
    Route::get('statuses/by-entity/{entityType}', [StatusController::class, 'byEntity'])->name('statuses.by-entity');
    Route::apiResource('statuses', StatusController::class)->names('statuses');

    // Tags
    Route::post('tags/merge', [TagController::class, 'merge'])->name('tags.merge');
    Route::post('tags/bulk-attach', [TagController::class, 'bulkAttach'])->name('tags.bulk-attach');
    Route::apiResource('tags', TagController::class)->names('tags');

    // Custom Fields
    Route::apiResource('custom-fields', CustomFieldController::class)->names('custom-fields');

    // Sources
    Route::apiResource('sources', SourceController::class)->names('sources');

    // Workflows
    Route::get('workflows/{workflowDefinition}/logs', [WorkflowDefinitionController::class, 'logs'])->name('workflows.logs');
    Route::apiResource('workflows', WorkflowDefinitionController::class)->names('workflows');

    // People
    Route::post('people/{id}/restore', [PersonController::class, 'restore'])->name('people.restore')->whereNumber('id');
    Route::apiResource('people', PersonController::class)->names('people');

    // Organizations
    Route::post('organizations/{id}/restore', [OrganizationController::class, 'restore'])->name('organizations.restore')->whereNumber('id');
    Route::apiResource('organizations', OrganizationController::class)->names('organizations');

    // Organization People
    Route::get('organization-people/by-organization/{organizationId}', [OrganizationPersonController::class, 'byOrganization'])->name('organization-people.by-organization');
    Route::get('organization-people/by-person/{personId}', [OrganizationPersonController::class, 'byPerson'])->name('organization-people.by-person');
    Route::apiResource('organization-people', OrganizationPersonController::class)->names('organization-people');

    // Addresses
    Route::get('addresses/by-entity/{entityType}/{entityId}', [AddressController::class, 'byEntity'])->name('addresses.by-entity');
    Route::apiResource('addresses', AddressController::class)->names('addresses');

    // Pipelines
    Route::apiResource('pipelines', PipelineController::class)->names('pipelines');

    // Pipeline Stages
    Route::get('pipeline-stages/by-pipeline/{pipelineId}', [PipelineStageController::class, 'byPipeline'])->name('pipeline-stages.by-pipeline');
    Route::post('pipeline-stages/reorder', [PipelineStageController::class, 'reorder'])->name('pipeline-stages.reorder');
    Route::apiResource('pipeline-stages', PipelineStageController::class)->names('pipeline-stages');

    // Leads
    Route::post('leads/{id}/restore', [LeadController::class, 'restore'])->name('leads.restore')->whereNumber('id');
    Route::post('leads/{lead}/move-stage', [LeadController::class, 'moveStage'])->name('leads.move-stage');
    Route::apiResource('leads', LeadController::class)->names('leads');

    // Activities
    Route::get('activities/by-entity/{type}/{id}', [ActivityController::class, 'byEntity'])->name('activities.by-entity');
    Route::post('activities/{id}/restore', [ActivityController::class, 'restore'])->name('activities.restore')->whereNumber('id');
    Route::apiResource('activities', ActivityController::class)->names('activities');

    // Notes
    Route::get('notes/by-entity/{type}/{id}', [NoteController::class, 'byEntity'])->name('notes.by-entity');
    Route::post('notes/{id}/restore', [NoteController::class, 'restore'])->name('notes.restore')->whereNumber('id');
    Route::apiResource('notes', NoteController::class)->names('notes');

    // Comments
    Route::get('comments/by-entity/{type}/{id}', [CommentController::class, 'byEntity'])->name('comments.by-entity');
    Route::get('comments/{parentComment}/replies', [CommentController::class, 'replies'])->name('comments.replies');
    Route::post('comments/{id}/restore', [CommentController::class, 'restore'])->name('comments.restore')->whereNumber('id');
    Route::apiResource('comments', CommentController::class)->names('comments');

    // Timeline
    Route::get('timeline/by-entity/{entityType}/{entityId}', [TimelineController::class, 'byEntity'])->name('timeline.by-entity');
    Route::apiResource('timeline', TimelineController::class)->parameters(['timeline' => 'timelineEntry'])->only(['index', 'show'])->names('timeline');

    // Tasks
    Route::post('tasks/{id}/restore', [TaskController::class, 'restore'])->name('tasks.restore')->whereNumber('id');
    Route::apiResource('tasks', TaskController::class)->names('tasks');

    // Task Comments (nested under tasks)
    Route::get('tasks/{taskId}/comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::post('tasks/{taskId}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::get('tasks/comments/{taskComment}', [TaskCommentController::class, 'show'])->name('tasks.comments.show');
    Route::put('tasks/comments/{taskComment}', [TaskCommentController::class, 'update'])->name('tasks.comments.update');
    Route::delete('tasks/comments/{taskComment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Task Reminders (nested under tasks)
    Route::get('tasks/{taskId}/reminders', [TaskReminderController::class, 'index'])->name('tasks.reminders.index');
    Route::post('tasks/{taskId}/reminders', [TaskReminderController::class, 'store'])->name('tasks.reminders.store');
    Route::get('tasks/reminders/{taskReminder}', [TaskReminderController::class, 'show'])->name('tasks.reminders.show');
    Route::put('tasks/reminders/{taskReminder}', [TaskReminderController::class, 'update'])->name('tasks.reminders.update');
    Route::delete('tasks/reminders/{taskReminder}', [TaskReminderController::class, 'destroy'])->name('tasks.reminders.destroy');

    // Calendar Events
    Route::post('calendar-events/{id}/restore', [CalendarEventController::class, 'restore'])->name('calendar-events.restore')->whereNumber('id');
    Route::apiResource('calendar-events', CalendarEventController::class)->names('calendar-events');

    // Conversations & Messages
    Route::middleware('crm-feature:communications.enabled')->group(function () {
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::put('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
        Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
        Route::post('conversations/{id}/restore', [ConversationController::class, 'restore'])->name('conversations.restore')->whereNumber('id');
        Route::post('conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');

        // Messages (nested under conversations)
        Route::get('conversations/{conversationId}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');
        Route::post('conversations/{conversationId}/messages', [MessageController::class, 'store'])->name('conversations.messages.store');
        Route::get('conversations/{conversation}/messages/{message}', [MessageController::class, 'show'])->name('conversations.messages.show');
        Route::put('conversations/{conversation}/messages/{message}', [MessageController::class, 'update'])->name('conversations.messages.update');
        Route::delete('conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy'])->name('conversations.messages.destroy');
    });

    // Message Templates
    Route::middleware('crm-feature:message_templates.enabled')->group(function () {
        Route::get('message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
        Route::post('message-templates', [MessageTemplateController::class, 'store'])->name('message-templates.store');
        Route::get('message-templates/{messageTemplate}', [MessageTemplateController::class, 'show'])->name('message-templates.show');
        Route::put('message-templates/{messageTemplate}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
        Route::delete('message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');
        Route::post('message-templates/{id}/restore', [MessageTemplateController::class, 'restore'])->name('message-templates.restore')->whereNumber('id');
    });

    // Document Folders
    Route::get('document-folders', [DocumentFolderController::class, 'index'])->name('document-folders.index');
    Route::post('document-folders', [DocumentFolderController::class, 'store'])->name('document-folders.store');
    Route::get('document-folders/{documentFolder}', [DocumentFolderController::class, 'show'])->name('document-folders.show');
    Route::put('document-folders/{documentFolder}', [DocumentFolderController::class, 'update'])->name('document-folders.update');
    Route::delete('document-folders/{documentFolder}', [DocumentFolderController::class, 'destroy'])->name('document-folders.destroy');
    Route::post('document-folders/{id}/restore', [DocumentFolderController::class, 'restore'])->name('document-folders.restore')->whereNumber('id');
    Route::put('document-folders/{documentFolder}/move', [DocumentFolderController::class, 'move'])->name('document-folders.move');

    // Documents
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('documents/{id}/restore', [DocumentController::class, 'restore'])->name('documents.restore')->whereNumber('id');
    Route::post('documents/{document}/lock', [DocumentController::class, 'lock'])->name('documents.lock');
    Route::post('documents/{document}/unlock', [DocumentController::class, 'unlock'])->name('documents.unlock');
    Route::put('documents/{document}/move', [DocumentController::class, 'move'])->name('documents.move');
    Route::post('documents/{document}/publish', [DocumentController::class, 'publish'])->name('documents.publish');
    Route::post('documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');

    // Document Download/Serve (signed temporary URLs)
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/serve', [DocumentController::class, 'serve'])->name('documents.serve');

    // Document Versions (nested under documents)
    Route::get('documents/{documentId}/versions', [DocumentVersionController::class, 'index'])->name('documents.versions.index');
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
    Route::get('documents/versions/{documentVersion}', [DocumentVersionController::class, 'show'])->name('documents.versions.show');
    Route::delete('documents/versions/{documentVersion}', [DocumentVersionController::class, 'destroy'])->name('documents.versions.destroy');

    // Document Version Download/Serve (signed temporary URLs)
    Route::get('documents/versions/{documentVersion}/download', [DocumentVersionController::class, 'download'])->name('documents.versions.download');
    Route::get('documents/versions/{documentVersion}/serve', [DocumentVersionController::class, 'serve'])->name('documents.versions.serve');

    // Document Shares (nested under documents)
    Route::get('documents/{documentId}/shares', [DocumentShareController::class, 'index'])->name('documents.shares.index');
    Route::post('documents/{document}/shares', [DocumentShareController::class, 'store'])->name('documents.shares.store');
    Route::get('documents/shares/{documentShare}', [DocumentShareController::class, 'show'])->name('documents.shares.show');
    Route::delete('documents/shares/{documentShare}', [DocumentShareController::class, 'destroy'])->name('documents.shares.destroy');

    // WhatsApp Messages (read-only)
    Route::middleware('crm-feature:whatsapp.enabled')->group(function () {
        Route::get('whatsapp-messages', [WhatsAppMessageController::class, 'index'])->name('whatsapp-messages.index');
        Route::get('whatsapp-messages/{whatsAppMessage}', [WhatsAppMessageController::class, 'show'])->name('whatsapp-messages.show');

        // WhatsApp Accounts
        Route::get('whatsapp-accounts', [WhatsAppAccountController::class, 'index'])->name('whatsapp-accounts.index');
        Route::post('whatsapp-accounts', [WhatsAppAccountController::class, 'store'])->name('whatsapp-accounts.store');
        Route::get('whatsapp-accounts/{whatsAppAccount}', [WhatsAppAccountController::class, 'show'])->name('whatsapp-accounts.show');
        Route::put('whatsapp-accounts/{whatsAppAccount}', [WhatsAppAccountController::class, 'update'])->name('whatsapp-accounts.update');
        Route::delete('whatsapp-accounts/{whatsAppAccount}', [WhatsAppAccountController::class, 'destroy'])->name('whatsapp-accounts.destroy');
        Route::post('whatsapp-accounts/{id}/restore', [WhatsAppAccountController::class, 'restore'])->name('whatsapp-accounts.restore')->whereNumber('id');
        Route::post('whatsapp-accounts/connect', [WhatsAppAccountController::class, 'connect'])->name('whatsapp-accounts.connect');
        Route::post('whatsapp-accounts/{whatsAppAccount}/disconnect', [WhatsAppAccountController::class, 'disconnect'])->name('whatsapp-accounts.disconnect');
        Route::post('whatsapp-accounts/{whatsAppAccount}/sync-phone-numbers', [WhatsAppAccountController::class, 'syncPhoneNumbers'])->name('whatsapp-accounts.sync-phone-numbers');
    });

});

/*
|--------------------------------------------------------------------------
| Portal User Management (Internal CRM Admin)
|--------------------------------------------------------------------------
|
| These routes require auth:tenant-api (internal User model).
| Portal users are managed by CRM admins with appropriate permissions.
|
*/
Route::prefix('crm')->name('crm.')->group(function () {

    Route::apiResource('portal-users', PortalUserController::class);

    Route::prefix('portal-users/{portalUser}')->name('portal-users.')->group(function () {
        Route::post('invite', [PortalUserController::class, 'invite'])->name('invite');
        Route::post('activate', [PortalUserController::class, 'activate'])->name('activate');
        Route::post('deactivate', [PortalUserController::class, 'deactivate'])->name('deactivate');
    });

    Route::apiResource('portal-person-links', PortalPersonLinkController::class)->except(['update']);

});

// Public Document Access (no auth required)
Route::withoutMiddleware('auth:tenant-api')->prefix('crm')->name('crm.')->group(function () {
    Route::post('documents/shared/{token}', [PublicDocumentController::class, 'access'])->name('documents.shared.access');
});

// Webhook endpoints (no auth required, Meta sends unauthenticated requests)
Route::withoutMiddleware('auth:tenant-api')->prefix('crm')->name('crm.')->group(function () {
    Route::get('webhook/whatsapp/{whatsAppAccount}', [WhatsAppWebhookController::class, 'verify'])->name('webhook.whatsapp.verify');
    Route::post('webhook/whatsapp/{whatsAppAccount}', [WhatsAppWebhookController::class, 'handle'])->name('webhook.whatsapp.handle');
});
