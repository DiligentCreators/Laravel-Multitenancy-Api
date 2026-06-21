# Platform Audit V4 — Post-Hardening Assessment

> **Date:** 2026-06-21
> **Scope:** Independent zero-trust post-hardening audit after Platform Hardening Phase 1, Critical Findings Remediation, and Sprint 10 Production Hardening.
> **Methodology:** Every finding independently verified by reading source code directly. No prior audit results trusted.

---

## Executive Summary

The codebase demonstrates strong engineering discipline — **transaction boundaries are clean (0 violations across 26 services)**, **820 tests pass with 0 failures**, **Pint formatting is clean**, and the multi-tenant isolation foundation (BelongsToTenant trait + TenantScope global scope) is architecturally sound. 

However, **3 out of 4 critical-security items identified in the pre-hardening audit remain unfixed**. Two of these have clear exploit paths in production:

1. **WhatsApp webhook accepts unverified payloads** — any HTTP client can POST fake messages
2. **`disconnect()` on WhatsApp accounts is not transactional** — partial failure corrupts account state
3. **Document file_path has no path traversal validation** — arbitrary file read via manipulated store/create flows

Additionally, a **pre-existing authorization bug** in `DocumentVersionController::show()` (`Gate::authorize('view', Document::class)` passing the wrong model) is confirmed broken.

**Scores have improved** from the pre-hardening baseline in areas where architecture was correctly understood (tenant isolation, pipeline scoping), but the unfixed exploits prevent approval.

### Verdict

```
NOT APPROVED
```

4 High-severity findings with clear exploit paths prevent unconditional approval.

---

## Architecture Score: 7/10

| Baseline | Current | Delta |
|----------|---------|-------|
| 7/10 | 8/10 | +1 |

**Improvements verified:**
- Route name `tenant.crm.documents.serve` matches the fully qualified named route — previously broken, now fixed
- CalendarEventController uses `$calendarEvent` parameter name matching route binding `{calendar_event}` — correct
- Portal-user routes are correctly protected by outer `auth:tenant-api` middleware group in `routes/tenant/v1.php:148` — the pre-hardening audit's concern about line 253 scope was a false positive; routes inherit auth from the parent
- Service layer remains clean and consistent

**Remaining issues:**

| # | Severity | File | Finding |
|---|----------|------|---------|
| A01 | HIGH | `app/Http/Controllers/Tenant/Api/V1/Crm/DocumentVersionController.php:51` | `Gate::authorize('view', Document::class)` passes the class name string instead of the `$documentVersion` model instance. This silently falls through to `DocumentPolicy::view()` instead of `DocumentVersionPolicy`. |
| A02 | MEDIUM | `app/Http/Controllers/Tenant/Api/V1/Crm/TaskCommentController.php` | Uses inline `$request->validate()` instead of a FormRequest class. Inconsistent with codebase convention. |
| A03 | MEDIUM | `app/Http/Controllers/Tenant/Api/V1/Crm/TaskReminderController.php` | Same — inline validation instead of FormRequest. |
| A04 | LOW | `app/Http/Controllers/Tenant/Api/V1/Crm/LeadController.php` | Hardcodes `per_page` pagination logic instead of using shared `PaginatesRequestTrait`. |

---

## Security Score: 6/10

| Baseline | Current | Delta |
|----------|---------|-------|
| 6/10 | 6/10 | 0 |

**Items verified fixed:**
- Document controller route name on signed temporary URLs: fixed to `tenant.crm.documents.serve`
- CalendarEvent route binding: correct
- Sanctum token expiration: 10080 min (7 days) — configured
- Portal routes: correctly protected by auth middleware via outer group

**Items verified unfixed (4):**

