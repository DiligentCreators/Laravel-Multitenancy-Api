# Platform Audit v3 — Full Enterprise Review

**Application:** Laravel-Multitenancy-Api  
**Audit Date:** 2026-06-20  
**Scope:** All production code across Central + Tenant + CRM + Portal domains  
**Methodology:** Full source code verification (no assumptions from prior audits)  
**Total Files Reviewed:** ~450+ across Models, Controllers, Services, Jobs, Policies, Requests, Resources, Config, Routes, Tests, Migrations  
**Test Suite:** 792 tests, 2128 assertions, 0 failures

---

## Executive Summary

This audit evaluated the codebase across 34 distinct areas. The platform demonstrates **strong architectural foundations** — consistent service layer pattern, thorough multi-tenant isolation via `TenantScope`/`BelongsToTenant`, well-structured queue job tenant handling, and excellent cross-tenant security tests. However, the audit uncovered **15 Critical**, **27 High**, **40 Medium**, and **41 Low** severity findings that must be addressed before production deployment.

The most critical issues fall into five categories:

1. **Authorization gaps** — No ownership checks in any CRM policy; 12 permission modules missing from tenant config; 36 policies rely solely on permission checks without verifying record ownership
2. **Data integrity risks** — Zero idempotency protection on timeline entries; executeWorkflowJob has a cross-tenant context leak; events dispatched outside transactions in delete() methods
3. **Security vulnerabilities** — Cross-tenant portal authentication bypass; API keys exposed in responses; file_path exposed; SVG+XSS vector; Sanctum tokens never expire
4. **Production readiness gaps** — Daily-only monitoring (insufficient for queue health); no real-time alerting; no cursor pagination; unsanitized sort columns in 36+ services; 3 Searchable trait crashes at runtime
5. **Test coverage gaps** — Zero unit tests; no queue job failure tests; no document file upload tests at HTTP level

---

## Scoring Summary

| Category | Score | Assessment |
|----------|-------|------------|
| **Architecture** | 7.5/10 | Strong service layer, but inconsistencies in event coverage and transaction boundaries |
| **Security** | 5.5/10 | Critical auth gaps; good tenant isolation but policies lack ownership checks |
| **Scalability** | 5.0/10 | No cursor pagination; collection search engine; unsanitized sort injection risks |
| **SaaS Readiness** | 6.0/10 | Multi-tenant foundation solid; missing billing integration, usage metering gaps |
| **Production Readiness** | 5.0/10 | No real-time alerting; daily monitoring; Sanctum tokens never expire; queue driver in dev mode |
| **Extensibility** | 6.5/10 | MorphableEntityResolver not extensible; hardcoded type lists; no module registration system |
| **Test Coverage** | 5.0/10 | Zero unit tests; critical paths untested (jobs, file uploads, quotas) |

---

## Detailed Findings

### 1. Multi-Tenant Isolation

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| MT-1 | **Critical** | `PortalAuthController::login()` queries `PortalUser::where('email', ...)` without tenant scoping. If tenancy not initialized, returns user from ANY tenant. | `PortalAuthController.php` | 22 |
| MT-2 | **High** | `InitializeTenancy` middleware silently continues if tenant resolution fails. No error raised — unscoped queries possible. | `InitializeTenancy.php` | 40-48 |
| MT-3 | **High** | `TenantScope` is a no-op when `tenancy()->initialized` is false. ALL tenant-scoped queries return cross-tenant data. | `TenantScope.php` | 26-28 |
| MT-4 | **Medium** | `MetricsService` raw `DB::table('crm_documents')` queries bypass TenantScope entirely. | `MetricsService.php` | 69-83 |
| MT-5 | **Medium** | `BelongsToTenant::creating()` only auto-assigns `tenant_id` when tenancy is initialized. | `BelongsToTenant.php` | 35-38 |
| MT-6 | **Low** | `NotifiesActions` trait uses `User::find($userId)` — safe via TenantScope but fragile | `NotifiesActions.php` | 13 |

**Positive:** Tenant isolation via `TenantScope`/`BelongsToTenant` is consistently applied across 43 tenant-scoped models. Queue jobs properly initialize/end tenancy with `finally` cleanup. Cross-tenant security tests validate workflow tenant isolation.

---

### 2. Global Scopes & Soft Deletes

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| GS-1 | **High** | No runtime protection against `TenantScope` bypass. Anyone can call `withoutGlobalScope()`. | `TenantScope.php` | 20 |
| GS-2 | **Medium** | `TenantController::forceDelete()` uses fragile two-phase logic (controller + observer). | `TenantController.php` | 131-135 |
| GS-3 | **Low** | `PortalUserService::restore()` is dead code — no route, no `resolveRouteBindingQuery`. | `PortalUserService.php` | 67-75 |
| GS-4 | **Low** | 21 tenant-facing API resources expose `deleted_at` inconsistently. | Various `*Resource.php` | ~25-31 |
| GS-5 | **Low** | CRM SoftDelete models lack `resolveRouteBindingQuery` overrides. | All CRM SoftDelete models | — |
| GS-6 | **Low** | Redundant `whereNull('deleted_at')` in `DashboardController` and `MetricsService`. | `DashboardController.php`, `MetricsService.php` | 32, 70+ |

**Positive:** All 37 CRM models correctly use `BelongsToTenant`. No production `withoutGlobalScope()` calls. All forceDelete/restore operations properly use `withTrashed()`.

---

### 3. Tenant-Aware Queues

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| QJ-1 | **Critical** | `ExecuteWorkflowJob` missing `$wasInitialized` guard — unconditionally initializes/ends tenancy, causing cross-tenant context leak. | `ExecuteWorkflowJob.php` | 34, 47-49 |
| QJ-2 | **Medium** | `ExecuteWorkflowJob` initializes tenant context before cross-tenant validation check. | `ExecuteWorkflowJob.php` | 34, 41-43 |
| QJ-3 | **Low** | Central jobs missing `tenant_id` in `failed()` logging for jobs that operate on specific tenants. | `TenantExportJob.php`, `SyncStripeCustomerJob.php` | 63-69, 51-57 |
| QJ-4 | **Low** | `$tries=0` + `$maxExceptions=3` creates confusing retry behavior interaction. | All 9 jobs | various |
| QJ-5 | **Low** | Redundant `->afterCommit()` in job constructors (connection-level `after_commit => true` already active). | 3 job constructors | 28, 33, 29 |

**Positive:** All 9 jobs have consistent retry/backoff/timeout config. `TriggerWorkflowJob`/`RecordTimelineEntryJob` correctly use `$wasInitialized` guard. Central jobs properly avoid tenancy initialization.

---

### 4. EventDispatcher Architecture

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| ED-1 | **High** | `recordGeneric()` does NOT trigger workflows — silent automation gap for callers expecting full event processing. | `EventDispatcher.php` | 28-38 |
| ED-2 | **Medium** | Events dispatched BEFORE DB delete in 8 services — phantom timeline entries if delete fails. | `PersonService`, `OrganizationService`, `LeadService`, `TaskService`, `ConversationService`, `CalendarEventService`, `WhatsAppAccountService`, `DocumentService` | various |
| ED-3 | **Medium** | `CommentService::create()`/`NoteService::create()` events dispatched outside transaction. | `CommentService.php`, `NoteService.php` | 50-60, 52-61 |
| ED-4 | **Low** | Redundant `->afterCommit()` calls consistent but unnecessary. | All dispatchers | — |

**Positive:** All 17 CRUD services inject `EventDispatcher`. No dead code in EventDispatcher. Service event coverage is near-complete.

---

