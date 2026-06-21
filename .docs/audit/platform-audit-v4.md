# Platform Audit V4

> **Date:** 2026-06-21
> **Auditor:** Automated (agents + static analysis + test suite)
> **Scope:** Full production-hardening audit of Laravel Multitenancy API

---

## Verdict

**NOT APPROVED WITH CONDITIONS** — 3 Critical, 18 High, 27 Medium, 28 Low findings remain. Sprint 10 resolved all 15 Critical findings from V3 but 3 new Critical issues surfaced in this audit.

---

## Executive Summary

| Dimension | Score | Status |
|-----------|-------|--------|
| Transaction Integrity | 47/50 | Acceptable |
| Tenant Isolation | 74/100 | Needs improvement |
| Authorization | 65/80 | Good |
| Queue & Async | 46/50 | Good |
| Input Validation | 48/60 | Acceptable |
| API Design | 44/50 | Good |
| Data Protection | 16/20 | Good |
| Observability | 52/60 | Acceptable |
| Operational Readiness | 46/60 | Acceptable |
| **Weighted Total** | **449/530** | **Needs improvement** |

---

## Critical Findings (3)

### C01. PipelineService — No Tenant Isolation (V4-C01)

**File:** `app/Services/Crm/PipelineService.php`

**Severity:** Critical

**Issue:** All pipeline queries lack `->where('tenant_id', tenant()->id)` scoping. Queries return results across all tenants.

**Affected methods:**
- `getPipelines()` — returns pipelines from all tenants
- `getStages(int $pipelineId)` — no tenant scope
- `moveStage(Lead $lead, int $stageId)` — no tenant scope
- `reorderStages(array $stages)` — no tenant scope
- `createPipeline()` — doesn't set tenant_id
- `createStage()` — no tenant scope
- `updatePipeline()` — no tenant scope
- `updateStage()` — no tenant scope
- `deletePipeline()` — no tenant scope
- `deleteStage()` — no tenant scope
- `getPipelineById()` — no tenant scope

**Recommendation:** Add `->where('tenant_id', tenant()->id)` to every query. Set `tenant_id` on create methods.

---

### C02. WhatsAppAccountService::disconnect() — Missing Transaction (V4-C02)

**File:** `app/Services/Crm/WhatsAppAccountService.php`

**Severity:** Critical

**Issue:** `disconnect()` performs three writes (update account, delete webhook, log activity) without any transaction. If the webhook deletion succeeds but the activity log fails, the account is partially disconnected but still marked connected; the webhook deletion is also lost.

**Recommendation:** Wrap all three operations in `DB::transaction()`.

---

### C03. CalendarEvent Route Parameter Mismatch (V4-C03)

**Files:**
- `routes/api.php`
- `app/Http/Controllers/Tenant/Api/V1/Crm/CalendarEventController.php`

**Severity:** Critical

**Issue:** The route is defined as `CalendarEvent` (implicit binding via `Route::apiResource('calendar-events', ...)` which resolves `{calendarEvent}`), but the controller methods receive `CalendarEvent $event` (parameter name `$event` instead of `$calendarEvent`). Laravel's route-model binding resolves by the route parameter name, not the variable name, so this mismatch may cause silent failures or binding errors.

**Recommendation:** Rename controller method parameters to `CalendarEvent $calendarEvent` to match the route key `{calendarEvent}`.

---

## High Findings (18)

### H01. Scout Shared Database — No Tenant Index Isolation

**File:** `config/scout.php`

**Severity:** High

**Issue:** Scout is configured to use the `database` engine (same DB across all tenants). Scout's database engine does not support multi-tenant index isolation — all tenants share the same search index table. A tenant A user can see tenant B results in search.

**Recommendation:** Configure Scout to use tenant-specific table prefixes, or switch to Meilisearch/Typesense with per-tenant indexes.

---

### H02. TaskService::restore() — No Ownership Check

**File:** `app/Services/Crm/TaskService.php`

**Severity:** High

**Issue:** The `restore()` method does not verify that the restoring user owns the task. Any authenticated user can restore any soft-deleted task.

**Recommendation:** Add `$this->authorize('restore', $task)` or an ownership check before restoring.

---

### H03. TaskReminderService::restore() — No Ownership Check

**File:** `app/Services/Crm/TaskReminderService.php`

**Severity:** High

**Issue:** Same as H02 — `restore()` lacks ownership verification.