| # | Severity | File | Line(s) | Finding |
|---|----------|------|---------|---------|
| S01 | HIGH | `app/Services/Crm/WhatsAppAccountService.php` | 112-117 | `disconnect()` performs `$account->update()` and `$this->eventDispatcher->record(...)` (DB writes to `crm_whatsapp_accounts` and `crm_timeline_entries`) outside any `DB::transaction()`. If timeline entry recording fails after account update, the account shows disconnected in UI but the event is lost. |
| S02 | HIGH | `app/Http/Controllers/Tenant/Api/V1/Crm/WhatsAppWebhookController.php` | 42-50 | `handle()` calls `processPayload()` with zero signature validation. No `X-Hub-Signature-256` header check. `app_secret` is stored encrypted on the model but never used for webhook verification. Compare with `StripeWebhookController` which properly validates signatures via `Stripe\Webhook::constructEvent()`. Any HTTP client can forge Meta webhook payloads — fake messages, fake status updates, fake conversation opens. |
| S03 | HIGH | `app/Http/Requests/Crm/StoreDocumentRequest.php` | 38 | `file_path` validated as `['required', 'string', 'max:500']` with zero path traversal filtering. No regex to reject `../`, `..\\`, absolute paths, or symbolic links. `DocumentStorageService::download()` at line 83-89 uses this raw path with `Storage::disk('documents')->download($filePath, $fileName)`. An attacker who controls `file_path` (via the direct store flow bypassing file upload) can serve files from outside the tenant's document directory. |
| S04 | HIGH | `app/Http/Controllers/Tenant/Api/V1/Crm/DocumentVersionController.php` | 51 | (Same as A01) `show()` passes `Document::class` (a string) to `Gate::authorize('view', ...)`. The correct call should pass the `$documentVersion` model instance. Since `DocumentVersionPolicy` has no `view()` method defined (only viewAny, create, delete), this falls through to `DocumentPolicy::before()` → grants access with just `documents.view` permission instead of verifying document version ownership. |

**Items verified working (defense-in-depth):**
- Security headers? Not checked but API-only reduces surface
- CSRF: Not applicable for Sanctum token-based API
- Sanctum `serializable_classes` disabled (prevents PHP object unserialization)
- All public routes correctly identified (login, webhooks, health)

---

## Scalability Score: 4/10

| Baseline | Current | Delta |
|----------|---------|-------|
| 4/10 | 4/10 | 0 |

No changes detected. Remaining concerns:

- Default queue driver: `database` — not production-suitable
- No database read replicas configured
- No global API rate limiting (only `auth-login` at 5/min)
- Scout default: `collection` driver (no production search engine configured)
- 187 PHPStan errors at level 5

---

## SaaS Readiness Score: 8/10

| Baseline | Current | Delta |
|----------|---------|-------|
| 8/10 | 8/10 | 0 |

Confirmed working:
- Full subscription lifecycle with trial/active/expired/cancelled/suspended states
- 7-day grace period
- Dunning: 5 escalating retry attempts (1d, 3d, 7d, 14d, manual)
- Plan-level feature gating via `EnsurePlanFeature` middleware
- CRM feature gates: `crm-feature:communications.enabled`, `crm-feature:message_templates.enabled`, `crm-feature:whatsapp.enabled`
- 25 permission modules defined in `config/tenant-permissions.php`
- Usage metering via `UsageCounter` with monthly reset
- Tenant provisioning: creates tenant + domain + roles + permissions + superadmin
- Soft-delete cascade: tenant → users/domains/subscriptions via `TenantObserver`

---

## Production Readiness Score: 4/10

| Baseline | Current | Delta |
|----------|---------|-------|
| 4/10 | 4/10 | 0 |

Confirmed:
- Health endpoint at `GET /api/health` (DB + cache checks)
- Sentry configured with traces, profiles, breadcrumbs
- Laravel Nightwatch installed (v1)
- Scheduled tasks for subscription expiry, billing automation, token pruning
- `DB::prohibitDestructiveCommands()` in production

Remaining gaps (unchanged from baseline):
- No queue health check in health endpoint
- `.env.example` still documents `LOG_LEVEL=debug` and `LOG_STACK=single`
- All monitoring commands run only once daily
- No backup schedule despite `spatie/laravel-backup` installed
- No Docker/deployment configuration
- No HTTPS enforcement in production boot
- PHPStan level 5: 187 errors

