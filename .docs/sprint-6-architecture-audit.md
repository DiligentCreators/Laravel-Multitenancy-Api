# Sprint 6 Architecture Audit

**Date:** 2026-06-20
**Auditor:** AI Architecture Review
**Scope:** Productivity Layer — Tasks, Task Comments, Task Reminders, Calendar Events, Recurring Events
**Baseline:** Sprint 5 (Service-Level Event Dispatching, Tenant-Safe Workflow Jobs)

---

## Table of Contents

1. [Tasks Architecture](#1-tasks-architecture)
2. [Calendar Architecture](#2-calendar-architecture)
3. [Recurring Event Engine](#3-recurring-event-engine)
4. [Task Assignment Security](#4-task-assignment-security)
5. [Task Reminder Infrastructure](#5-task-reminder-infrastructure)
6. [Notification Integration](#6-notification-integration)
7. [Workflow Create Task Action](#7-workflow-create-task-action)
8. [Timeline Integration](#8-timeline-integration)
9. [Event Dispatcher Usage](#9-event-dispatcher-usage)
10. [Morph Security](#10-morph-security)
11. [Tenant Isolation](#11-tenant-isolation)
12. [Queue Safety](#12-queue-safety)
13. [Soft Delete Behavior](#13-soft-delete-behavior)
14. [Search & Filtering](#14-search--filtering)
15. [API Consistency](#15-api-consistency)
16. [Policy Coverage](#16-policy-coverage)
17. [Request Validation](#17-request-validation)
18. [Scalability](#18-scalability)
19. [Performance](#19-performance)
20. [Test Coverage](#20-test-coverage)
21. [Findings Summary](#21-findings-summary)
22. [Scoring](#22-scoring)
23. [Dependency Graph](#23-dependency-graph)
24. [Domain Map](#24-domain-map)
25. [Technical Debt](#25-technical-debt)
26. [Verdict](#26-verdict)

---

## 1. Tasks Architecture

### Model (`app/Models/Crm/Task.php`)

| Aspect | Verdict | Notes |
|--------|---------|-------|
| `$fillable` | ✅ Correct | All 13 columns match migration |
| `$casts` | ✅ Correct | `TaskPriorityEnum`, `datetime` for dates |
| `#[UsePolicy]` | ✅ Correct | Points to `TaskPolicy::class` |
| `BelongsToTenant` | ✅ Correct | Auto-scopes + auto-fills `tenant_id` |
| `SoftDeletes` | ✅ Correct | Full soft-delete support |
| `morphTo('taskable')` | ✅ Correct | Standard polymorphic |
| Relationships | ✅ Correct | `status()`, `owner()`, `comments()`, `reminders()` |
| `team_id` handling | ⚠️ **Low** | Fillable + indexed but no `team()` relationship defined |

### Service (`app/Services/Crm/TaskService.php`)

| Aspect | Verdict | Notes |
|--------|---------|-------|
| EventDispatcher injection | ✅ Correct | Constructor injection, `private readonly` |
| `create()` event | ✅ Correct | `task.created` with metadata |
| `update()` event detection | ✅ Correct | Detects `completed_at` transition → `task.completed` |
| `delete()` event | ✅ Correct | `task.deleted` before soft-delete |
| `restore()` event | ✅ Correct | `task.restored` after restore |
| Follows LeadService pattern | ✅ Correct | Same structure, improves with completion detection |
| `created_by` / `updated_by` | ❌ **HIGH** | Never set from `Auth::id()` — audit trail broken |
| `forceDelete()` event | ⚠️ **Low** | No `task.force_deleted` event (consistent with LeadService) |
| Usage counters | ⚠️ **Medium** | No `FeatureGateService` integration for task quotas |

### Controller (`app/Http/Controllers/Tenant/Api/V1/Crm/TaskController.php`)

| Concern | Verdict |
|---------|---------|
| Service layer bypassed? | ✅ **NO** — all operations delegate to `TaskService` |
| Timeline written directly? | ✅ **NO** — no direct TimelineWriter/RecordTimelineEntryJob calls |
| Workflows triggered directly? | ✅ **NO** — no direct WorkflowService/TriggerWorkflowJob calls |
| EventDispatcher in controller? | ✅ **NO** — only inside `TaskService` |
| Gate checks present? | ✅ YES — all actions gated |
| `restore()` permission | ❌ **HIGH** — `Gate::authorize('create', Task::class)` is semantically wrong |

### Policy (`app/Policies/Crm/TaskPolicy.php`)

| Method | Permission | Verdict |
|--------|-----------|---------|
| `viewAny()` | `tasks.view` | ✅ Correct |
| `view()` | `tasks.view` | ✅ Correct |
| `create()` | `tasks.create` | ✅ Correct |
| `update()` | `tasks.update` | ✅ Correct |
| `delete()` | `tasks.delete` | ✅ Correct |
| `restore()` | ❌ **Missing** | — No dedicated method, controller falls back to `create` gate |
| `forceDelete()` | ❌ **Missing** | — No endpoint yet, but no gate either |
| Owner scoping | ⚠️ **Medium** | No $user->id === $task->owner_id checks — purely permission-based |

### Form Requests

| Request | Key Issue |
|---------|-----------|
| `StoreTaskRequest` | ❌ **CRITICAL** — `taskable_id` has no existence/tenant validation |
| `UpdateTaskRequest` | ❌ **CRITICAL** — Same issue; taskable can be changed to reference cross-tenant entity |

### Resource (`app/Http/Resources/Tenant/Api/V1/Crm/TaskResource.php`)

✅ Correct — uses `whenLoaded`, `whenCounted`, no sensitive data exposure.

---

## 2. Calendar Architecture

### Model (`app/Models/Crm/CalendarEvent.php`)

✅ All relationships, casts, fillable, traits correct.
⚠️ `team_id` is fillable but no `team()` relationship (same issue as Task).

### Service (`app/Services/Crm/CalendarEventService.php`)

| Aspect | Verdict |
|--------|---------|
| EventDispatcher usage | ✅ Correct — `calendar.created/updated/deleted/restored` |
| RecurringEventAction usage | ✅ Correct — extracted from request data before mass-assignment |
| `create()` transaction | ⚠️ **Medium** — Not wrapped in transaction; orphaned event if `generate()` fails |
| `update()` recurring | ⚠️ **Medium** — Cannot modify/remove recurring pattern on update |
| `restore()` cascade | ⚠️ **Medium** — Does NOT restore RecurringEventPattern or sibling occurrences |
| `forceDelete()` | ⚠️ **Medium** — Orphans RecurringEventPattern; no timeline event |
| `created_by` / `updated_by` | ❌ **HIGH** — Never set from `Auth::id()` |

### Controller (`app/Http/Controllers/Tenant/Api/V1/Crm/CalendarEventController.php`)

✅ Clean — no service bypass, no direct timeline/workflow calls.
⚠️ `restore()` uses `Gate::authorize('create', CalendarEvent::class)` — semantically wrong.
⚠️ No `forceDelete` endpoint exposed (service has the method).

### Policy (`app/Policies/Crm/CalendarEventPolicy.php`)

✅ Permission names `calendar.{view,create,update,delete}` consistent.
❌ **Missing:** `restore()` method, `forceDelete()` method.

### Form Requests

| Request | Issues |
|---------|--------|
| `StoreCalendarEventRequest` | ⚠️ No server-side cap on `occurrences_limit`; ⚠️ `recurring.ends_at` not validated against `starts_at` |
| `UpdateCalendarEventRequest` | ⚠️ **High** — No `recurring` field at all; cannot modify pattern on update |

---

## 3. Recurring Event Engine

**File:** `app/Actions/Crm/RecurringEventAction.php`

### Findings

| Concern | Severity | Detail |
|---------|----------|--------|
| **Duplicate generation** | 🔴 **HIGH** | `generate()` is NOT idempotent. If called twice on the same event, it creates a second pattern + second set of occurrences. No check for existing `recurring_event_pattern_id`. |
| **DST handling** | 🟡 **Medium** | Uses raw Carbon `add()` — during DST transitions, events drift by ±1 hour |
| **Timezone handling** | 🟡 **Medium** | Completely timezone-agnostic; no user timezone support |
| **MAX_OCCURRENCES = 365** | 🟡 **Medium** | Hardcoded, not configurable. Falls back to 365 if no limit specified. |
| **No server-side cap** | 🟡 **Medium** | User-supplied `occurrences_limit` can exceed MAX_OCCURRENCES (it's only the fallback, not a cap) |
| **Transaction boundary** | 🟡 **Medium** | Internal transaction (pattern + occurrences) but **caller** (`CalendarEventService::create()`) is outside transaction |
| **N+1 INSERTs** | 🟡 **Medium** | Each occurrence is a separate INSERT — 367+ queries for 365 daily events |
| **Soft-delete handling** | 🟡 **Medium** | `generate()` doesn't check if original event is trashed; generating against deleted events possible |
| **`frequencyUnit()` fallback** | 🟢 **Low** | Unknown enum values silently default to 'day' — should throw or fail explicitly |
| **`ends_at` comparison** | 🟢 **Low** | Date-only comparison uses midnight boundary (pattern ends at midnight on end date) |

---

## 4. Task Assignment Security

| Concern | Verdict | Detail |
|---------|---------|--------|
| `owner_id` tenant-scoped | ✅ **Pass** | `Rule::exists('users', 'id')->where('tenant_id', tenant()->id)` on StoreTaskRequest |
| Route model binding scoped | ✅ **Pass** | `TenantScope` on all models prevents cross-tenant access |
| Workflow-created task ownership | ✅ **Pass** | Inherits `$entity->tenant_id` correctly |
| No owner-level policy scoping | ⚠️ **Medium** | `view()`/`update()` check only permission, not ownership — any user with `tasks.update` can reassign any task |

---

## 5. Task Reminder Infrastructure

### Service (`app/Services/Crm/TaskReminderService.php`)

| Concern | Severity | Detail |
|---------|----------|--------|
| EventDispatcher | 🔴 **HIGH** | **Not used at all** — no timeline entries, no workflow triggers for reminders |
| NotificationService call | 🔴 **HIGH** | `queue($reminder->owner_id, ...)` passes integer ID instead of a notifiable User model. Works now because stub is empty, will fail when implemented. |
| `created_by` | 🟡 **Medium** | Never set |
| `updated_by` | 🟡 **Medium** | Never set |

### Controller (`app/Http/Controllers/Tenant/Api/V1/Crm/TaskReminderController.php`)

✅ No service bypass.
⚠️ No EventDispatcher.

---

## 6. Notification Integration

| Aspect | Verdict |
|--------|---------|
| NotificationService stub | ✅ **No leak possible** — all methods are empty `{}` |
| TaskReminder integration | ❌ **HIGH** — Passes raw integer `$owner_id` instead of User model |
| EventDispatcher notification path | ✅ **Not used** (NotificationService is independent) |

---

## 7. Workflow Create Task Action

**File:** `app/Services/Crm/WorkflowService.php` (lines 127–139)

| Concern | Verdict |
|---------|---------|
| Tenant context inheritance | ✅ **Correct** — `'tenant_id' => $entity->tenant_id` |
| Taskable morph linkage | ✅ **Correct** — `'taskable_type' => $entity->getMorphClass()`, `'taskable_id' => $entity->getKey()` |
| `withoutEvents()` scope | ✅ **Correct** — Only disables events on triggering entity, not on Task |
| Cross-tenant guard | ✅ **Correct** — Runtime check in `execute()` throws exception |
| `created_by` missing | ⚠️ **Low** — Created task won't have `created_by` set |
| Task model now exists | ✅ **Resolved** — Previously blocked by missing Task model |

---

## 8. Timeline Integration

| Entity | Timeline Events | Verdict |
|--------|----------------|---------|
| Task | `task.created`, `task.updated`, `task.completed`, `task.deleted`, `task.restored` | ✅ **Full coverage** |
| CalendarEvent | `calendar.created`, `calendar.updated`, `calendar.deleted`, `calendar.restored` | ✅ **Full coverage** |
| TaskComment | **None** | ❌ **No events** — EventDispatcher not used |
| TaskReminder | **None** | ❌ **No events** — EventDispatcher not used |

All timeline entries are dispatched via `RecordTimelineEntryJob` (queue: `timeline`, `afterCommit`) ✅

---

## 9. Event Dispatcher Usage

| Service | EventDispatcher | Pattern |
|---------|----------------|---------|
| `TaskService` | ✅ Used | Service-level (LeadService pattern) ✅ |
| `CalendarEventService` | ✅ Used | Service-level ✅ |
| `TaskCommentService` | ❌ **Not used** | — No events at all |
| `TaskReminderService` | ❌ **Not used** | — No events at all |

**Verdict:** Inconsistent. Core entities (Task, CalendarEvent) use EventDispatcher correctly. Sub-entities (TaskComment, TaskReminder) skip it entirely. This should be consistent.

---

## 10. Morph Security

**File:** `app/Services/Crm/MorphableEntityResolver.php`

| Aspect | Verdict |
|--------|---------|
| Whitelist-based | ✅ **Secure** — Only 8 types allowed |
| Blocks arbitrary classes | ✅ **Yes** — `resolve()` checks key in `ALLOWED_TYPES` or FQCN in values; throws `ValidationException` otherwise |
| Can inject `App\Models\Crm\Task`? | ✅ **Yes, but allowed** — Task is in ALLOWED_TYPES |
| Can inject `App\Models\User`? | ✅ **Blocked** — Not in ALLOWED_TYPES |
| Form request `taskable_id` | ❌ **CRITICAL** — No tenant-scoped existence check on morph target |


### Validation Gap Detail

Both `StoreTaskRequest` and `UpdateTaskRequest` validate:
```php
'taskable_type' => ['nullable', 'string', 'in:person,organization,...,task,calendar-event'],
'taskable_id'   => ['required_with:taskable_type', 'integer', 'min:1'],
```

`taskable_id` has no `->exists()` rule. An attacker can:
1. Set `taskable_type=lead` and `taskable_id=999999` (non-existent) — creates an orphaned task
2. Set `taskable_type=lead` and `taskable_id=1` (a lead in another tenant) — **cross-tenant reference**

The same gap exists in `StoreCalendarEventRequest` / `UpdateCalendarEventRequest` for `eventable_id`.

---

## 11. Tenant Isolation

| Check | Entities | Verdict |
|-------|----------|---------|
| Global `TenantScope` | Task, TaskComment, TaskReminder, CalendarEvent, RecurringEventPattern | ✅ **ALL covered** |
| Auto-fill `tenant_id` on create | All | ✅ **Via BelongsToTenant boot** |
| Route model binding scoped | All | ✅ **TenantScope applies** |
| `owner_id` existence scoped | Task, CalendarEvent | ✅ `->where('tenant_id', tenant()->id)` |
| `status_id` existence scoped | Task | ✅ `->where('tenant_id', tenant()->id)` |
| `taskable_id` existence scoped | **NONE** | ❌ **CRITICAL** — No check on morph target tenant |
| `eventable_id` existence scoped | **NONE** | ❌ **CRITICAL** — Same issue |
| `parent_id` scope (TaskComment) | **NONE** | ❌ **HIGH** — `exists:crm_task_comments,id` bypasses Eloquent global scopes |
| Tenant isolation tests | TaskTest ✅, CalendarEventTest ✅ | ❌ TaskCommentTest, TaskReminderTest **missing** |
| Queue job tenant awareness | All | ✅ **Verified in Sprint 5** — jobs serialize tenantId, init/end tenancy |
| Cross-tenant workflow guard | WorkflowService | ✅ `$entity->tenant_id !== $workflow->tenant_id` |

---

## 12. Queue Safety

| Job | Tenant-Aware | Direct DB Access | Verdict |
|-----|-------------|-----------------|---------|
| `RecordTimelineEntryJob` | ✅ Yes (from Sprint 5) | ✅ Creates TimelineEntry | ✅ Safe |
| `TriggerWorkflowJob` | ✅ Yes (from Sprint 5 fix) | ✅ Queries WorkflowDefinition | ✅ Safe |
| `ExecuteWorkflowJob` | ✅ Yes (from Sprint 5 fix) | ✅ Creates WorkflowLog | ✅ Safe |
| Notification queue | N/A | ✅ Stub only | ✅ Safe |

No Sprint 6-specific queue jobs were created — all side effects are dispatched through existing Sprint 5 jobs.

---

## 13. Soft Delete Behavior

| Entity | Soft Delete | Restore Events | Cascade Restore | forceDelete Events |
|--------|------------|----------------|-----------------|-------------------|
| Task | ✅ | `task.restored` | N/A | ❌ No `task.force_deleted` |
| CalendarEvent | ✅ | `calendar.restored` | ❌ Does NOT restore sibling occurrences or pattern | ❌ Orphans RecurringEventPattern |
| TaskComment | ✅ | ❌ No event | N/A | ❌ No event |
| TaskReminder | ✅ | ❌ No event | N/A | ❌ No event |
| RecurringEventPattern | ✅ | ❌ No event | N/A | ❌ No event |

---

## 14. Search & Filtering

| Entity | Search Fields | Filters | Sort | Pagination |
|--------|-------------|---------|------|-----------|
| Task | title, description (LIKE) | status_id, priority, owner_id | sort_by, sort_order (default created_at desc) | 25 default, 100 max |
| CalendarEvent | title, description (LIKE) | status, owner_id, from_date, to_date | sort_by, sort_order (default starts_at desc) | 25 default, 100 max |
| TaskComment | content (LIKE) | parent_id | sort_by, sort_order | 25 default, 100 max |
| TaskReminder | — | — | — | 25 default, 100 max |

All use `LIKE '%...%'` — no full-text search. Acceptable at current scale but won't scale beyond ~100K records per entity.

---

## 15. API Consistency

| Aspect | Pattern | Task | CalendarEvent | TaskComment | TaskReminder |
|--------|---------|------|---------------|-------------|-------------|
| Route prefix | `crm/...` | ✅ tasks | ✅ calendar-events | ✅ tasks/{taskId}/comments | ✅ tasks/{taskId}/reminders |
| HTTP verbs | REST | ✅ | ✅ | ✅ | ✅ |
| Status codes | 201/200 | ✅ | ✅ | ✅ | ✅ |
| Response format | `{status, message, data, meta}` | ✅ | ✅ | ✅ | ✅ |
| `restore` route | `POST /{id}/restore` | ✅ | ✅ | ❌ Not exposed | ❌ Not exposed |
| `forceDelete` route | `DELETE /{id}/force` | ❌ | ❌ | ❌ | ❌ |

---

## 16. Policy Coverage

| Entity | viewAny | view | create | update | delete | restore | forceDelete |
|--------|---------|------|--------|--------|--------|---------|-------------|
| Task | ✅ tasks.view | ✅ tasks.view | ✅ tasks.create | ✅ tasks.update | ✅ tasks.delete | ❌ (falls to create) | ❌ |
| TaskComment | ✅ tasks.view | ✅ tasks.view | ✅ tasks.update | ✅ tasks.update | ✅ tasks.delete | ❌ | ❌ |
| TaskReminder | ✅ tasks.view | ✅ tasks.view | ✅ tasks.update | ✅ tasks.update | ✅ tasks.delete | ❌ | ❌ |
| CalendarEvent | ✅ calendar.view | ✅ calendar.view | ✅ calendar.create | ✅ calendar.update | ✅ calendar.delete | ❌ (falls to create) | ❌ |

---

## 17. Request Validation

| Entity | Fields | Tenant-Scoped Exists | Cross-Tenant Protection | Verdict |
|--------|--------|---------------------|------------------------|---------|
| Task | title, description, status_id, priority, due_at, owner_id, taskable_type, taskable_id | status_id ✅, owner_id ✅ | **taskable_id** ❌ | **CRITICAL gap** |
| CalendarEvent | title, description, starts_at, ends_at, all_day, status, location, color, owner_id, eventable_type, eventable_id, recurring.* | owner_id ✅ | **eventable_id** ❌ | **CRITICAL gap** |
| TaskComment | content, parent_id | — | **parent_id** ❌ (`exists` bypasses TenantScope) | **HIGH** |
| TaskReminder | remind_at | — | — | ✅ |

---

## 18. Scalability

| Concern | Assessment |
|---------|-----------|
| LIKE search on large tables | ⚠️ Medium — `LIKE '%...%'` cannot use indexes; will degrade beyond ~100K rows |
| Pre-generated recurring occurrences | ✅ Design choice — 365 events max per series, acceptable |
| N+1 INSERTs in recurring generation | ⚠️ Medium — Should use bulk insert for large occurrence counts |
| Pagination capped at 100 | ✅ Prevents abuse |
| Global TenantScope on all queries | ✅ Simplifies tenant isolation without manual WHERE clauses |
| No indexing on `content` columns | ⚠️ Low — Full-text search would need dedicated indexes |

---

## 19. Performance

| Concern | Assessment |
|---------|-----------|
| Eager loading in `query()` | ✅ Task loads `status`, `owner`; CalendarEvent loads `owner` |
| `whenLoaded` in resources | ✅ Prevents N+1 on serialization |
| `whenCounted('comments')` | ✅ Efficient subquery count |
| `paginate()` vs `cursor()` | ✅ Correct for paginated APIs |
| Recurring event INSERTs | ⚠️ Medium — 367 INSERTs in one transaction for 365 daily events |
| Double fetch in `show()` | ⚠️ Low — Route binding resolves model, then service re-fetches with relations |

---

## 20. Test Coverage

| Test File | Tests | Coverage Gaps | Verdict |
|-----------|-------|---------------|---------|
| `TaskTest.php` | 20 | ✅ CRUD, search, filters, pagination, tenant isolation, 401/403/404/422, soft-delete restore | ✅ **Good** |
| `CalendarEventTest.php` | 22 | ✅ CRUD, recurring, all_day, location/color, filters, tenant isolation, 401/403/404/422, restore | ✅ **Good** |
| `TaskCommentTest.php` | 7 | ❌ **No tenant isolation**, ❌ **No 403/401 tests**, ❌ No replies test, ❌ No pagination, ❌ No soft-delete | ⚠️ **Incomplete** |
| `TaskReminderTest.php` | 7 | ❌ **No tenant isolation**, ❌ **No 403/401 tests**, ❌ No pagination, ❌ No soft-delete, ❌ No notification assertion | ⚠️ **Incomplete** |

**Total Sprint 6 tests:** 56

---

## 21. Findings Summary

### Critical (2)

| ID | File | Issue |
|----|------|-------|
| **C1** | `StoreTaskRequest`, `UpdateTaskRequest` | `taskable_id` has **no existence/tenant validation**. Attacker can link task to any entity across tenants. |
| **C2** | `StoreCalendarEventRequest`, `UpdateCalendarEventRequest` | `eventable_id` has **no existence/tenant validation**. Same cross-tenant reference vulnerability. |

### High (8)

| ID | File | Issue |
|----|------|-------|
| **H1** | `TaskService`, `CalendarEventService`, `TaskCommentService`, `TaskReminderService` | `created_by` / `updated_by` **never set from `Auth::id()`** — audit trail broken across all entities |
| **H2** | `TaskCommentController` | `parent_id` validation uses `exists:crm_task_comments,id` which **bypasses Eloquent TenantScope** — cross-tenant parent_id injection |
| **H3** | `TaskReminderService` | `NotificationService::queue()` called with **integer `$owner_id`** instead of User model — will fail when NotificationService is implemented |
| **H4** | `TaskCommentService`, `TaskReminderService` | **EventDispatcher not used** — no timeline entries, no workflow triggers for sub-entities |
| **H5** | `TaskController`, `CalendarEventController`, `TaskCommentController`, `TaskReminderController` | `restore()` gated by `create` permission — semantically wrong, no dedicated restore permission |
| **H6** | `RecurringEventAction` | **Not idempotent** — calling `generate()` twice on same event creates duplicate pattern + occurrences |

### Medium (11)

| ID | File | Issue |
|----|------|-------|
| **M1** | `RecurringEventAction` | DST/timezone completely unhandled in occurrence calculation |
| **M2** | `CalendarEventService::create()` | Not wrapped in transaction — orphaned event if `generate()` fails |
| **M3** | `CalendarEventService::restore()` | Does not cascade to RecurringEventPattern or sibling occurrences |
| **M4** | `CalendarEventService::forceDelete()` | Orphans RecurringEventPattern; no timeline event |
| **M5** | `UpdateCalendarEventRequest` | No `recurring` field — cannot modify pattern on update |
| **M6** | `RecurringEventAction` | No server-side cap on `occurrences_limit` (hardcoded 365 fallback is not enforced as max) |
| **M7** | `RecurringEventAction` | N+1 INSERTs for occurrence generation (367 queries for 365 events) |
| **M8** | `CalendarEventController` | No `forceDelete` endpoint exposed |
| **M9** | `TaskCommentTest`, `TaskReminderTest` | **Missing tenant isolation tests** |
| **M10** | `TaskCommentTest`, `TaskReminderTest` | **Missing 403/401 permission tests** |
| **M11** | Policies | No ownership scoping — any user with `tasks.view` can view any task in the tenant |

### Low (8)

| ID | File | Issue |
|----|------|-------|
| **L1** | `CalendarEventResource` | No `recurringPattern` relationship included — consumers see only FK |
| **L2** | `CalendarEventResource` | No timezone on timestamps |
| **L3** | `TaskResource` | `deleted_at` always included in response |
| **L4** | `RecurringEventAction` | `frequencyUnit()` silently defaults to 'day' on unknown values |
| **L5** | `RecurringEventAction` | `ends_at` date-only comparison uses midnight boundary |
| **L6** | All models | `team_id` fillable + indexed but no `team()` relationship |
| **L7** | `MorphableEntityResolver` | `getValidationRule()` exposes FQCNs in API responses |
| **L8** | `forceDelete()` in services | No `entity.force_deleted` timeline event (consistent with LeadService) |

---

## 22. Scoring

### Architecture Score: **7.9 / 10**

| Criterion | Score | Reasoning |
|-----------|-------|-----------|
| Layering (Controller → Service → EventDispatcher → Jobs) | 9/10 | Clean separation for core entities; sub-entities skip EventDispatcher |
| Pattern consistency | 7/10 | Task + CalendarEvent follow LeadService; TaskComment + TaskReminder diverge |
| Event-driven design | 7/10 | Timeline + workflows event-driven; missing coverage for 2 of 4 entities |
| Service reusability | 9/10 | Services are injectable, testable, framework-agnostic |
| Error handling | 7/10 | No @throws annotations; orphaned entities on failure paths |

### Productivity Layer Score: **7.5 / 10**

| Criterion | Score | Reasoning |
|-----------|-------|-----------|
| Feature completeness | 8/10 | Tasks, comments, reminders, calendar, recurring all built |
| Recurring engine robustness | 6/10 | Missing idempotency, DST, timezone, transaction boundary, cap enforcement |
| Notification integration | 5/10 | Stub only; broken parameter type when implemented |
| Team support | 4/10 | team_id in schema but no relationship, no scope, no API |
| API completeness | 8/10 | Missing forceDelete, trashed endpoints; restore permission wrong |

### Security Score: **7.5 / 10**

| Criterion | Score | Reasoning |
|-----------|-------|-----------|
| Tenant isolation (query level) | 9/10 | TenantScope covers all models |
| Tenant isolation (validation level) | 5/10 | CRITICAL: taskable_id/eventable_id/parent_id not validated within tenant |
| Morph whitelist | 10/10 | Blocks arbitrary model classes |
| Permission coverage | 7/10 | Missing restore/forceDelete gates; no ownership scoping |
| Input validation | 7/10 | Cross-tenant exists bypass on parent_id; missing existence checks on morph targets |

### SaaS Readiness Score: **8.0 / 10**

| Criterion | Score | Reasoning |
|-----------|-------|-----------|
| Tenant isolation | 8/10 | Strong query-level; gaps at validation level |
| Scalability | 8/10 | LIKE search needs addressing; pagination capped; recurring event generation needs optimization |
| Configurability | 7/10 | MAX_OCCURRENCES hardcoded; no feature-flag middleware for calendar |
| Audit trail | 6/10 | Created_by/updated_by broken; TaskComment + TaskReminder lack timeline events altogether |
| Plan gating | 7/10 | Tasks + calendar features seeded; no feature middleware on calendar routes |

### Production Readiness Score: **8.0 / 10**

| Criterion | Score | Reasoning |
|-----------|-------|-----------|
| Test coverage | 8/10 | 56 Sprint 6 tests; gaps in sub-entity coverage |
| Queue safety | 10/10 | All jobs tenant-aware; afterCommit; no new jobs created |
| Error resilience | 7/10 | Orphaned entities on failure; no bulk insert; no retry logic for recurring |
| Monitoring | 6/10 | No timeline events for sub-entities means no monitoring visibility |
| Migration safety | 9/10 | Down methods present; idempotent seeders |

---

## 23. Dependency Graph

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Routes (crm-v1.php)                    │
│  /tasks, /calendar-events, /tasks/{id}/comments, /reminders   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Controllers                                 │
│  TaskController, CalendarEventController,                     │
│  TaskCommentController, TaskReminderController                 │
│  [Gate::authorize] → Policies                                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Services (Business Logic)                   │
│  TaskService ────────┐  CalendarEventService ────┐            │
│  TaskCommentService  │  TaskReminderService ──┐  │            │
└──────────────────────┼────────────────────────┼──┴──────────┘
                       │                        │
          ┌────────────┘            ┌────────────┘
          ▼                         ▼
┌──────────────────────┐  ┌──────────────────────────┐
│   EventDispatcher    │  │  RecurringEventAction     │
│   [Timeline +        │  │  [Creates RecurringEvent  │
│    Workflow trigger] │  │   Pattern + occurrences]  │
└────────┬─────────────┘  └───────────┬──────────────┘
         │                            │
         ▼                            ▼
┌──────────────────┐      ┌──────────────────────┐
│ RecordTimeline   │      │ CalendarEvent::create │
│ EntryJob (queue) │      │ (N INSERTs)           │
└──────────────────┘      └──────────────────────┘
         │
         ▼
┌──────────────────┐      ┌──────────────────────┐
│ TriggerWorkflow  │      │ NotificationService   │
│ Job (queue)      │──────│ (stub → TaskReminder) │
└──────────────────┘      └──────────────────────┘
         │
         ▼
┌──────────────────┐
│ ExecuteWorkflow  │
│ Job (queue)      │
│ → create_task    │
└──────────────────┘
```

**Legend:**
- Solid lines: Implemented and verified
- Dashed lines: Missing (TaskComment, TaskReminder skip EventDispatcher)
- Dotted: Partial implementation (NotificationService is stub)

---

## 24. Domain Map

```
┌─────────────────────────────────────────────────────────────┐
│                    CRM DOMAIN (Sprint 6)                      │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                    PRODUCTIVITY LAYER                   │ │
│  │                                                         │ │
│  │  ┌─────────┐  ┌──────────────┐  ┌───────────────────┐  │ │
│  │  │  Task   │  │    Task      │  │  Task Reminder    │  │ │
│  │  │         │──│   Comment    │  │                   │  │ │
│  │  │ • CRUD  │  │ • CRUD       │  │ • CRUD            │  │ │
│  │  │ • morph │  │ • threaded   │  │ • notify →        │  │ │
│  │  │ • L10n  │  │ • sub-res    │  │   NotificationSvc │  │ │
│  │  └────┬────┘  └──────────────┘  └───────────────────┘  │ │
│  │       │                                                 │ │
│  │       ▼                                                 │ │
│  │  ┌────────────────────┐  ┌──────────────────────────┐   │ │
│  │  │  Calendar Event    │  │  Recurring Event Pattern │   │ │
│  │  │                    │  │                          │   │ │
│  │  │ • CRUD             │  │ • frequency (d/w/m/y)    │   │ │
│  │  │ • all_day          │  │ • interval               │   │ │
│  │  │ • location/color   │  │ • ends_at / occurrences  │   │ │
│  │  │ • morph eventable  │  │ • generates occurences   │   │ │
│  │  │ • recurring series │──│   via RecurringEventAct  │   │ │
│  │  └────────────────────┘  └──────────────────────────┘   │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                 CROSS-CUTTING (Sprint 5)                │ │
│  │  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │ │
│  │  │ Timeline    │  │ Workflow     │  │ Notification  │  │ │
│  │  │ (append-    │  │ (trigger →   │  │ (stub)        │  │ │
│  │  │  only log)  │  │  execute)    │  │               │  │ │
│  │  └─────────────┘  └──────────────┘  └───────────────┘  │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                 FOUNDATION (Sprint 4.5)                 │ │
│  │  ┌─────────────┐  ┌─────────────────┐  ┌────────────┐  │ │
│  │  │ Event       │  │ MorphableEntity │  │ Feature    │  │ │
│  │  │ Dispatcher  │  │ Resolver        │  │ Gate Svc   │  │ │
│  │  └─────────────┘  └─────────────────┘  └────────────┘  │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                CORE CRM (Sprint 1-4)                    │ │
│  │  People, Orgs, Leads, Pipelines, Activities,            │ │
│  │  Notes, Comments, Tags, Sources, Statuses, Addresses    │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## 25. Technical Debt

### New Debt from Sprint 6

| ID | Debt Item | Severity | Estimated Effort |
|----|-----------|----------|------------------|
| TD-1 | Add tenant-scoped existence validation for `taskable_id` and `eventable_id` in all form requests | Critical | 2h |
| TD-2 | Auto-set `created_by` / `updated_by` in TaskService, CalendarEventService, TaskCommentService, TaskReminderService | High | 1h |
| TD-3 | Add `restore()` and `forceDelete()` policy methods with dedicated permissions | High | 2h |
| TD-4 | Inject EventDispatcher into TaskCommentService and TaskReminderService for timeline + workflow events | High | 2h |
| TD-5 | Fix `parent_id` validation in TaskCommentController to use tenant-scoped exists rule | High | 0.5h |
| TD-6 | Fix `NotificationService::queue()` call in TaskReminderService to pass User model, not integer | High | 0.5h |
| TD-7 | Make `RecurringEventAction::generate()` idempotent (check existing pattern_id, throw on duplicate) | High | 1h |
| TD-8 | Add server-side max cap to `occurrences_limit` in form request validation | Medium | 0.5h |
| TD-9 | Wrap `CalendarEventService::create()` in DB transaction | Medium | 0.5h |
| TD-10 | Add DST-safe date calculations to `RecurringEventAction` | Medium | 2h |
| TD-11 | Add `recurring` field support to `UpdateCalendarEventRequest` | Medium | 2h |
| TD-12 | Add tenant isolation + permission tests for TaskComment and TaskReminder | Medium | 3h |
| TD-13 | Add `forceDelete` routes and controller endpoints for all entities | Medium | 1h |
| TD-14 | Cascade restore/forceDelete to RecurringEventPattern and sibling occurrences | Medium | 2h |
| TD-15 | Make `MAX_OCCURRENCES` configurable (env or config file) | Low | 0.5h |
| TD-16 | Bulk-insert recurring occurrences instead of N+1 INSERTs | Low | 1h |
| TD-17 | Add `team()` relationship and team scoping to Task and CalendarEvent | Low | 2h |
| TD-18 | Add timezone context to CalendarEventResource timestamps | Low | 1h |
| TD-19 | Expose `recurringPattern` details in CalendarEventResource | Low | 0.5h |
| TD-20 | Replace `LIKE '%...%'` with full-text search for large-scale deployments | Low | 4h |

**Total estimated effort:** ~28 hours

### Pre-existing Debt (Not Sprint 6)

| Debt Item | Status |
|-----------|--------|
| ActivityService/NoteService/CommentService use EventDispatcher at controller level (pre-existing pattern inconsistency) | Unchanged |
| No usage counter integration on any entity | Unchanged |
| `forceDelete()` never records timeline events (across all entities) | Unchanged |
| Created_by/updated_by not auto-set in pre-Sprint-6 entities | Pre-existing |

---

## 26. Verdict

| Criterion | Score | Threshold | Result |
|-----------|-------|-----------|--------|
| Architecture Score | 7.9 / 10 | ≥ 7.0 | ✅ PASS |
| Productivity Layer Score | 7.5 / 10 | ≥ 7.0 | ✅ PASS |
| Security Score | 7.5 / 10 | ≥ 7.0 | ✅ PASS |
| SaaS Readiness Score | 8.0 / 10 | ≥ 7.0 | ✅ PASS |
| Production Readiness Score | 8.0 / 10 | ≥ 6.5 | ✅ PASS |

### Conditions for Approval

The following **Critical** findings must be resolved before this can be considered fully production-safe:

1. **C1** — `taskable_id` tenant-scoped existence validation
2. **C2** — `eventable_id` tenant-scoped existence validation

The following **High** findings should be resolved before next sprint:

3. **H1** — `created_by` / `updated_by` auto-set
4. **H2** — `parent_id` tenant-scoped exists rule
5. **H3** — `NotificationService::queue()` parameter type fix
6. **H4** — EventDispatcher integration for TaskComment + TaskReminder
7. **H5** — Dedicated `restore` permissions
8. **H6** — RecurringEventAction idempotency

---

## FINAL VERDICT: **APPROVED** (with conditions)

Sprint 6 is **architecturally approved** for the four core entities (Task, CalendarEvent, TaskComment, TaskReminder, RecurringEventPattern). The layering, tenant isolation (at the query level), queue safety, morph security, and event-driven design are sound.

**However, this approval is conditional on resolving the 2 Critical (C1, C2) and 6 High (H1–H6) findings**. These represent genuine security vulnerabilities (cross-tenant reference via morph fields) and architectural gaps (EventDispatcher bypass, broken audit trail, broken notification integration) that should be addressed before the next deployment to production.

**Recommendation:** Schedule a Sprint 6.5 remediation sprint for the Critical + High items before beginning Sprint 7.