### 5. Workflow System

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| WF-1 | **High** | Infinite loop/cascade risk via `executeCreateTask` creating Task model which could trigger additional workflows. | `WorkflowService.php` | 64, 130-143 |
| WF-2 | **High** | `withoutEvents()` suppresses all Eloquent events during workflow action execution — no timeline entries or audit logs for workflow-driven mutations. | `WorkflowService.php` | 64 |
| WF-3 | **High** | Unlimited retries/DOS risk — `$tries=0` + `retryUntil(5min)` means poisoned jobs retry repeatedly, consuming queue worker resources. | `TriggerWorkflowJob`, `ExecuteWorkflowJob`, `RecordTimelineEntryJob` | all |
| WF-4 | **Medium** | No validation on per-action parameters in workflow store/update requests. | `StoreWorkflowRequest.php`, `UpdateWorkflowRequest.php` | 11-19 |
| WF-5 | **Medium** | No workflow execution idempotency — duplicate events cause duplicate workflow execution. | `WorkflowService.php` | 30-43 |
| WF-6 | **Medium** | Notifications sent inside `withoutEvents()` + transaction — ghost notification risk if transaction rolls back. | `WorkflowService.php` | 150 |
| WF-7 | **Low** | Loose comparison (`!=`) in condition evaluation. | `WorkflowService.php` | 84-101 |
| WF-8 | **Low** | Execution logging gaps — no info-level log for successful execution. | `WorkflowService.php` | 51-82 |