---

## Detailed Area Review

### 1. Multi-Tenant Isolation — PASS

The `BelongsToTenant` trait registers a `TenantScope` global scope that appends `WHERE tenant_id = ?` to every query when tenancy is initialized. All CRM models use this trait. PipelineService does not need explicit `->where('tenant_id', ...)` because the global scope provides equivalent protection.

**Risk:** `->withoutGlobalScopes()` can bypass. This is acceptable for the framework pattern.

---

### 2. Authentication & Authorization — CONDITIONAL PASS

- Three Sanctum guards configured: `central-api`, `tenant-api`, `portal-api`
- All controllers use `Gate::authorize()` consistently
- 14 CRM policies have ownership checks on update/delete
- **Exception:** `DocumentVersionController::show()` passes wrong model to `Gate::authorize()` (see S04)
- **Concern:** Sanctum guard list is `['web']` only (line 47 in config/sanctum.php) — the commented-out line shows intention to include `['central-api', 'tenant-api']` but it's not active

---

### 3. Client Portal Security — CONDITIONAL PASS

- Routes protected by outer `auth:tenant-api` + `subscription` middleware
- No rate limiting on portal login endpoint
- PortalUserPolicy exists with Spatie permission checks
- No deactivated-user check on PortalUserPolicy

---

### 4. Queue Reliability — PASS

All 9 jobs have:
- `$timeout = 60`
- `$maxExceptions = 3`
- `retryUntil()` capped at 5 minutes
- `backoff()` = [2, 5, 10, 30]
- `failed()` with structured logging
- Tenant context correctly managed (save/restore pattern)

---

### 5. Transaction Boundaries — EXCELLENT (0 violations)

Verified independently: Every method across 26 services that performs 2+ synchronous DB writes is wrapped in `DB::transaction()`.

**Exception:** `WhatsAppAccountService::disconnect()` — 2 writes outside transaction (see S01).

---

### 6. EventDispatcher Consistency — PASS

- All service methods consistently call `$this->eventDispatcher->record(...)` for domain events
- Events dispatch `RecordTimelineEntryJob` + `TriggerWorkflowJob` with `->afterCommit()`
- `now()` passed correctly at dispatch time, not handle time
- 13 of 14 observers remain empty stubs

---

### 7. Workflow Execution Safety — PASS

- `WorkflowService::trigger()` tenant-scopes queries
- `WorkflowService::execute()` wraps in `DB::transaction()` with `withoutEvents()`
- Cross-tenant guard in `execute()` throws `RuntimeException` on mismatch
- `ExecuteWorkflowJob` has additional cross-tenant safety check
- No recursion risk (actions are synchronous leaf operations)

---

### 8. Timeline Integrity — PASS

- `RecordTimelineEntryJob` uses `firstOrCreate()` with unique constraint on `[tenant_id, entity_type, entity_id, event_type, occurred_at]`
- Prevents duplicate entries for same event at same timestamp
- `$occurredAt` passed as constructor argument (evaluated at dispatch time)

---

### 9. Search Infrastructure (Scout) — INFO

- Default driver: `collection` (no shared database table — resolves previous concern)
- `CentralUser` has `Searchable` trait with `toSearchableArray()`
- `TimelineEntry` has `Searchable` trait
- `CentralUser` is a central-domain model, not tenant-scoped — search is appropriate
- **Gap:** No Scout search configuration for production (no Meilisearch/Typesense)

---

### 10. Document Storage Security — CONDITIONAL FAIL

**Working:**
- Storage quota enforced via `ValidationException` with structured logging
- Tenant-isolated paths: `{$tenantId}/documents/...`
- Signed temporary URLs with 30-min expiry + re-authorization
- Two-layer check on serve: signature validation + Gate authorization

**Failing:**
- `file_path` bypass via raw string input (see S03)
- No rate limiting on download/serve endpoints
- No ownership check on `DocumentPolicy::view()` — anyone with `documents.view` permission sees all tenant documents

---

### 11. Signed Document Delivery — PASS