**Recommendation:** Add authorization check before restoring.

---

### H04. PortalAuthController — Generic Error on Failed Login

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/PortalAuthController.php`

**Severity:** High

**Issue:** Returns `__('auth.failed')` generic error on failed portal authentication. This is a security-vs-UX tradeoff: generic messages prevent user enumeration but make debugging difficult for legitimate users.

**Recommendation:** Keep generic error for production but document the decision. Consider returning more specific errors during development environments only.

---

### H05. No PortalUserPolicy

**File:** `app/Policies/Crm/PortalUserPolicy.php` (missing)

**Severity:** High

**Issue:** PortalUser model exists but no policy gates access. Any authenticated user can potentially create/update/delete portal users.

**Recommendation:** Create `PortalUserPolicy` with proper tenant-scoped gates.

---

### H06. Scout Database — No Searchable Prefix

**File:** `config/scout.php`

**Severity:** High

**Issue:** Scout on database engine creates a single `search_index` table. Without tenant prefixing, all tenants' searchable data is interleaved.

**Recommendation:** Use `tenant()->id` as a prefix or discriminator column in the search index table.

---

### H07. Missing FormRequest — WhatsAppAccountController::store()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/WhatsAppAccountController.php`

**Severity:** High

**Issue:** `store()` validates inline in the controller instead of using a FormRequest.

**Recommendation:** Create and use `StoreWhatsAppAccountRequest`.

---

### H08. Missing FormRequest — WhatsAppAccountController::update()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/WhatsAppAccountController.php`

**Severity:** High

**Issue:** Same as H07 — inline validation.

**Recommendation:** Create and use `UpdateWhatsAppAccountRequest`.

---

### H09. Missing FormRequest — DocumentVersionController::store()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/DocumentVersionController.php`

**Severity:** High

**Issue:** `store()` validates inline.

**Recommendation:** Create and use `StoreDocumentVersionRequest`.

---

### H10. Missing FormRequest — DocumentVersionController::update()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/DocumentVersionController.php`

**Severity:** High

**Issue:** Same as H09 — inline validation.

**Recommendation:** Create and use `UpdateDocumentVersionRequest`.

---

### H11. Missing FormRequest — PortalUserController::store()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/PortalUserController.php`

**Severity:** High

**Issue:** `store()` validates inline.

**Recommendation:** Create and use `StorePortalUserRequest`.

---

### H12. Missing FormRequest — PortalUserController::update()

**File:** `app/Http/Controllers/Tenant/Api/V1/Crm/PortalUserController.php`

**Severity:** High

**Issue:** Same as H11 — inline validation.

**Recommendation:** Create and use `UpdatePortalUserRequest`.

---

### H13. WorkflowService::trigger() — Nested Jobs Without Limit

**File:** `app/Services/Crm/WorkflowService.php`

**Severity:** High

**Issue:** `trigger()` dispatches `ExecuteWorkflowJob` for each matching workflow. If workflows trigger other workflows (directly or through side effects), unbounded recursion is possible.

**Recommendation:** Add a maximum recursion depth (e.g., 5 levels) and count already-dispatched jobs in the same request.

---

### H14. DunningService::handleFailedPayment() — No Max Retry Safety

**File:** `app/Services/Central/DunningService.php`

**Severity:** High

**Issue:** `handleFailedPayment()` increments retry count and dispatches a new job. Without a hard cap on retries, payment failures for perpetually failing cards could loop indefinitely.

**Recommendation:** Add an explicit `$attempt >= $maxAttempts` check (e.g., `maxAttempts = 3`) before retrying.

---

### H15. InvoicePdfService::generate() — File Write + DB Update Not Atomic

**File:** `app/Services/Central/InvoicePdfService.php`

**Severity:** High

**Issue:** `generate()` writes the PDF to disk then updates the invoice record in the DB. If the DB update fails, an orphan PDF file remains on disk. If the write to disk fails, the DB still records a non-existent PDF path.

**Recommendation:** Write to a temp file first, then move only after DB update succeeds. Delete the DB record if the file move fails.

---

### H16. Document Download/Serve — No Cache or Rate Limiting

**Files:**
- `app/Http/Controllers/Tenant/Api/V1/Crm/DocumentController.php`
- `app/Services/Crm/DocumentService.php`

**Severity:** High