**Positive:** WorkflowService has a runtime cross-tenant check. Event suppression via `withoutEvents()` is intentional (prevents re-triggering same entity's workflows).

---

### 6. Timeline System

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| TL-1 | **Critical** | Zero idempotency protection — no unique constraints on `crm_timeline_entries`. Retried jobs create duplicate entries. | `RecordTimelineEntryJob.php`, migration | 43-53 |
| TL-2 | **Medium** | Scout `whereIn()` with large ID sets causes SQL query size and performance issues. | `TimelineService.php` | 33-36 |
| TL-3 | **Medium** | Timeline + workflow dispatch not atomic — two separate jobs dispatched sequentially; if second fails, timeline recorded but workflow not triggered. | `EventDispatcher.php` | 15-26 |
| TL-4 | **Medium** | Unbounded timeline table growth — no retention policy or pruning mechanism. | Migration | all |
| TL-5 | **Low** | Missing index on `caused_by` column. | Migration | 21 |
| TL-6 | **Low** | No explicit tenant guard in `TimelineService::query()` — relies on TenantScope only. | `TimelineService.php` | 12-14 |

---

### 7. Search Infrastructure

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| SI-1 | **Critical** | `CentralUser::search()` called but model has NO Searchable trait — throws `BadMethodCallException` at runtime. | `UserService.php`, `CentralUser.php` | 32 |
| SI-2 | **Critical** | `Permission::search()` called but model has NO Searchable trait — throws at runtime during tenant provisioning. | `TenantProvisioningService.php`, `Permission.php` | 89 |
| SI-3 | **High** | Collection engine is default — loads ALL records into PHP memory for every search query. Unsuitable for production. | `config/scout.php` | 19 |
| SI-4 | **High** | 6 models index only `id` — search on name/title/email returns zero results. | `Role.php`, `SettingDefinition.php`, `Feature.php`, `Plan.php`, `Subscription.php`, `User.php` | various |
| SI-5 | **High** | `TaskCommentService` uses raw LIKE with leading wildcard — full table scan, bypasses Scout. | `TaskCommentService.php` | 23 |
| SI-6 | **Medium** | `ActivityLogController` uses raw LIKE with leading wildcard. | `ActivityLogController.php` | 26 |
| SI-7 | **Medium** | Soft delete handling disabled in Scout config — trashed records appear in search results. | `config/scout.php` | 87 |

**Positive:** 36 models correctly use Searchable trait. Consistent `->keys()` + `whereIn()` pattern across all services. Document prefix-only LIKE queries are acceptably index-friendly.

---

### 8. MorphableEntityResolver

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| MR-1 | **Critical** | 3 requests have hardcoded entity type lists differing from resolver — `CustomField`, `BulkTag`. | `StoreCustomFieldRequest.php`, `BulkTagRequest.php` | 12 |
| MR-2 | **High** | `ALLOWED_TYPES` is a `const` — not extensible by modules. No `register()` method. | `MorphableEntityResolver.php` | 21-34 |
| MR-3 | **Medium** | 7 CRM models missing from resolver (Pipeline, PipelineStage, WhatsAppAccount, TimelineEntry, MessageTemplate, Message, DocumentFolder). | `MorphableEntityResolver.php` | 21-34 |
| MR-4 | **Medium** | `StatusType` and `Workflow` requests allow arbitrary `entity_type` strings — no resolver validation. | `StoreStatusTypeRequest.php`, `StoreWorkflowRequest.php` | 12-13 |
| MR-5 | **Medium** | `MessageService` and `ConversationService` duplicate resolver's type maps — drift risk. | `MessageService.php`, `ConversationService.php` | 74-78, 94-98 |

**Positive:** Resolver is well-tested (6 test cases). `getValidationRule()` is used correctly in most requests. `ConversationParticipantResource` uses resolver correctly.

---

### 9. Soft Deletes

(See Section 2 — Global Scopes & Soft Deletes)

---

### 10. Ownership Model

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| OM-1 | **Critical** | **No ownership checks in any CRM policy** — `owner_id` exists on 14+ models but never verified at authorization layer. | All 36 CRM policies | all |
| OM-2 | **Medium** | `team_id` field exists on 11 models but no `Team` model or relationship exists. | Various `app/Models/Crm/*.php` | various |
| OM-3 | **Medium** | No auto-setting for `created_by`/`updated_by` — must be manually set, often null. | All CRM models | various |

**Positive:** `owner_id` filtering available via query parameters in list endpoints (used as search filter, not access control).

---

### 11. Policies

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| PL-1 | **Critical** | No ownership checks in any of 36 CRM policies — only permission checks. Staff with `tasks.update` can update ANY task. | All 36 CRM policies | all |
| PL-2 | **High** | No `UserPolicy` exists for tenant `User` model — user management has no policy protection. | `User.php` | — |
| PL-3 | **Medium** | `hasPermissionTo()` bypasses `Gate::before` superadmin bypass (CRM policies use direct Spatie call, not Gate). | All 36 CRM policies | all |
| PL-4 | **Medium** | Zero Form Requests override `authorize()` — all rely on `BaseFormRequest::authorize() { return true; }`. | All 100+ Form Requests | — |
| PL-5 | **High** | `subscriptions.forceDelete` naming mismatch with config (`central-permissions.php` uses `subscriptions.force.delete`). | `SubscriptionPolicy.php` | 71 |
| PL-6 | **Medium** | `tenant.list`/`tenant.update` naming mismatch (singular vs plural). | `TenantSettingPolicy.php` | 17, 22 |
| PL-7 | **Low** | 4 models missing `#[UsePolicy]` attribute. | `Module.php`, `TenantSetting.php`, `Role.php`, `User.php` | — |

**Positive:** 57 policy files exist with good coverage. `before()` method grants blanket access to owner/admin roles in CRM policies. Consistent `Gate::authorize()` in controllers.

---

### 12. Permissions

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| PM-1 | **Critical** | **12 permission modules missing from `config/tenant-permissions.php`** — used in policies but never seeded: leads, people, organizations, notes, activities, comments, addresses, pipelines, pipeline-stages, portal-users, organization-people, timeline. | `config/tenant-permissions.php` | all |
| PM-2 | **Low** | 9 central permission modules defined but never used in code (billing, invoice-pdfs, exports, admin-audit-logs, activity-logs, audit-logs). | `config/central-permissions.php` | 184-202 |

**Positive:** Spatie permission cache properly isolated via Tenancy cache bootstrapper. Guard separation (`central-api` vs `tenant-api`) correctly implemented.

---

### 13. Form Requests

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| FR-1 | **High** | 24 endpoints use `$request->validate()` inline instead of FormRequest classes. | Various controllers | all |
| FR-2 | **High** | 12 tenant form requests use bare `exists:` without tenant scope — cross-tenant validation reference. | `StorePersonRequest`, `StoreOrganizationRequest`, `StoreDocumentRequest`, etc. | various |
| FR-3 | **Low** | Mixed array/pipe validation syntax across requests. | Various `*Request.php` | various |

---

### 14. Validation Rules

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| VR-1 | **High** | Polymorphic type/ID pairs NOT validated together — `noteable_type=person` + `noteable_id=999999` passes validation. | `StoreNoteRequest.php`, `StoreCommentRequest.php`, `StoreActivityRequest.php` | 16-18 |
| VR-2 | **Medium** | No usage of `Rule::enum()` — uses `Rule::in()` for enum validation instead. | Various requests | various |
| VR-3 | **Medium** | `Conversation` channel validated as string `in:whatsapp,sms,email,internal` instead of against the enum. | `StoreConversationRequest.php` | 14 |

---

### 15. Sorting

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| SR-1 | **Critical** | **Unsanitized sort columns passed directly to `orderBy()` in 36+ services** — SQL injection via column parameter in MySQL. | 17 Central services + 19 CRM services | all |
| SR-2 | **Medium** | Only `SubscriptionService` implements column whitelisting — all other services vulnerable. | `SubscriptionService.php` (only safe one) | 32-46 |
| SR-3 | **Low** | Inconsistent default sort columns across services (`created_at` vs `name` vs `sort_order`). | Various | all |

---

### 16. Pagination

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| PG-1 | **Medium** | No cursor pagination anywhere — all offset-based. High-volume tables will degrade. | All controllers/services | all |
| PG-2 | **High** | `ModuleController` has no `per_page` cap — memory exhaustion DoS vector. | `ModuleController.php` | 27 |
| PG-3 | **Low** | Inconsistent default per_page values (10, 15, 25, 50). | Various controllers | all |
| PG-4 | **Low** | Duplicate per-page logic (trait vs inline min()). | `PaginatesRequestTrait` vs inline | all |

---

### 17. API Resources

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| AR-1 | **Critical** | `file_path` exposed in `MessageAttachmentResource` — internal storage path leakage. | `MessageAttachmentResource.php` | 18 |
| AR-2 | **Critical** | API `key` exposed in `ApiKeyResource` — raw credentials returned. | `ApiKeyResource.php` | 19 |
| AR-3 | **High** | `SubscriptionResource` missing `whenLoaded()` on tenant/plan — N+1 queries. | `SubscriptionResource.php` | 25-37 |
| AR-4 | **High** | `PlanResource` missing `whenLoaded()` on features — N+1 queries. | `PlanResource.php` | 26 |
| AR-5 | **Medium** | `share_token` exposed in `DocumentShareResource`. | `DocumentShareResource.php` | 17 |
| AR-6 | **Medium** | Polymorphic type/ID pairs exposed (FQCN leakage) in 8 resources. | Various | all |
| AR-7 | **Medium** | `deleted_at` exposure inconsistent across 30+ resources. | Various | all |

---

### 18. Service Layer

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| SL-1 | **Critical** | `TagService.resolveMorphClass()` always throws — `bulkAttach()` is unreachable. | `TagService.php` | 99-102 |
| SL-2 | **High** | `TaskService`, `NoteService`, `CommentService`, `ActivityService` create() lacks DB::transaction. | Various | all |
| SL-3 | **High** | `DocumentService.create()` lacks DB::transaction — orphan file on DB failure. | `DocumentService.php` | 106-128 |
| SL-4 | **Medium** | `SourceService`, `TagService`, `PipelineStageService` have zero event dispatching. | Various | all |
| SL-5 | **Medium** | `NoteService`/`CommentService` update/delete skip events. | `NoteService.php`, `CommentService.php` | 64-73 |
| SL-6 | **Medium** | `MessageService.delete()` skips events. | `MessageService.php` | 125-128 |
| SL-7 | **Medium** | `ActivityService.delete()` skips events. | `ActivityService.php` | 76-79 |
| SL-8 | **Low** | Inconsistent method signatures — some use `array $filters`, some use `Request $request`. | Various | all |

**Positive:** Strong service layer pattern with consistent CRUD methods. 22 services have proper DB::transaction() coverage. EventDispatcher integration is consistent across most services.

---

### 19. Controllers

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| CT-1 | **Critical** | `DashboardController` (163 lines) has all business logic inline — no service delegation. | `DashboardController.php` | 19-163 |
| CT-2 | **High** | `ProfileController` has inline password change logic. | `ProfileController.php` | 39-66 |
| CT-3 | **High** | `TenantController` performs cascading restore/forceDelete inline. | `TenantController.php` | 114, 131-132 |
| CT-4 | **Medium** | `TicketController.addReply()` updates ticket status inline. | `TicketController.php` | 126-128 |

**Positive:** 40+ CRM controllers are consistently thin (3-6 lines per method). All mutating methods use `Gate::authorize()`. Consistent `$this->api->success()` response pattern.

---

### 20. Test Coverage

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| TC-1 | **Critical** | **Zero tests for queue job failure handling** — `failed()`, `retryUntil()`, `backoff()` behavior unverified. | All 9 job files | all |
| TC-2 | **Critical** | **Zero unit tests** — `tests/Unit/` is empty. Service-layer logic not tested independently of HTTP. | `tests/Unit/` | — |
| TC-3 | **Medium** | No tenant creation API endpoint test. | Missing test file | — |
| TC-4 | **Medium** | Document file upload not tested at HTTP level — only file_path string used. | `DocumentTest.php` | — |
| TC-5 | **Low** | No rate-limit tests on auth endpoints. | `LoginTest.php`, `PortalAuthTest.php` | — |
| TC-6 | **Low** | Workflow action execution (`assign_owner`, `update_field`, `create_task`, `send_notification`) not directly tested. | `WorkflowTest.php` | — |

**Positive:** 792 tests pass. `CrossTenantSecurityTest.php` is excellent. Strong CRUD + permission testing across most CRM resources. Consistent use of `RefreshDatabase`.

---

### 21. N+1 Query Risks

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| N1-1 | **Critical** | `SubscriptionService::find()` lacks `->with('tenant', 'plan')` — N+1 on every show/update of subscriptions. | `SubscriptionService.php` | 81-87 |
| N1-2 | **High** | `PlanService::find()` lacks `->with('features')` — N+1 on every show/update of plans. | `PlanService.php` | 57-63 |
| N1-3 | **Low** | Post-creation lazy loads in `LeadService`/`ConversationService` (minor). | Various | various |

**Positive:** CRM services consistently use eager loading in `query()` method. All CRM resources properly use `whenLoaded()` for relationships.

---

### 22. Transaction Boundaries

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| TB-1 | **High** | 8 services dispatch events before DB delete — phantom timeline entries if delete fails. | (see ED-2) | various |
| TB-2 | **High** | 4 services' `create()` lacks DB::transaction (Task, Note, Comment, Activity). | (see SL-2) | various |
| TB-3 | **Medium** | `BillingAutomationJob.retryFailedPayments()` is a no-op — sets status to already-set value, misleading log. | `BillingAutomationJob.php` | 86-101 |

**Positive:** 22 services have proper DB::transaction() coverage. Transaction callback form used consistently (no nested transaction anti-patterns).

---

### 23. File Upload Security

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| FL-1 | **High** | **Quota bypass via direct `file_path` submission** — clients can create Document records without file upload, bypassing MIME/size/quota checks. | `DocumentService.php`, `StoreDocumentRequest.php` | 106-128, 37-43 |
| FL-2 | **Medium** | `validateMime()` silently accepts null MIME — bypasses validation entirely. | `DocumentStorageService.php` | 90-97 |
| FL-3 | **Medium** | SVG in allowed MIMEs — no sanitization, stored XSS risk if SVGs rendered in browser. | `DocumentStorageService.php` | 25 |
| FL-4 | **Medium** | Tenant ID interpolated into storage path without sanitization — path traversal risk. | `DocumentStorageService.php` | 48 |
| FL-5 | **Low** | Unsanitized `getClientOriginalName()` stored and returned — XSS in filename rendering. | `DocumentStorageService.php` | 54 |

---

### 24. Document Delivery Security

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| DD-1 | **High** | **Soft-delete does not clean up version files** — orphaned files on disk, quota accounting drift. | `DocumentService.php` | 143-148 |
| DD-2 | **Medium** | Public share tokens have no tenant isolation check when tenancy not initialized. | `DocumentShareService.php` | 82-85 |
| DD-3 | **Low** | Signed URLs require authenticated session — cannot be shared externally (may be intentional). | `DocumentController.php` | 139-142 |

**Positive:** Signed URLs use 30-min expiry with `URL::temporarySignedRoute()`. `file_path` NOT exposed in resources. Downloads audited via timeline events.

---

### 25. Storage Quotas

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| SQ-1 | **High** | Soft-delete does NOT delete version files or decrement version storage from quota. | `DocumentService.php` | 143-148 |
| SQ-2 | **Medium** | `delete()` in `DocumentStorageService` silently skips quota decrement when `tenant()` is null (queue/console context). | `DocumentStorageService.php` | 61-74 |
| SQ-3 | **Low** | Quota error messages leak remaining MB to client. | `DocumentStorageService.php` | 121 |

**Positive:** Quota checked before upload (`enforceStorageQuota()` at line 40, before store at line 48). Overage support available via FeatureGateService.

---

### 26. Queue Reliability

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| QR-1 | **Critical** | `ExecuteWorkflowJob` missing `$wasInitialized` — unconditionally initializes/ends tenancy. | `ExecuteWorkflowJob.php` | 34, 47-49 |
| QR-2 | **Low** | Central jobs don't log `tenant_id` in `failed()` for tenant-specific jobs. | `TenantExportJob.php`, `SyncStripeCustomerJob.php` | 63-69, 51-57 |

**Positive:** All 9 jobs have `$tries=0`, `$maxExceptions=3`, `$timeout=60`, `retryUntil()`, `backoff()`, `failed()` with logging and tenant cleanup.

---

### 27. Monitoring Infrastructure

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| MI-1 | **High** | **No real-time alerting** — all monitors are daily, log-only. Production incidents undetected for up to 24h. | `routes/console.php`, `config/logging.php` | 13-21, 76-83 |
| MI-2 | **Medium** | `MonitorJobFailures` triggers at ANY failure — no differentiation between transient and critical. | `MonitorJobFailures.php` | 28-33 |
| MI-3 | **Medium** | All monitoring scheduled daily — insufficient for queue health (degrades in minutes). | `routes/console.php` | 19-21 |
| MI-4 | **Low** | No `->withoutOverlapping()` on scheduled commands — concurrent execution risk in clusters. | `routes/console.php` | 13-21 |
| MI-5 | **Low** | Sentry performance tracing disabled (`traces_sample_rate = null`). | `config/sentry.php` | 33 |

**Positive:** MetricsService provides centralized metrics for jobs, workflows, queue, and storage. Sentry error tracking configured at 100% sample rate.

---

### 28. Cross-Tenant Leakage

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| XL-1 | **Critical** | PortalAuthController login has no tenant scoping — can authenticate users across tenants. | (see MT-1) | 22 |
| XL-2 | **Medium** | `InitializeTenancy` accepts tenant from request headers/body — attacker-controlled context switch. | `InitializeTenancy.php` | 60-78 |
| XL-3 | **Low** | `CheckTenantUsage` resolves tenant from route/input, not tenancy context — mismatch risk. | `CheckTenantUsage.php` | 19-22 |

**Positive:** Cache isolation via `CacheTenancyBootstrapper`. All queue jobs properly initialize tenant context. WorkflowService cross-tenant runtime check. Portal resources scoped via `PortalPersonLink`.

---

### 29. Workflow Security

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| WS-1 | **High** | Infinite loop/cascade via Task creation in workflow. | (see WF-1) | — |
| WS-2 | **High** | Event suppression via `withoutEvents()` — audit/event gaps for workflow-driven changes. | (see WF-2) | — |
| WS-3 | **High** | Unlimited retries/DOS on workflow/timeline queues. | (see WF-3) | — |

---

### 30. Event Duplication

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| ED-1 | **Critical** | Zero idempotency — timeline entries can be duplicated on job retry. | (see TL-1) | — |
| ED-2 | **High** | `TriggerWorkflowJob` has same duplication risk for `WorkflowLog` entries. | `TriggerWorkflowJob.php` | 37-44 |

---

### 31. API Versioning

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| AV-1 | **Medium** | No documented v2 upgrade strategy for controller sharing. | All route files | — |
| AV-2 | **Low** | `crm-v1.php` / `portal-v1.php` filenames have redundant "v1". | `routes/tenant/` | — |

**Positive:** URL-prefix versioning is well-structured (`/api/central/v1/`, `/api/tenant/v1/`). All routes properly versioned. No routes bypass versioning.

---

### 32. Client Portal Security

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| CP-1 | **Critical** | Sanctum token `expiration => null` — tokens never expire. Stolen tokens work indefinitely. | `config/sanctum.php` | 60 |
| CP-2 | **High** | Portal routes lack `subscription` and `feature:portal.enabled` middleware — accessible even with expired subscription. | `routes/tenant/portal-v1.php` | 47-66 |
| CP-3 | **Medium** | Invitation flow incomplete — `invited_at`/`registered_at` fields exist but no API endpoint. | `PortalUser.php`, `PortalAuthController.php` | 34-35 |
| CP-4 | **Medium** | `PortalUserPolicy::view()`/`update()` don't verify tenant ownership of target user. | `PortalUserPolicy.php` | 27-40 |

**Positive:** Separate auth guard (`portal-api`). Proper password reset with separate broker (`portal_users`). Tokens revoked on password reset. `PortalPersonLink` scoping provides row-level access. Cross-tenant portal tests exist and pass.

---

### 33. Production Readiness

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| PR-1 | **High** | No real-time alerting — daily monitoring only. | (see MI-1) | — |
| PR-2 | **High** | Scout collection engine default — memory exhaustion on any search query. | (see SI-3) | — |
| PR-3 | **High** | Sanctum tokens never expire. | (see CP-1) | — |
| PR-4 | **Medium** | Queue driver is `database` in dev — performance ceiling, no Horizon/Horizon config. | `config/queue.php` | — |
| PR-5 | **Medium** | Unbounded timeline table growth — no retention policy. | (see TL-4) | — |
| PR-6 | **Low** | Telescope at `/telescope` with no IP restriction. | `config/telescope.php` | 45 |
| PR-7 | **Low** | Sentry performance tracing disabled. | `config/sentry.php` | 33 |

---

### 34. Future Module Readiness

| ID | Severity | Finding | File(s) | Lines |
|----|----------|---------|---------|-------|
| FM-1 | **High** | `MorphableEntityResolver::ALLOWED_TYPES` is a `const` — new modules cannot register types without modifying core file. | `MorphableEntityResolver.php` | 21-34 |
| FM-2 | **High** | Permission config (`config/tenant-permissions.php`) is manual per-module — no auto-discovery. | `config/tenant-permissions.php` | all |
| FM-3 | **Medium** | Hardcoded entity type lists in 3 requests — new types silently excluded. | (see MR-1) | all |
| FM-4 | **Medium** | No module registration system or service provider interface for new modules. | — | — |
| FM-5 | **Low** | `FeatureDefinition` model is intentionally global (no tenant_id) but undocumented. | `FeatureDefinition.php` | — |

---

## Critical Findings Table (15)

| # | ID | Severity | Finding | File(s) |
|---|----|----------|---------|---------|
| C01 | MT-1 | **Critical** | PortalAuthController login — cross-tenant authentication bypass via unscoped PortalUser query | `PortalAuthController.php:22` |
| C02 | QJ-1 | **Critical** | ExecuteWorkflowJob missing `$wasInitialized` — cross-tenant context leak in queue | `ExecuteWorkflowJob.php:34,47-49` |
| C03 | TL-1 | **Critical** | Zero idempotency — duplicate timeline entries on job retry | `RecordTimelineEntryJob.php:43-53`, migration |
| C04 | SI-1 | **Critical** | `CentralUser::search()` called but model has NO Searchable trait — runtime crash | `UserService.php:32`, `CentralUser.php` |
| C05 | SI-2 | **Critical** | `Permission::search()` called but model has NO Searchable trait — runtime crash | `TenantProvisioningService.php:89`, `Permission.php` |
| C06 | OM-1 | **Critical** | No ownership checks in ANY of 36 CRM policies | All 36 CRM policies |
| C07 | PM-1 | **Critical** | 12 permission modules missing from `config/tenant-permissions.php` | `config/tenant-permissions.php` |
| C08 | SL-1 | **Critical** | `TagService.resolveMorphClass()` always throws — bulkAttach is unreachable dead code | `TagService.php:99-102` |
| C09 | AR-1 | **Critical** | `file_path` exposed in MessageAttachmentResource — internal storage path leakage | `MessageAttachmentResource.php:18` |
| C10 | AR-2 | **Critical** | API `key` exposed in ApiKeyResource — raw credentials returned | `ApiKeyResource.php:19` |
| C11 | N1-1 | **Critical** | `SubscriptionService::find()` lacks eager loading — N+1 on every show/update | `SubscriptionService.php:81-87`, `SubscriptionResource.php:25-37` |
| C12 | SR-1 | **Critical** | Unsanitized sort columns passed to `orderBy()` in 36+ services | 17 Central + 19 CRM services |
| C13 | TC-1 | **Critical** | Zero tests for queue job failure handling | All 9 job files |
| C14 | TC-2 | **Critical** | Zero unit tests | `tests/Unit/` (empty) |
| C15 | CP-1 | **Critical** | Sanctum tokens never expire (`expiration => null`) | `config/sanctum.php:60` |

---

## High Findings Table (27)

| # | ID | Severity | Finding | File(s) |
|---|----|----------|---------|---------|
| H01 | MT-2 | **High** | InitializeTenancy silently continues if tenant resolution fails | `InitializeTenancy.php:40-48` |
| H02 | MT-3 | **High** | TenantScope is no-op when tenancy not initialized — unreachable unscoped queries | `TenantScope.php:26-28` |
| H03 | GS-1 | **High** | No runtime protection against TenantScope bypass | `TenantScope.php:20` |
| H04 | ED-1 | **High** | recordGeneric() does NOT trigger workflows — silent automation gap | `EventDispatcher.php:28-38` |
| H05 | WF-1 | **High** | Infinite loop/cascade risk via executeCreateTask triggering additional workflows | `WorkflowService.php:64,130-143` |
| H06 | WF-2 | **High** | withoutEvents() suppresses all Eloquent events — no audit for workflow-driven changes | `WorkflowService.php:64` |
| H07 | WF-3 | **High** | Unlimited retries/DOS on workflow/timeline queues | `TriggerWorkflowJob`, `ExecuteWorkflowJob`, `RecordTimelineEntryJob` |
| H08 | SI-3 | **High** | Collection engine default — loads ALL records into memory for every search | `config/scout.php:19` |
| H09 | SI-4 | **High** | 6 models index only `id` — search returns zero results | `Role.php`, `SettingDefinition.php`, etc. |
| H10 | SI-5 | **High** | TaskCommentService raw LIKE with leading wildcard — full table scan | `TaskCommentService.php:23` |
| H11 | MR-2 | **High** | MorphableEntityResolver::ALLOWED_TYPES is a const — not extensible | `MorphableEntityResolver.php:21-34` |
| H12 | PL-2 | **High** | No UserPolicy exists for tenant User model | `User.php` |
| H13 | PL-5 | **High** | subscriptions.forceDelete naming mismatch (dot notation vs camelCase) | `SubscriptionPolicy.php:71` |
| H14 | FR-1 | **High** | 24 endpoints use inline $request->validate() instead of FormRequest classes | Various controllers |
| H15 | FR-2 | **High** | 12 tenant form requests use bare `exists:` without tenant scope | Various request files |
| H16 | VR-1 | **High** | Polymorphic type/ID pairs not validated together | `StoreNoteRequest.php`, etc. |
| H17 | PG-2 | **High** | ModuleController has no per_page cap — memory exhaustion DoS | `ModuleController.php:27` |
| H18 | AR-3 | **High** | SubscriptionResource missing whenLoaded — N+1 queries | `SubscriptionResource.php:25-37` |
| H19 | AR-4 | **High** | PlanResource missing whenLoaded on features — N+1 queries | `PlanResource.php:26` |
| H20 | SL-2 | **High** | 4 services' create() lacks DB::transaction (Task, Note, Comment, Activity) | Various service files |
| H21 | SL-3 | **High** | DocumentService.create() lacks transaction — orphan file on DB failure | `DocumentService.php:106-128` |
| H22 | CT-2 | **High** | ProfileController has inline password change logic | `ProfileController.php:39-66` |
| H23 | CT-3 | **High** | TenantController performs cascading inline restore/forceDelete | `TenantController.php:114,131-132` |
| H24 | FL-1 | **High** | Quota bypass via direct file_path submission | `DocumentService.php`, `StoreDocumentRequest.php` |
| H25 | DD-1 | **High** | Soft-delete does not clean up version files — storage leak | `DocumentService.php:143-148` |
| H26 | MI-1 | **High** | No real-time alerting — daily-only monitoring | `routes/console.php:13-21` |
| H27 | CP-2 | **High** | Portal routes lack subscription/feature middleware | `routes/tenant/portal-v1.php:47-66` |

---

## Medium Findings Table (40)

| # | ID | Severity | Finding | File(s) |
|---|----|----------|---------|---------|
| M01 | MT-4 | **Medium** | MetricsService raw DB queries bypass TenantScope | `MetricsService.php:69-83` |
| M02 | MT-5 | **Medium** | BelongsToTenant creating() only works when tenancy initialized | `BelongsToTenant.php:35-38` |
| M03 | GS-2 | **Medium** | TenantController forceDelete two-phase logic fragile | `TenantController.php:131-135` |
| M04 | QJ-2 | **Medium** | ExecuteWorkflowJob initializes tenancy before validation check | `ExecuteWorkflowJob.php:34,41-43` |
| M05 | QJ-3 | **Medium** | Central jobs query non-tenant-scoped models — fragile | `Invoice`, `Subscription` models |
| M06 | ED-2 | **Medium** | 8 services dispatch events before DB delete — phantom entries | 8 service files |
| M07 | ED-3 | **Medium** | CommentService/NoteService create events outside transaction | `CommentService.php`, `NoteService.php` |
| M08 | WF-4 | **Medium** | No validation on per-action workflow parameters | `StoreWorkflowRequest.php` |
| M09 | WF-5 | **Medium** | No workflow execution idempotency | `WorkflowService.php:30-43` |
| M10 | WF-6 | **Medium** | Notifications inside withoutEvents + transaction — ghost send | `WorkflowService.php:150` |
| M11 | TL-2 | **Medium** | Scout whereIn with large ID sets — SQL query size issues | `TimelineService.php:33-36` |
| M12 | TL-3 | **Medium** | Timeline + workflow dispatch not atomic — partial failures | `EventDispatcher.php:15-26` |
| M13 | TL-4 | **Medium** | Unbounded timeline table growth — no retention policy | Migration |
| M14 | SI-6 | **Medium** | ActivityLogController raw LIKE with leading wildcard | `ActivityLogController.php:26` |
| M15 | SI-7 | **Medium** | Scout soft_delete disabled — trashed records in search results | `config/scout.php:87` |
| M16 | MR-3 | **Medium** | 7 CRM models missing from MorphableEntityResolver | `MorphableEntityResolver.php:21-34` |
| M17 | MR-4 | **Medium** | StatusType/Workflow requests allow arbitrary entity_type | `StoreStatusTypeRequest.php`, `StoreWorkflowRequest.php` |
| M18 | MR-5 | **Medium** | MessageService/ConversationService duplicate resolver type maps | `MessageService.php`, `ConversationService.php` |
| M19 | OM-2 | **Medium** | team_id exists on 11 models but no Team model | Various CRM models |
| M20 | OM-3 | **Medium** | No auto-setting for created_by/updated_by | All CRM models |
| M21 | PL-3 | **Medium** | hasPermissionTo() bypasses Gate::before superadmin | All 36 CRM policies |
| M22 | PL-4 | **Medium** | Zero Form Requests override authorize() | All 100+ Form Requests |
| M23 | PL-6 | **Medium** | tenant.list/tenant.update naming mismatch | `TenantSettingPolicy.php:17,22` |
| M24 | VR-2 | **Medium** | No usage of Rule::enum() — uses Rule::in() | Various requests |
| M25 | VR-3 | **Medium** | Conversation channel validated as string, not against enum | `StoreConversationRequest.php:14` |
| M26 | SR-2 | **Medium** | Only 1 service (SubscriptionService) implements sort whitelisting | `SubscriptionService.php:32-46` |
| M27 | PG-1 | **Medium** | No cursor pagination anywhere — offset-only | All controllers/services |
| M28 | AR-5 | **Medium** | share_token exposed in DocumentShareResource | `DocumentShareResource.php:17` |
| M29 | AR-6 | **Medium** | Polymorphic type/ID FQCN leakage in 8 resources | Various resources |
| M30 | AR-7 | **Medium** | deleted_at exposure inconsistent across 30+ resources | Various resources |
| M31 | FL-2 | **Medium** | validateMime silently accepts null — bypass | `DocumentStorageService.php:90-97` |
| M32 | FL-3 | **Medium** | SVG allowed without sanitization — XSS vector | `DocumentStorageService.php:25` |
| M33 | FL-4 | **Medium** | Tenant ID in storage path without sanitization | `DocumentStorageService.php:48` |
| M34 | DD-2 | **Medium** | Public share tokens no tenant check when tenancy not init | `DocumentShareService.php:82-85` |
| M35 | SQ-2 | **Medium** | Quota decrement silently skipped when tenant() is null | `DocumentStorageService.php:61-74` |
| M36 | MI-2 | **Medium** | MonitorJobFailures triggers at ANY failure — alert fatigue | `MonitorJobFailures.php:28-33` |
| M37 | MI-3 | **Medium** | All monitoring daily — insufficient for queue health | `routes/console.php:19-21` |
| M38 | XL-2 | **Medium** | InitializeTenancy accepts tenant from headers/body | `InitializeTenancy.php:60-78` |
| M39 | CP-4 | **Medium** | PortalUserPolicy doesn't verify tenant ownership | `PortalUserPolicy.php:27-40` |
| M40 | TB-3 | **Medium** | BillingAutomationJob.retryFailedPayments() is a no-op | `BillingAutomationJob.php:86-101` |

---

## Low Findings Table (41)

| # | ID | Severity | Finding | File(s) |
|---|----|----------|---------|---------|
| L01 | MT-6 | **Low** | NotifiesActions User::find() fragile | `NotifiesActions.php:13` |
| L02 | GS-3 | **Low** | PortalUserService.restore() dead code | `PortalUserService.php:67-75` |
| L03 | GS-4 | **Low** | 21 resources expose deleted_at inconsistently | Various resources |
| L04 | GS-5 | **Low** | CRM SoftDelete models lack resolveRouteBindingQuery | All CRM SoftDelete models |
| L05 | QJ-4 | **Low** | tries=0 + maxExceptions=3 interaction confusing | All 9 jobs |
| L06 | QJ-5 | **Low** | Redundant afterCommit() in job constructors | 3 job files |
| L07 | ED-4 | **Low** | Redundant afterCommit() calls | All dispatchers |
| L08 | WF-7 | **Low** | Loose comparison (!=) in condition evaluation | `WorkflowService.php:84-101` |
| L09 | WF-8 | **Low** | Workflow execution logging gaps | `WorkflowService.php:51-82` |
| L10 | TL-5 | **Low** | Missing index on caused_by column | Migration |
| L11 | TL-6 | **Low** | No explicit tenant guard in TimelineService | `TimelineService.php:12-14` |
| L12 | SI-3 | **Low** | No model-specific index settings | `config/scout.php:118-206` (commented out) |
| L13 | PL-7 | **Low** | 4 models missing #[UsePolicy] attribute | `Module.php`, etc. |
| L14 | PM-2 | **Low** | 9 central permission modules defined but unused | `config/central-permissions.php:184-202` |
| L15 | FR-3 | **Low** | Mixed array/pipe validation syntax | Various FormRequests |
| L16 | SR-3 | **Low** | Inconsistent default sort columns | Various services |
| L17 | PG-3 | **Low** | Inconsistent default per_page values | Various controllers |
| L18 | PG-4 | **Low** | Duplicate per-page logic (trait vs inline) | `PaginatesRequestTrait` vs inline |
| L19 | N1-3 | **Low** | Post-creation lazy loads in LeadService/ConversationService | Various |
| L20 | FL-5 | **Low** | Unsanitized getClientOriginalName() stored | `DocumentStorageService.php:54` |
| L21 | DD-3 | **Low** | Signed URLs require auth — cannot be shared externally | `DocumentController.php:139-142` |
| L22 | SQ-3 | **Low** | Quota error messages leak remaining MB | `DocumentStorageService.php:121` |
| L23 | QR-2 | **Low** | Central jobs missing tenant_id in failed() logs | `TenantExportJob.php`, `SyncStripeCustomerJob.php` |
| L24 | MI-4 | **Low** | No ->withoutOverlapping() on scheduled commands | `routes/console.php:13-21` |
| L25 | MI-5 | **Low** | Sentry performance tracing disabled | `config/sentry.php:33` |
| L26 | XL-3 | **Low** | CheckTenantUsage resolves from route, not tenancy context | `CheckTenantUsage.php:19-22` |
| L27 | AV-2 | **Low** | crm-v1.php / portal-v1.php filenames have redundant "v1" | `routes/tenant/` |
| L28 | FM-5 | **Low** | FeatureDefinition intentionally global but undocumented | `FeatureDefinition.php` |
| L29 | TC-5 | **Low** | No rate-limit tests on auth endpoints | `LoginTest.php`, `PortalAuthTest.php` |
| L30 | TC-6 | **Low** | Workflow action execution not directly tested | `WorkflowTest.php` |
| L31 | CP-3 | **Low** | Invitation flow incomplete (fields exist, no API) | `PortalUser.php:34-35` |
| L32 | CT-4 | **Low** | TicketController.addReply() updates status inline | `TicketController.php:126-128` |
| L33 | PR-6 | **Low** | Telescope at /telescope with no IP restriction | `config/telescope.php:45` |
| L34 | PR-7 | **Low** | Sentry performance tracing disabled | `config/sentry.php:33` |
| L35 | SL-8 | **Low** | Inconsistent method signatures across services | Various services |
| L36 | TB-3 | **Low** | Redundant whereNull(deleted_at) in DashboardController | `DashboardController.php:32` |
| L37 | TB-4 | **Low** | Redundant whereNull(deleted_at) in MetricsService | `MetricsService.php:70,76,80,99` |
| L38 | QJ-6 | **Low** | Queue name prefixing inconsistency (QueueTenancyBootstrapper vs hardcoded) | Jobs, config/tenancy.php |
| L39 | WF-9 | **Low** | WorkflowDefinitionService no explicit tenant guard | `WorkflowDefinitionService.php:10-14` |
| L40 | CP-6 | **Low** | Password reset URL uses config('app.url') — multi-tenant breakage | `app/Notifications/Portal/Auth/ResetPassword.php:19` |
| L41 | SI-4 | **Low** | PersonService uses inline when() pattern instead of extract-check pattern | `PersonService.php:29` |

---

## Updated Technical Debt List

| Priority | Item | Area | Effort |
|----------|------|------|--------|
| **P0** | Fix PortalAuthController cross-tenant auth bypass | Security | 1h |
| **P0** | Add `$wasInitialized` guard to ExecuteWorkflowJob | Queue | 30m |
| **P0** | Add unique constraint + firstOrCreate to timeline entries | Data Integrity | 1h |
| **P0** | Add Searchable trait to CentralUser and Permission | Search | 1h |
| **P0** | Add ownership checks to all 36 CRM policies | Auth | 1d |
| **P0** | Add missing 12 permission modules to config/tenant-permissions.php | Permissions | 2h |
| **P0** | Remove file_path from MessageAttachmentResource | API Security | 30m |
| **P0** | Remove key from ApiKeyResource (creation-only return) | API Security | 1h |
| **P0** | Add ->with('tenant','plan') to SubscriptionService::find() | Performance | 30m |
| **P0** | Whitelist sort columns in all 36+ services | Security | 1d |
| **P0** | Add queue job failure tests | Testing | 1d |
| **P0** | Add unit test directory with service tests | Testing | 3d |
| **P0** | Set Sanctum token expiration | Security | 30m |
| **P0** | Fix TagService.resolveMorphClass() | Data Integrity | 1h |
| **P1** | Wire Slack alerting into monitor commands | Monitoring | 2h |
| **P1** | Change monitoring frequency: queue-health every 5min, job-failures every 15min | Monitoring | 30m |
| **P1** | Switch Scout driver to `database` or Meilisearch | Search | 1d |
| **P1** | Add 6 missing fields to toSearchableArray() for id-only models | Search | 1h |
| **P1** | Wrap delete() in DB::transaction for 8 services | Data Integrity | 4h |
| **P1** | Wrap Task/Note/Comment/Activity create() in DB::transaction | Data Integrity | 2h |
| **P1** | Add subscription + feature middleware to portal routes | Security | 1h |
| **P1** | Clean up version files on Document soft delete | Storage | 2h |
| **P1** | Fix Document quota bypass via file_path | Storage | 2h |
| **P1** | Replace TaskComment raw LIKE with Scout | Search | 1h |
| **P1** | Add `->withoutOverlapping()` to all scheduled commands | Operations | 30m |
| **P2** | Make MorphableEntityResolver extensible | Architecture | 4h |
| **P2** | Add cursor pagination for high-volume endpoints | Scalability | 1d |
| **P2** | Implement timeline retention policy + pruning command | Data Mgmt | 4h |
| **P2** | Standardize deleted_at exposure across all resources | API | 2h |
| **P2** | Replace 24 inline validations with FormRequest classes | Architecture | 1d |
| **P2** | Add tenant scoping to 12 bare exists: rules | Security | 4h |
| **P2** | Add polymorphic type/ID cross-validation | Validation | 4h |
| **P2** | Add 7 missing CRM models to MorphableEntityResolver | Architecture | 1h |
| **P2** | Fix Policy hasPermissionTo() -> can() for Gate::before bypass | Auth | 2h |
| **P2** | Add UserPolicy for tenant User model | Auth | 2h |
| **P2** | Add created_by/updated_by auto-set trait | Architecture | 2h |
| **P2** | Replace Rule::in() with Rule::enum() | Validation | 2h |
| **P2** | Remove `withoutEvents()` from WorkflowService (replace with targeted suppression) | Workflow | 1d |
| **P2** | Add workflow execution depth counter (loop prevention) | Workflow | 4h |
| **P2** | Implement workflow execution idempotency | Workflow | 4h |
| **P2** | Add TenantScope runtime protection (re-apply guard) | Security | 2h |
| **P3** | Document v2 upgrade strategy | Documentation | 1h |
| **P3** | Remove redundant crm-v1.php filename version | Architecture | 30m |
| **P3** | Enable Sentry performance tracing (0.1 sample rate) | Monitoring | 30m |
| **P3** | Add IP restriction to Telescope | Security | 30m |
| **P3** | Consolidate TenantController forceDelete logic | Architecture | 1h |
| **P3** | Clean up BillingAutomationJob.retryFailedPayments() | Billing | 1h |
| **P3** | Standardize default per_page across all endpoints | API | 2h |
| **P3** | Remove redundant afterCommit() calls | Queue | 30m |
| **P3** | Add queue name prefixing documentation | Queue | 30m |
| **P3** | Remove redundant whereNull(deleted_at) calls | Cleanup | 1h |
| **P3** | Add PortalUser register/invitation endpoint | Portal | 1d |

---

## Architecture Score: 7.5/10

**Strengths:**
- Consistent Service Layer pattern with thin controllers
- Well-structured multi-tenant isolation (TenantScope + BelongsToTenant on 43 models)
- Clean event dispatching via EventDispatcher (17 services integrated)
- Proper separation of Central vs Tenant concerns at route/model/service level
- 22 services have proper DB::transaction() coverage
- Consistent API response format via ApiResponseService

**Weaknesses:**
- Inconsistent transaction boundaries (8 services missing transaction on delete, 4 on create)
- Event coverage gaps (5 services have zero event dispatching)
- TagService has unreachable dead code (bulkAttach)
- Duplicate type maps in services (MessageService, ConversationService)
- No centralized search abstraction — `->keys()` + `whereIn()` repeated in 37 files
- Mixed parameter styles (array vs Request, no return types in many services)
- No `declare(strict_types=1)` in any CRM service (Central services all have it)

---

## Security Score: 5.5/10

**Strengths:**
- Multi-tenant isolation fundamentally sound via global scopes
- Queue jobs properly initialize/end tenancy
- Signed URLs for document delivery (30-min expiry, HMAC validation)
- Separate auth guards (central-api, tenant-api, portal-api)
- Cross-tenant runtime checks in WorkflowService
- Password reset with token revocation
- Sentry error tracking configured at 100% sample

**Critical Gaps:**
- PortalAuthController cross-tenant auth bypass (C01)
- No ownership checks in any CRM policy (C06)
- API keys and file_path exposed in responses (C09, C10)
- Sanctum tokens never expire (C15)
- Unsanitized sort columns — SQL injection via orderBy (C12)
- No idempotency — timeline/event duplication (C03)
- ExecuteWorkflowJob cross-tenant context leak (C02)
- SVG+XSS vector in allowed MIMEs (M32)
- Null MIME bypass (M31)
- Soft-delete storage leak (H25)
- Quota bypass via direct file_path (H24)
- Portal routes lack subscription/feature middleware (H27)

---

## Scalability Score: 5.0/10

**Strengths:**
- Cursor pagination-ready architecture (service layer can support it)
- Eager loading consistently used in CRM services
- Cache isolation via Tenancy bootstrapper with tenant tags

**Critical Gaps:**
- No cursor pagination anywhere — all offset-based (M27)
- Scout collection engine default — loads ALL records into memory (H08)
- Unsanitized sort columns — SQL injection via orderBy (C12)
- ModuleController no per_page cap — memory exhaustion DoS (H17)
- Unbounded timeline table growth — no retention (M13)
- No unit tests — can't verify performance-critical code in isolation (C14)
- Scout whereIn with large ID sets (M11)
- Queue driver is `database` (not Horizon/Redis)
- No per-tenant storage monitoring

---

## SaaS Readiness Score: 6.0/10

**Strengths:**
- Multi-tenant architecture fundamentally sound (stancl/tenancy)
- Tenant provisioning pipeline exists (TenantProvisioningService)
- Plan/Feature/Subscription management infrastructure
- Stripe billing integration (Cashier, webhooks)
- Usage tracking and feature gating (FeatureGateService)

**Gaps:**
- Overage billing not wired to document storage (FeatureGateService supports it but no hook)
- Invitation flow incomplete (no accept-invite endpoint)
- No tenant-level SLA or rate limiting
- Portal routes not gated on subscription check
- No self-service tenant deletion
- No usage dashboard or tenant-facing metrics
- No audit trail for subscription plan changes
- No custom domain management beyond initial setup

---

## Production Readiness Score: 5.0/10

**Strengths:**
- 792 tests pass with 0 failures
- Sentry error tracking configured
- Queue reliability features on all jobs (retryUntil, backoff, maxExceptions, timeout, failed())
- Document storage quota enforcement
- Operational monitoring commands exist

**Critical Gaps:**
- No real-time alerting — daily-only monitoring (H26)
- Scout collection engine in production — memory exhaustion on any search (H08)
- Sanctum tokens never expire (C15)
- 2 runtime crashes (CentralUser, Permission missing Searchable trait) (C04, C05)
- 12 permission modules missing — permission checks silently fail (C07)
- No cursor pagination — performance degradation on high-volume tables (M27)
- No unit tests — regression risk (C14)
- Telescope accessible without IP restriction (L33)
- Sentry performance tracing disabled (L34)
- No ->withoutOverlapping() on scheduled commands (M05)
- Queue driver is `database` — no Horizon config

---

## Extensibility Score: 6.5/10

**Strengths:**
- Well-defined service layer — new business logic goes in services
- MorphableEntityResolver provides polymorphic type resolution
- FeatureDefinition system for gating new features
- Permission config per module
- Named routes with API versioning prefix

**Gaps:**
- MorphableEntityResolver not extensible — const array, no register() method (H11)
- 7 CRM models not registered in resolver (M16)
- Hardcoded entity type lists in 3 requests exclude new types (MR-1)
- No module registration system or service provider interface
- Permission config is fully manual — no auto-discovery
- No documented extension points for new modules
- StatusType/Workflow requests accept arbitrary entity_type strings (M17)

---

## Future Module Readiness Assessment

| Module | Readiness | Gaps |
|--------|-----------|-------|
| **Followka Sales OS** | 🟡 Partial | Would need: pipeline+lead+person infrastructure exists; missing: deal scoring, forecasting, sales sequences |
| **Notifications Center** | 🟡 Partial | Notification templates exist (email, SMS); missing: in-app notifications, notification preferences, delivery tracking |
| **Mobile App** | 🟡 Partial | API-first architecture works; missing: OAuth2 flow, push notification endpoints, offline sync support |
| **WhatsApp Integration** | 🟢 Good | Full WhatsApp business API integration, webhooks, message templates, account management |
| **AI Assistant** | 🔴 Sparse | No AI infrastructure; would need: NLP pipeline, embedding storage, prompt templates, context management |
| **Solar Module** | 🔴 Sparse | No solar-specific models; would need: panel tracking, production monitoring, inverter management |
| **Agency Module** | 🔴 Sparse | No agency-specific models; would need: client management, portfolio tracking, commission calculation |
| **Real Estate Module** | 🔴 Sparse | No real estate models; would need: property listings, MLS integration, showing scheduling, document management |

**Key blockers for ALL new modules:**
1. `MorphableEntityResolver` not extensible — new module types cannot register
2. Permission config is fully manual — no auto-discovery for module permissions
3. No module registration system or hooks system
4. CustomFieldDefinition hardcoded type list must be modified for each new module
5. Hardcoded entity type lists in 3 requests exclude new modules

---

## Top 10 Recommendations

### Priority Order (P0 = Must fix before production)

1. **P0 — Fix PortalAuthController cross-tenant auth bypass** (C01, 1h)
   - Add explicit `where('tenant_id', tenant()->id)` to PortalUser login query
   - Return 403 if tenancy not initialized

2. **P0 — Add ownership checks to all 36 CRM policies** (C06, 1d)
   - Add `$model->owner_id === $user->id` check in `update()` and `delete()` gates
   - Follow pattern: `$user->hasPermissionTo('module.action') && $record->owner_id === $user->id`

3. **P0 — Add missing 12 permission modules to tenant config** (C07, 2h)
   - Add leads, people, organizations, notes, activities, comments, addresses, pipelines, pipeline-stages, portal-users, organization-people, timeline to `config/tenant-permissions.php`
   - Re-seed permissions for all existing tenants

4. **P0 — Fix ExecuteWorkflowJob tenant context** (C02, 30m)
   - Add `$wasInitialized` guard pattern (same as TriggerWorkflowJob)
   - Context: `tenancy()->initialize()` only if not already initialized

5. **P0 — Add timeline entry idempotency** (C03, 1h)
   - Add unique constraint: `(tenant_id, entity_type, entity_id, event_type, occurred_at)`
   - Change `create()` to `firstOrCreate()` in RecordTimelineEntryJob

6. **P0 — Fix 2 runtime crashes: CentralUser + Permission Searchable** (C04, C05, 1h)
   - Add `use Laravel\Scout\Searchable` and the trait to both models
   - Replace Permission::search() with direct query in TenantProvisioningService

7. **P0 — Whitelist sort columns in all 36+ services** (C12, 1d)
   - Follow SubscriptionService pattern with ALLOWED_SORT_COLUMNS constant
   - Add ALLOWED_DIRECTIONS = ['asc', 'desc']

8. **P0 — Secure API resources** (C09, C10, C15, 2h)
   - Remove `file_path` from MessageAttachmentResource
   - Remove `key` from ApiKeyResource (show on creation only)
   - Set `expiration` in config/sanctum.php (e.g., 24h or 7d)

9. **P0 — Add queue job failure + unit tests** (C13, C14, 3d)
   - Write tests for each job's `failed()`, `retryUntil()`, `backoff()` behavior
   - Add service-layer unit tests (at minimum: DocumentStorageService, WorkflowService, EventDispatcher)

10. **P1 — Wire real-time alerting** (H26, 2h)
    - Configure Slack logging channel for `critical` level
    - Add `->everyFiveMinutes()` for `monitor:queue-health`
    - Add `->everyFifteenMinutes()` for `monitor:job-failures`
    - Add `->withoutOverlapping()` to all scheduled commands

---

## Final Verdict

# NOT APPROVED

**Rationale:** The platform has **15 Critical findings** that represent active security vulnerabilities, runtime crashes, data integrity risks, and fundamental production readiness gaps. While the architectural foundation is sound (service layer, multi-tenant isolation, event dispatching), the current state would be unsafe to deploy in production.

### Conditions for Approval

The platform will be considered **APPROVED WITH CONDITIONS** once the following are addressed:

**Gate 1 — Security (must fix all P0):**
- [ ] PortalAuthController cross-tenant auth bypass fixed
- [ ] Ownership checks added to all 36 CRM policies
- [ ] ExecuteWorkflowJob $wasInitialized guard added
- [ ] API keys and file_path removed from resource responses
- [ ] Sanctum token expiration configured
- [ ] 12 missing permission modules added to config and seeded

**Gate 2 — Runtime Integrity (must fix all P0):**
- [ ] CentralUser + Permission Searchable traits added
- [ ] Timeline entry idempotency implemented
- [ ] TagService.resolveMorphClass() fixed
- [ ] Sort columns whitelisted in all services
- [ ] Subscription N+1 fixed with eager loading

**Gate 3 — Production Operations:**
- [ ] Scout driver changed to `database` or Meilisearch (collection engine removed)
- [ ] Real-time alerting wired (queue-health every 5min minimum)
- [ ] Monitor commands have ->withoutOverlapping()
- [ ] Queue driver documented/configured for production
- [ ] Timeline retention policy implemented

**Gate 4 — Testing:**
- [ ] Queue job failure tests written (minimum 1 per job type)
- [ ] Service-layer unit tests started (DocumentStorageService, WorkflowService, EventDispatcher)
- [ ] Document file upload tested at HTTP level

---

*Audit completed: 2026-06-20 | Total findings: 15 Critical, 27 High, 40 Medium, 41 Low | 792 tests passing*