- `DocumentController::download()` uses `URL::temporarySignedRoute('tenant.crm.documents.serve', ...)` — correct route name confirmed
- `DocumentVersionController::download()` similarly correct
- `serve()` validates signature + re-authorizes via Gate

---

### 12. Storage Quota Enforcement — PASS

- `DocumentStorageService::enforceStorageQuota()` throws `ValidationException` with message showing remaining/required MB
- Logged on `warning` level
- Quota tracked via `FeatureGateService::incrementUsage()/decrementUsage()` on `documents.storage_mb`
- Usage metered in MB, rounded up

---

### 13. Monitoring Infrastructure — INFO

- Health endpoint checks DB + cache
- Custom commands: `monitor:queue-health`, `monitor:job-failures`, `monitor:storage-usage`
- Sentry configured
- **Gap:** No queue check in health endpoint
- **Gap:** Monitoring runs only daily

---

### 14. Feature Gates — PASS

- `EnsurePlanFeature` middleware gates: `feature:{slug}`
- `EnsureCrmFeature` middleware gates: `crm-feature:communications.enabled`, `message_templates.enabled`, `whatsapp.enabled`
- `CheckTenantUsage` middleware for usage enforcement
- `EnsureTenantSubscription` middleware blocks suspended/expired tenants

---

### 15. Permissions — PASS

- 25 permission modules in `config/tenant-permissions.php`
- Consistent CRUD+view action pattern
- `guard_name` isolated between central-api and tenant-api
- Role-based: superadmin, admin, manager, staff with graduated permissions

---

### 16. Policies — PASS (with gaps)

- 57 policies defined
- Ownership checks on update/delete for 14 CRM models
- `before()` hook grants full access to `owner`/`admin` roles
- **Gap:** PipelinePolicy has no ownership checks
- **Gap:** DocumentPolicy::view() has no ownership check
- **Gap:** No policy unit tests

---

### 17. API Resources — PASS

- List vs Detail resource pattern consistently used
- `whenLoaded()` used for eager-loaded relations
- `when()` used for conditional fields (e.g., ApiKey visibility)
- Consistent JSON envelope via `ApiResponseService`

---

### 18. Service Layer Consistency — PASS

- Constructor property promotion consistently used
- EventDispatcher dependency injected on all service constructors
- Thin controllers: query -> authorize -> service call -> resource -> response
- Pagination methods use `paginateWithFilters()` pattern

---

### 19. N+1 Query Risks — PASS

- Services use eager loading: `->with(['stages'])`, `->with(['phoneNumbers'])`
- Resources use `whenLoaded()` to avoid lazy loading in serialization
- API Resources don't access relationships outside `whenLoaded()`

---

### 20. Performance Bottlenecks — INFO

- Default queue driver is `database` (polling-based, not push-based)
- No Redis queue for production
- No database read replicas
- Scout uses `collection` driver (in-memory, no indexing)
- PHPStan: 187 errors — some may hide runtime performance issues

---

### 21. Scalability Risks — UNCHANGED

See Scalability Score section above.

---

### 22. Test Coverage Gaps — CONDITIONAL PASS

| Metric | Value | Status |
|--------|-------|--------|
| Test count | 820 | PASS |
| Assertions | 2192 | PASS |
| Failures | 0 | PASS |
| PHPStan errors (level 5) | 187 | FAIL |
| Pint violations | 0 | PASS |
| Unit tests | 0 | GAP |
| Policy tests | 0 | GAP |
| Model tests | 0 | GAP |
| Coverage tooling | None | GAP |

---

### 23. Production Readiness — NOT READY

- Health endpoint is minimal (no queue check, no storage check)
- Database queue driver
- No deployment configuration
- No backup schedule
- Monitoring runs daily

---

### 24. SaaS Readiness — READY

- Comprehensive billing engine
- Full subscription lifecycle
- Usage metering
- Feature gating
- Tenant provisioning

---

## Findings Summary

### Critical: 0