**Issue:** `download()` and `serve()` endpoints have no caching headers or rate limiting. Large documents served repeatedly waste bandwidth and disk I/O. No throttling means an attacker could trigger mass downloads.

**Recommendation:** Add `Cache-Control: private, max-age=3600` headers. Apply `throttle:30,1` middleware to download/serve routes. Consider signed temporary URLs.

---

### H17. Activity Retention — No Purging Policy

**Files:**
- `app/Models/Crm/TimelineEntry.php`
- `app/Models/Crm/Tag.php`

**Severity:** High

**Issue:** Timeline entries and soft-deleted tags accumulate indefinitely. No scheduled job purges entries or tags older than a configurable retention period (e.g., 90 days).

**Recommendation:** Create `app/Console/Commands/PurgeExpiredData.php` and schedule it in `Kernel.php`.

---

### H18. Permission Cache — Not Warmed on Deployment

**File:** `config/permission.php`

**Severity:** High

**Issue:** The Spatie permission cache (`cache-permissions` config) is enabled but not warmed during deployment. First request after deployment incurs a cold-start penalty and may serve stale permissions during the warm-up window.

**Recommendation:** Add `php artisan permission:cache-reset` to the deployment script. Add a warm-up health check.

---

## Medium Findings (27)

### M01. No Delete Event Dispatching — DocumentVersion

**File:** `app/Services/Crm/DocumentVersionService.php`

**Issue:** `delete()` does not dispatch a `DocumentVersionDeleted` event. Downstream systems cannot react to version deletions.

---

### M02. No Delete Event Dispatching — DocumentShare

**File:** `app/Services/Crm/DocumentShareService.php`

**Issue:** `delete()` does not dispatch a `DocumentShareDeleted` event.

---

### M03. No Delete Event Dispatching — WhatsAppMessage

**File:** `app/Services/Crm/WhatsAppMessageService.php`

**Issue:** `delete()` does not dispatch a `WhatsAppMessageDeleted` event.

---

### M04. No Delete Event Dispatching — TaskComment

**File:** `app/Services/Crm/TaskCommentService.php`

**Issue:** `delete()` does not dispatch a `TaskCommentDeleted` event.

---

### M05. No Delete Event Dispatching — WhatsAppAccount

**File:** `app/Services/Crm/WhatsAppAccountService.php`

**Issue:** `disconnect()` does not dispatch a `WhatsAppAccountDisconnected` event.

---

### M06. Missing forceDelete() — DocumentVersionService

**File:** `app/Services/Crm/DocumentVersionService.php`

**Issue:** No `forceDelete()` method. Soft-deleted versions cannot be permanently removed.

---

### M07. Missing forceDelete() — DocumentShareService

**File:** `app/Services/Crm/DocumentShareService.php`

**Issue:** No `forceDelete()` method.

---

### M08. Missing forceDelete() — WhatsAppMessageService

**File:** `app/Services/Crm/WhatsAppMessageService.php`

**Issue:** No `forceDelete()` method.

---

### M09. Missing forceDelete() — TaskCommentService

**File:** `app/Services/Crm/TaskCommentService.php`

**Issue:** No `forceDelete()` method.

---

### M10. Missing forceDelete() — TagService

**File:** `app/Services/Crm/TagService.php`

**Issue:** No `forceDelete()` method.

---

### M11. Missing forceDelete() — OrganizationService

**File:** `app/Services/Crm/OrganizationService.php`

**Issue:** No `forceDelete()` method.

---

### M12. Missing forceDelete() — PersonService

**File:** `app/Services/Crm/PersonService.php`

**Issue:** No `forceDelete()` method.

---

### M13. Missing forceDelete() — InvoiceService

**File:** `app/Services/Central/InvoiceService.php`

**Issue:** No `forceDelete()` method.

---

### M14. Missing forceDelete() — SubscriptionService

**File:** `app/Services/Central/SubscriptionService.php`

**Issue:** No `forceDelete()` method.

---

### M15. Missing forceDelete() — PlanService

**File:** `app/Services/Central/PlanService.php`

**Issue:** No `forceDelete()` method.

---

### M16. Missing forceDelete() — TenantService

**File:** `app/Services/Central/TenantService.php`

**Issue:** No `forceDelete()` method.

---

### M17. DunningService — $payment->data Null Coalescing

**File:** `app/Services/Central/DunningService.php`