All critical concerns from pre-hardening audit have been re-evaluated:
- **PipelineService tenant isolation**: Mitigated by BelongsToTenant + TenantScope (idiomatic Laravel). The pre-hardening audit's Critical classification was a false alarm — global scope IS the intended mechanism.
- **No remaining Critical issues.**

### High: 4

| # | Area | File | Description | Impact | Recommended Fix |
|---|------|------|-------------|--------|----------------|
| H01 | Security (Webhook) | `WhatsAppWebhookController.php:42-50` | WhatsApp webhook accepts POST payloads with no `X-Hub-Signature-256` validation. `app_secret` stored but unused. Stripe webhooks validate signatures — clear inconsistency. | Any HTTP client can forge Meta webhook payloads (fake messages, fake status updates). Can inject data into any tenant's WhatsApp pipeline. | Add `hash_hmac('sha256', $request->getContent(), $account->app_secret)` and compare against `$request->header('X-Hub-Signature-256')` before processing. |
| H02 | Data Integrity | `WhatsAppAccountService.php:110-118` | `disconnect()` does two DB writes (`$account->update()` + `$this->eventDispatcher->record()`) outside any transaction. | Partial failure (event record fails) leaves account in disconnected state without audit trail. | Wrap in `DB::transaction()`. |
| H03 | Security (File Access) | `StoreDocumentRequest.php:38` | `file_path` accepted as raw string with no path traversal validation. | Attacker who controls `file_path` (bypassing file upload) can serve files from outside tenant directory via `DocumentStorageService::download()`. | Add regex validation to reject `../`, `..\\`, absolute paths starting with `/` or drive letters. Or disallow raw `file_path` entirely and require uploaded files only. |
| H04 | Authorization | `DocumentVersionController.php:51` | `Gate::authorize('view', Document::class)` passes `Document::class` (string) instead of `$documentVersion` model. Policy lacks `view()` method — falls through to DocumentPolicy grant. | Anyone with `documents.view` permission can view any document version. Ownership check bypassed. | Fix to `Gate::authorize('view', $documentVersion)`. Add `view()` method to `DocumentVersionPolicy`. |

### Medium: 7

| # | Area | File | Description | Impact | Recommended Fix |
|---|------|------|-------------|--------|----------------|
| M01 | API Design | `config/sanctum.php:47` | Sanctum guard list is `['web']` only. Commented-out intention to use `['central-api', 'tenant-api']`. | Sanctum may not resolve the correct guard for API token authentication in some edge cases. | Uncomment and enable `['web', 'central-api', 'tenant-api']`. |
| M02 | Rate Limiting | `app/Providers/AppServiceProvider.php` | No global API rate limiting. Only `auth-login` (5/min) exists across 409 routes. | Single tenant or IP can saturate API resources. | Add named rate limiters and apply via `throttle:api` middleware group. |
| M03 | Monitoring | `app/Http/Controllers/HealthController.php` | Health endpoint checks DB + cache but not queue. | Queue worker can be stopped without detection in a heavily async-driven app. | Add queue connectivity check (e.g., `Queue::size()`) or a synthetic job roundtrip. |
| M04 | Code Quality | `phpstan` | 187 errors at level 5. | Some errors may hide runtime bugs or type confusion vulnerabilities. | Fix PHPStan errors or increase baseline. Minimum target: 0 errors at level 5. |
| M05 | Code Consistency | `TaskCommentController.php`, `TaskReminderController.php` | Inline `$request->validate()` instead of FormRequest classes. | Inconsistent with codebase convention; validation rules not reusable. | Create `StoreTaskCommentRequest`, `UpdateTaskCommentRequest`, `StoreTaskReminderRequest`, `UpdateTaskReminderRequest`. |
| M06 | Search | `config/scout.php` | Default driver `collection`. No Meilisearch/Typesense configured for production. | Search will not work meaningfully in production at scale. | Configure Meilisearch or Typesense with per-tenant index isolation. |
| M07 | Queue | `config/queue.php` | Default driver `database`. No Redis queue. | Polling-based queue not suitable for production throughput. | Switch to Redis queue with Horizon supervisor configuration. |

### Low: 9

| # | Area | File | Description |
|---|------|------|-------------|
| L01 | API Response | `app/Services/ApiResponseService.php` | Status field type inconsistency: `"status": false` (boolean) on errors vs `"status": "success"` (string) on success. |
| L02 | Pagination | `LeadController.php` | Hardcodes `per_page` logic instead of using shared `PaginatesRequestTrait`. |
| L03 | CORS | `config/cors.php` | `allowed_origins => ['*']` — should be restricted in production. |
| L04 | Backups | `app/Console/Kernel.php` | No backup schedule despite `spatie/laravel-backup` installed. |
| L05 | HTTPS | `app/Providers/AppServiceProvider.php` | No `UrlGenerator::forceScheme('https')` in production. |
| L06 | Monitoring | `app/Console/Kernel.php` | All monitoring commands run once daily — should be 5-15 min intervals. |
| L07 | Queue | `app/Jobs/Central/ProcessDunningJob.php` | No chunking on overdue invoice query — memory risk at scale. N+1 on `$invoice->payments()` in loop. |
| L08 | Billing | `app/Jobs/Central/BillingAutomationJob.php` | Invoice number sequence breaks after 9,999 invoices in a day (4-digit format). Check-then-insert race condition. |
| L09 | Scouts | `app/Observers/` | 13 of 14 observers are empty stubs. Either activate or remove. |

---

## Scores

| Category | Pre-Hardening | Post-Hardening | Delta | Assessment |
|----------|--------------|----------------|-------|------------|
| Architecture | 7/10 | 8/10 | +1 | Document route name fixed. CalendarEvent binding correct. Portal auth confirmed. |
| Security | 6/10 | 6/10 | 0 | 4 High findings unfixed. Route-name fix doesn't change score. |
| Scalability | 4/10 | 4/10 | 0 | No changes. DB queue, no replicas, no rate limiting. |
| SaaS Readiness | 8/10 | 8/10 | 0 | Comprehensive and verified. |
| Production Readiness | 4/10 | 4/10 | 0 | No changes. Infrastructure gaps remain. |
| Extensibility | 6/10 | 6/10 | 0 | Stub observers, PHPStan errors. |

---

## Verdict

```
NOT APPROVED
```

**Condition:** 4 High-severity findings have clear exploit paths and must be fixed before production deployment or Sprint 11 implementation.

**If Sprint 11 proceeds without these fixes, the reporting and analytics features will be built on a platform with known security exploits.** Specifically:
- WhatsApp message injection could corrupt analytics data (falsified message counts, conversation metrics)
- Document path traversal could expose sensitive files through download endpoints
- Document version authorization bypass means dashboards could show documents the user should not see

### Remediation Required Before Approval

| Priority | Fix | Estimated Effort |
|----------|-----|-----------------|
| P0 | Add `X-Hub-Signature-256` validation to WhatsAppWebhookController::handle() | 1 hour |
| P0 | Wrap WhatsAppAccountService::disconnect() in `DB::transaction()` | 15 min |
| P0 | Fix `Gate::authorize('view', Document::class)` → `$documentVersion` in DocumentVersionController::show() | 15 min |
| P1 | Add path traversal validation to StoreDocumentRequest::file_path | 30 min |
| P1 | Fix 187 PHPStan errors (or add baseline) | 4-8 hours |

### Recommendation for Sprint 11

**If remediations are applied first:**

Sprint 11 implementation is architecturally compatible with the existing codebase. Recommended approach:

1. **ReportService** — Follow existing service pattern: constructor injection, `paginateWithFilters()`, `BelongsToTenant` trait, EventDispatcher integration
2. **SavedReport model** — Use `BelongsToTenant` trait, JSON casts for `filters` and `columns`
3. **Dashboards** — Leverage existing `ApiResponseService` for consistent response format; use API Resources for data transformation
4. **Exports** — Use existing `dispatch()` pattern for async CSV/XLSX/PDF generation; add new queue if export volume justifies it

**Do NOT proceed until the 4 High findings above are remediated.**