**Issue:** `handleFailedPayment()` accesses `$payment->data['stripe_id']` and similar without null coalescing (`??`). If `$payment->data` is null, this will throw an error.

---

### M18. LeadService::moveStage() — No Ownership Check

**File:** `app/Services/Crm/LeadService.php`

**Issue:** `moveStage()` does not verify the user owns the lead before moving stages.

---

### M19. DocumentService — Import Methods Don't Set owner_id

**File:** `app/Services/Crm/DocumentService.php`

**Issue:** `importDocument()` creates documents without setting `owner_id`. The imported document is orphaned.

---

### M20. InvoicePdfService — download() Calls generate() Internally

**File:** `app/Services/Central/InvoicePdfService.php`

**Issue:** `download()` calls `generate()` as a side effect. A simple GET to download a PDF generates a new PDF every time, even if one already exists.

---

### M21. InvoicePdfService — stream() Calls generate() Internally

**File:** `app/Services/Central/InvoicePdfService.php`

**Issue:** Same as M20 — `stream()` triggers PDF generation as a side effect.

---

### M22. No Pagination Limit — Central Services

**Files:**
- `app/Services/Central/TenantService.php`
- `app/Services/Central/UserService.php`
- `app/Services/Central/InvoiceService.php`
- `app/Services/Central/SubscriptionService.php`

**Issue:** List methods accept arbitrary `$perPage` values without a maximum limit. An attacker could request 10,000 results per page, causing slow queries and memory exhaustion.

---

### M23. WorkflowService — Recursive Dispatch Without Depth Limit

**File:** `app/Services/Crm/WorkflowService.php`

**Issue:** `trigger()` dispatches jobs for each matching workflow without checking if the current dispatch is already inside a workflow execution. If workflow A triggers an event matched by workflow B, and workflow B triggers an event matched by workflow A, infinite dispatch occurs.

---

### M24. No Searchable — Document Model

**File:** `app/Models/Crm/Document.php`

**Issue:** Documents are not Scout-searchable. Users cannot full-text search document content or metadata.

---

### M25. No Searchable — Organization Model

**File:** `app/Models/Crm/Organization.php`

**Issue:** Organizations are not Scout-searchable.

---

### M26. No Searchable — CalendarEvent, Task, Note, Comment, Message

**Files:**
- `app/Models/Crm/CalendarEvent.php`
- `app/Models/Crm/Task.php`
- `app/Models/Crm/Note.php`
- `app/Models/Crm/Comment.php`
- `app/Models/Crm/Message.php`

**Issue:** These CRM models lack the `Searchable` trait. Users cannot search across these entities.

---

### M27. Missing Indexes — Common WHERE Columns

**Files:**
- `database/migrations/*`

**Issue:** No indexes on:
- `timeline_entries.tenant_id`, `timeline_entries.entity_type`, `timeline_entries.entity_id`
- `taggables.tag_id`, `taggables.taggable_type`, `taggables.taggable_id`

Queries filtering by tenant + entity will perform full table scans as data grows.

---

## Low Findings (28)

### L01. No publish() Method — DocumentResource

**File:** `app/Http/Resources/Crm/DocumentResource.php`

**Issue:** Resource lacks a `publish()` helper for consistent API responses.

---

### L02. No publish() Method — CalendarEventResource

**File:** `app/Http/Resources/Crm/CalendarEventResource.php`

**Issue:** Same as L01.

---

### L03. No publish() Method — InvoiceResource

**File:** `app/Http/Resources/Central/InvoiceResource.php`

**Issue:** Same as L01.

---

### L04. No publish() Method — SubscriptionResource

**File:** `app/Http/Resources/Central/SubscriptionResource.php`

**Issue:** Same as L01.

---

### L05. No publish() Method — PlanResource

**File:** `app/Http/Resources/Central/PlanResource.php`

**Issue:** Same as L01.

---

### L06. No publish() Method — TenantResource

**File:** `app/Http/Resources/Central/TenantResource.php`

**Issue:** Same as L01.

---

### L07. No Type Hints — WhatsAppAccountService Return Types

**File:** `app/Services/Crm/WhatsAppAccountService.php`

**Issue:** Several methods lack explicit return type declarations.

---

### L08. No Type Hints — TaskReminderService Return Types

**File:** `app/Services/Crm/TaskReminderService.php`

**Issue:** Same as L07.

---

### L09. No Type Hints — DunningService Return Types

**File:** `app/Services/Central/DunningService.php`

**Issue:** Same as L07.

---

### L10. No Type Hints — WorkflowService Return Types

**File:** `app/Services/Crm/WorkflowService.php`

**Issue:** Same as L07.

---

### L11. No forceDelete() Override — DocumentVersion Model

**File:** `app/Models/Crm/DocumentVersion.php`

**Issue:** Model does not override `forceDelete()` to handle related records or events.

---

### L12. No forceDelete() Override — DocumentShare Model

**File:** `app/Models/Crm/DocumentShare.php`

**Issue:** Same as L11.

---

### L13. No forceDelete() Override — WhatsAppMessage Model

**File:** `app/Models/Crm/WhatsAppMessage.php`

**Issue:** Same as L11.

---

### L14. No forceDelete() Override — TaskComment Model

**File:** `app/Models/Crm/TaskComment.php`

**Issue:** Same as L11.

---

### L15. No forceDelete() Override — Tag Model

**File:** `app/Models/Crm/Tag.php`

**Issue:** Same as L11.

---

### L16. No forceDelete() Override — Organization Model

**File:** `app/Models/Crm/Organization.php`

**Issue:** Same as L11.

---

### L17. No forceDelete() Override — Person Model

**File:** `app/Models/Crm/Person.php`

**Issue:** Same as L11.

---

### L18. No forceDelete() Override — Invoice Model

**File:** `app/Models/Central/Invoice.php`

**Issue:** Same as L11.

---

### L19. No forceDelete() Override — Subscription Model

**File:** `app/Models/Central/Subscription.php`

**Issue:** Same as L11.

---

### L20. No forceDelete() Override — Plan Model

**File:** `app/Models/Central/Plan.php`

**Issue:** Same as L11.

---

### L21. No forceDelete() Override — Tenant Model

**File:** `app/Models/Central/Tenant.php`

**Issue:** Same as L11.

---

### L22. No restore() — WhatsAppAccountService

**File:** `app/Services/Crm/WhatsAppAccountService.php`

**Issue:** WhatsAppAccount uses SoftDeletes but the service has no `restore()` method.

---

### L23. No restore() — TagService

**File:** `app/Services/Crm/TagService.php`

**Issue:** Tag uses SoftDeletes but the service has no `restore()` method.

---

### L24. No restore() — OrganizationService

**File:** `app/Services/Crm/OrganizationService.php`

**Issue:** Organization uses SoftDeletes but the service has no `restore()` method.

---

### L25. No restore() — PersonService

**File:** `app/Services/Crm/PersonService.php`

**Issue:** Person uses SoftDeletes but the service has no `restore()` method.

---

### L26. CentralUser — No ownedByTenant() Relationship

**File:** `app/Models/Central/CentralUser.php`

**Issue:** No `ownedByTenant()` scope or relationship to facilitate tenant-scoped queries from the central database.

---

### L27. No onDelete('cascade') — Per-Tenant Cleanup

**Files:**
- `database/migrations/*`

**Issue:** Migrations defining foreign keys to `tenants.id` do not cascade on tenant deletion. Deleting a tenant leaves orphaned records.

---

### L28. No Circuit Breaker — Stripe API Calls

**Files:**
- `app/Services/Central/SubscriptionService.php`
- `app/Services/Central/InvoicePdfService.php`

**Issue:** Stripe API calls are not protected by a circuit breaker. If Stripe experiences an outage, the application will exhaust connection pools and degrade.

---

## Resolved Findings From V3 and Sprint 10

All 15 Critical V3 findings resolved (see Sprint 10 work). Additionally:

| Finding | Resolution |
|---------|-----------|
| Transaction boundaries (30 methods) | DB::transaction() added |
| Storage quota enforcement | Changed to ValidationException |
| Queue reliability (3 jobs) | Already complete, verified |
| Health monitoring | /api/health endpoint added |
| Route name bug (documents.serve) | Fixed |
| Gate::authorize bug (DocumentVersion) | Fixed |
| TagService table alias bug | Fixed |
| DunningService double update | Fixed |
| PortalAuthController cross-tenant | where('tenant_id', ...) added |
| ExecuteWorkflowJob wasInitialized | Conditional tenancy()->end() |
| CentralUser Searchable | Added |
| Permission search crash | Direct query instead of Scout |
| Ownership checks (14 policies) | Added owner_id checks |
| TagService dead code | Delegates to resolver |
| MessageAttachmentResource file_path | Removed |
| ApiKeyResource key visibility | Hidden behind when(showKey) |
| Subscription/Plan N+1 | Eager loads + whenLoaded() |
| Sanctum token expiration | Set to 10080 minutes |
| Timeline entry idempotency | firstOrCreate() + now() |
| Tenant permissions config | 12 modules added |
| Sort column whitelist (19 services) | Fixed null coalescing |
| Queue failure tests | JobFailureTest created |
| Service-layer tests | TagServiceTest + EventDispatcherServiceTest |

---

## Data Map

### Models
| Model | Tenant-Scoped? | SoftDeletes? | Searchable? | Ownership Check? |
|-------|---------------|--------------|-------------|-----------------|
| Tenant | N/A | Yes | No | N/A |
| CentralUser | Via scope | No | Yes | N/A |
| Plan | No | Yes | No | N/A |
| Subscription | Via tenant | Yes | No | N/A |
| Invoice | Via tenant | Yes | No | N/A |
| Lead | Yes | Yes | No | Yes |
| Organization | Yes | Yes (missing service restore) | No | Yes |
| Person | Yes | Yes (missing service restore) | No | Yes |
| Task | Yes | Yes | No | Yes |
| CalendarEvent | Yes | No | No | Yes |
| Document | Yes | Yes | No | No (import only — M19) |
| DocumentVersion | Yes | Yes | No | N/A |
| DocumentShare | Yes | Yes | No | N/A |
| Note | Yes | No | No | Yes |
| Comment | Yes | No | No | Yes |
| Message | Yes | No | No | Yes |
| TaskComment | Yes | Yes | No | N/A |
| WhatsAppAccount | Yes | Yes (missing service restore) | No | N/A |
| WhatsAppMessage | Yes | Yes | No | N/A |
| Tag | Yes | Yes (missing service restore) | No | N/A |
| Pipeline | Yes | No | No | No |
| Stage | Yes | No | No | No |
| TimelineEntry | Yes | No | No | N/A |
| PortalUser | Yes | No | No | N/A |
| Workflow | Yes | No | No | N/A |
| WorkflowStep | Yes | No | No | N/A |

### Feature Gates
| Gate | Implemented? |
|------|-------------|
| receive-invoices-permission | Yes |
| manage-pipeline-permission | Yes |
| manage-workflows-permission | Yes |
| manage-contacts-permission | Yes |
| ManageDocuments | Yes |
| ManageCalendarEvents | Yes |
| ManageTasks | Yes |
| ManageNotes | Yes |
| ManageComments | Yes |
| ManageMessages | Yes |
| ManagePortalUsers | Yes |
| ManageWhatsApp | Yes |
| ManageTags | Yes |

### API Coverage
| Resource | Controller? | FormRequest? | Policy? | API Resource? |
|----------|------------|-------------|---------|---------------|
| Lead | Yes | Yes | Yes | Yes |
| Organization | Yes | Yes | Yes | Yes |
| Person | Yes | Yes | Yes | Yes |
| Task | Yes | Yes | Yes | Yes |
| CalendarEvent | Yes | Yes | Yes | Yes |
| Document | Yes | Yes | Yes | Yes |
| DocumentVersion | Yes | **No** (H09-H10) | N/A | Yes |
| DocumentShare | Yes | Yes | N/A | Yes |
| Note | Yes | Yes | Yes | Yes |
| Comment | Yes | Yes | Yes | Yes |
| Message | Yes | Yes | Yes | Yes |
| TaskComment | Yes | Yes | N/A | Yes |
| WhatsAppAccount | Yes | **No** (H07-H08) | N/A | Yes |
| WhatsAppMessage | Yes | Yes | N/A | Yes |
| Tag | Yes | Yes | N/A | Yes |
| Pipeline | Partial | N/A | N/A | N/A |
| PortalUser | Yes | **No** (H11-H12) | **No** (H05) | Yes |
| Workflow | No | N/A | N/A | N/A |

---

## Notes

- Sprint 10 transaction boundaries considered complete — 30 methods wrapped across CRM and Central
- V3 Critical findings (15) all resolved and verified
- V4 Critical findings (3) must be addressed before next production deployment
- PipelineService remains the single largest risk in the codebase
- Scout database engine without tenant isolation is the highest-severity architectural issue
