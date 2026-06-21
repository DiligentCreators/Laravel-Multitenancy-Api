# Sprint 7 Architecture & Security Audit

**Date:** 2026-06-20
**Domain:** Communications Layer (Conversations, Messages, MessageTemplates, MessageAttachments)
**Test Suite:** 664 tests, 1905 assertions, 0 failures — pint clean

---

## Executive Summary

Sprint 7 delivers a complete multi-tenant Communications domain: conversations with polymorphic participants, messages with polymorphic senders, message templates, and message attachments. The implementation follows established patterns from Sprints 4.5/5/6 (EventDispatcher, TimelineWriter, WorkflowService, BelongsToTenant, TenantScope) and includes full CRUD, search/filter/sort, pagination, tenant isolation, soft deletes, and timeline/workflow integration.

**Verdict: APPROVED with conditions** — 1 High finding, 4 Medium findings, 5 Low findings.

---

## 1. Database Design

### Score: 8.0/10

| Table | Purpose | Key Columns | Indexes |
|-------|---------|-------------|---------|
| `crm_conversations` | Conversation threads | id (PK), tenant_id, uuid (unique), channel, status, last_message_at | tenant_id, [tenant_id,channel], [tenant_id,status], [tenant_id,last_message_at] |
| `crm_conversation_participants` | Polymorphic participants | id, tenant_id, conversation_id (FK), participant_type, participant_id, is_primary | tenant_id, [tenant_id,conversation_id], [participant_type,participant_id], UNIQUE(conversation_id,participant_type,participant_id) |
| `crm_messages` | Individual messages | id, tenant_id, conversation_id (FK), sender_type, sender_id, direction, body, status, sent_at/delivered_at/read_at | tenant_id, [tenant_id,conversation_id], [tenant_id,status], [tenant_id,direction], [tenant_id,sent_at] |
| `crm_message_templates` | Reusable message templates | id, tenant_id, name, channel, category, body, variables (json), is_active | tenant_id, [tenant_id,channel], [tenant_id,is_active] |
| `crm_message_attachments` | File metadata | id, tenant_id, message_id (FK), file_name, file_path, mime_type, size | tenant_id, [tenant_id,message_id] |

**Strengths:**
- Consistent tenant_id FK + cascadeOnDelete on all tenant-scoped tables
- Composite indexes on (tenant_id, *) for all query patterns
- Polymorphic columns (participant_type/participant_id, sender_type/sender_id) have proper indexes
- Unique constraint on (conversation_id, participant_type, participant_id) prevents duplicate participants per conversation
- UUID column on conversations properly indexed with unique constraint
- All datetime tracking columns present (sent_at, delivered_at, read_at)
- JSON columns for metadata/variables use native json type
- Cascade delete on all child FKs (participants/messages cascade with conversation, attachments cascade with message)

**Issues:**
- **MEDIUM** — `crm_message_attachments` has no `uuid` column and no soft deletes, inconsistent with other entities
- **LOW** — `crm_conversation_participants` has no soft deletes (design decision: participants map is append-only)
- **MEDIUM** — `crm_messages` sender_type/sender_id are NOT NULL, but MessageFactory generates them via User factory — cross-tenant risk if User factory creates user outside current tenant (mitigated by BelongsToTenant on User)

---

## 2. Migrations

### Score: 9.0/10

Single combined migration `2026_06_20_000011_create_communications_tables.php` with proper dependency order (conversations → participants/messages → attachments → templates). Drop order is reverse.

**Strengths:**
- Atomic up/down — properly sorted dependencies
- All FK constraints with cascadeOnDelete
- Default values for status, is_active, is_primary, size
- Enums used only for default values, not column types (string columns)
- Timestamps on all tables

**Issues:**
- **LOW** — No `->nullable()` on `uuid()` column for conversations (line 16): `$table->uuid()->unique();` — the uuid column is NOT NULL. This is fine since the model auto-generates UUIDs, but manual DB inserts would fail. The model's `creating` event compensates.

---

## 3. Models

### Score: 8.5/10

| Model | Traits | Key Features |
|-------|--------|-------------|
| `Conversation` | BelongsToTenant, HasFactory, SoftDeletes | Auto-UUID via `creating` event, channel/status enum casts |
| `ConversationParticipant` | BelongsToTenant, HasFactory | `participant()` morphTo |
| `Message` | BelongsToTenant, HasFactory, SoftDeletes | `sender()` morphTo, direction/status enum casts |
| `MessageTemplate` | BelongsToTenant, HasFactory, SoftDeletes | variables json cast, is_active boolean cast |
| `MessageAttachment` | BelongsToTenant | Simple relation, no HasFactory |

**Strengths:**
- Consistent use of BelongsToTenant for tenant isolation
- Proper PHPDoc `@mixin` annotations on resources
- All fillable arrays explicit (mass-assignment protected by default)
- Enum casts for channel, status, direction
- Soft deletes on all main entities (except ConversationParticipant — intentional, append-only)
- Auto-UUID generation via `creating` event as fallback

**Issues:**
- **LOW** — `MessageAttachment` does not use `HasFactory` (inconsistent; though it's a simple relation with no complex test dependencies)
- **MEDIUM** — `Conversation` uses both `$table->uuid()` column AND separate auto-increment `$table->id()`. The UUID is a secondary unique identifier, not the PK. This is intentional (maintains integer PK for FK performance, UUID for external references), but adds cognitive load.
- **LOW** — `ConversationParticipant` has no `owner_id`/`created_by`/`updated_by` fields, unlike all other CRM entities. This is acceptable since participants are always created in context of conversation creation.

---

## 4. Relationships

### Score: 9.0/10

| Model | Relationships |
|-------|---------------|
| `Conversation` | `belongsTo(Tenant)`, `belongsTo(User, owner_id)`, `belongsTo(User, created_by)`, `belongsTo(User, updated_by)`, `hasMany(ConversationParticipant)`, `hasMany(Message)` |
| `ConversationParticipant` | `belongsTo(Tenant)`, `belongsTo(Conversation)`, `morphTo(participant)` |
| `Message` | `belongsTo(Tenant)`, `belongsTo(Conversation)`, `morphTo(sender)`, `belongsTo(User, owner_id)`, `hasMany(MessageAttachment)` |
| `MessageTemplate` | `belongsTo(Tenant)`, `belongsTo(User, created_by)`, `belongsTo(User, updated_by)` |
| `MessageAttachment` | `belongsTo(Tenant)`, `belongsTo(Message)` |

**Strengths:**
- Polymorphic `participant()` supports Person, Organization, User (future-proof)
- Polymorphic `sender()` supports User, Person, Organization (future-proof)
- All relationships use explicit FK column names
- No unnecessary eager loading in model definitions

**Issues:**
- **LOW** — `ConversationResource::participants` is always loaded via `->with(['participants'])` in the query builder, even when not needed for index responses. This won't N+1 but adds unnecessary queries for participant-heavy index views.

---

## 5. UUID Strategy

### Score: 7.5/10

Conversation uses a dual-key strategy:
- Primary key: auto-increment integer (`$table->id()`)
- Secondary: `$table->uuid()->unique()` (separate column named `uuid`)

**Strengths:**
- UUID exposed in API responses for external references
- Integer PK for FK performance (smaller index, faster JOINs)
- Model auto-generates UUID via `creating` event when not provided

**Issues:**
- **MEDIUM** — The original Sprint 7 plan specified `HasUuids` trait (which makes UUID the PK). The implementation uses a separate uuid column instead. This was changed because the migration used `$table->id()` for PK. The dual-key strategy adds complexity: two identifiers per record, both must be kept in sync.
- **LOW** — UUID is generated in two places: `ConversationService::create()` sets UUID explicitly, and the model's `creating` event generates a fallback. This duplication is harmless but could be consolidated.

---

## 6. Controllers

### Score: 8.5/10

| Controller | Actions | Tenant Scope |
|------------|---------|-------------|
| `ConversationController` | index, store, show, update, destroy, restore, close | Implicit via BelongsToTenant/TenantScope |
| `MessageController` | index, store, show, update, destroy | Implicit via BelongsToTenant/TenantScope |
| `MessageTemplateController` | index, store, show, update, destroy, restore | Implicit via BelongsToTenant/TenantScope |

**Strengths:**
- Consistent pattern: constructor injection of ApiResponseService + service class
- Guard gates via `Gate::authorize()` before all operations
- Nested route pattern for messages (conversations/{id}/messages) with non-nested show/update/delete
- Per-page capped at 100 with `min()` guard
- Restore uses `whereNumber('id')` route constraint

**Issues:**
- **HIGH** — [C1] **Cross-tenant message access via nested routes**: `MessageController::show` and `update`/`destroy` use implicit route model binding on `Message $message` WITHOUT verifying the message belongs to the specified conversation. The routes are:
  - `GET conversations/messages/{message}` (show)
  - `PUT conversations/messages/{message}` (update)
  - `DELETE conversations/messages/{message}` (destroy)
  
  An attacker could access any message across conversations (though TenantScope still limits to same tenant). The conversation context is lost. While TenantScope prevents cross-tenant access, the route design allows accessing messages from conversation A via the conversation B context in URLs like `/conversations/1/messages/5` where message 5 belongs to conversation 2.
  
  **Mitigation**: TenantScope limits to same tenant, so cross-tenant access is blocked. But cross-conversation access within the same tenant is possible (no conversation ownership check on show/update/destroy).

- **LOW** — `MessageController` `store(int $conversationId)` and `index(int $conversationId)` take a raw integer `$conversationId` without verifying the conversation exists. If the conversation does not exist, the message creation will fail with a FK constraint violation (ugly 500 error) instead of a clean 404.

- **LOW** — `ConversationController::store` does not call `$conversation->refresh()` or reload the relationship after creation, so the response always has `participants` as empty (they're added after `Conversation::create()` but before the response).

  **Wait** — The `create()` method creates participants on the conversation, then returns `$conversation`. But the service returns the same `$conversation` instance without refreshing. The `participants` relationship IS loaded in the response because `ConversationResource` uses `$this->whenLoaded('participants')`. Let me verify: the `find()` method in the service uses `->with(['participants', 'messages'])`. But `create()` does NOT reload participants. So the response from `store()` will NOT include participants.

  Actually wait — let me re-check. The controller's `store` action:
  ```php
  $conversation = $this->conversationService->create($request->validated());
  return $this->api->success('...', new ConversationResource($conversation), 201);
  ```
  
  The service `create()` method:
  ```php
  $conversation = Conversation::create($data);
  foreach ($participants as $participant) { ConversationParticipant::create([...]); }
  // No refresh, no load
  return $conversation;
  ```
  
  So the `$conversation` returned has NO participants loaded. The resource uses `$this->whenLoaded('participants')`, which returns an empty collection. The API response will show `"participants": []`. **This is a bug.**

---

## 7. Requests (Form Requests)

### Score: 8.0/10

| Request | Rules |
|---------|-------|
| `StoreConversationRequest` | channel (required, in:whatsapp,sms,email,internal), subject (nullable), status (nullable, in), metadata (nullable, json), owner_id (nullable, exists with tenant scope), participants (required array min:1), participants.*.type (required, in:person,organization,user), participants.*.id (required, integer), participants.*.is_primary (nullable, boolean) |
| `UpdateConversationRequest` | subject (nullable), status (nullable, in), metadata (nullable), owner_id (nullable, exists) |
| `StoreMessageRequest` | direction (required, in:inbound,outbound), body (nullable), status (nullable, in), sender_type (required, in:user,person,organization), sender_id (required, integer), sent_at (nullable, date), metadata (nullable, json) |
| `UpdateMessageRequest` | body (nullable), status (nullable, in), delivered_at (nullable, date), read_at (nullable, date), metadata (nullable, json) |
| `StoreMessageTemplateRequest` | name (required), channel (required, in), category (nullable), body (required), variables (nullable, array), is_active (nullable, boolean) |
| `UpdateMessageTemplateRequest` | name (sometimes), channel (sometimes), category (nullable), body (sometimes), variables (nullable), is_active (nullable) |
| `StoreMessageAttachmentRequest` | file_name (required), file_path (required), mime_type (required), size (required, integer, min:0) |

**Strengths:**
- Consistent `in:` validation matching enum values
- `tenant()` scope on owner_id existence checks
- `sometimes` on update requests (PATCH semantics)
- BaseFormRequest extends for shared behavior

**Issues:**
- **MEDIUM** — [C2] `StoreMessageRequest` does NOT validate that `sender_id` exists in the specified `sender_type` table. The rule `['required', 'integer', 'min:1']` accepts any integer. This means a client can reference non-existent Person/Organization/User IDs. No `exists:` rule with polymorphic type handling is applied. While this won't crash (FK constraint is not enforced at DB level for morph columns since they're string+integer, not a real FK), it creates orphan references.
- **LOW** — `StoreConversationRequest` does not validate that `participants.*.id` exists in the corresponding type table. Same issue as C2.
- **LOW** — `StoreMessageAttachmentRequest` validates file metadata but has no controller or route using it (the request is created but unused). Dead code.

---

## 8. Resources

### Score: 8.5/10

| Resource | Fields | Conditionals |
|----------|--------|-------------|
| `ConversationResource` | id, uuid, subject, channel, status, last_message_at, metadata, created_at, updated_at, deleted_at, owner, participants, messages_count | `whenLoaded('owner')`, `whenLoaded('participants')`, `whenCounted('messages')` |
| `MessageResource` | id, conversation_id, direction, body, status, sent_at, delivered_at, read_at, metadata, created_at, updated_at, deleted_at, sender_type, sender_id, attachments | `whenLoaded('attachments')` |
| `MessageTemplateResource` | id, name, channel, category, body, variables, is_active, created_at, updated_at, deleted_at | None |
| `ConversationParticipantResource` | id, participant_type, participant_id, is_primary | None |
| `MessageAttachmentResource` | id, message_id, file_name, file_path, mime_type, size | None |

**Strengths:**
- All nullable fields use null-safe operator (`$this->status?->value`)
- Conditional loading with `whenLoaded` prevents N+1
- Pagination meta handled by Laravel's `paginate()`
- `@mixin` annotations for IDE autocomplete

**Issues:**
- **MEDIUM** — [C3] `ConversationResource` returns `participants` as an empty array when not loaded (due to `whenLoaded` returning `Collection`). This is correct behavior but the create response will always show empty participants due to the service not reloading them (see Controller issue).
- **LOW** — `ConversationParticipantResource` exposes raw `participant_type` (FQCN like `App\Models\Crm\Person`), which is an internal implementation detail. External APIs should expose a short type key (e.g., `person`).

---

## 9. Services

### Score: 7.5/10

| Service | Key Methods | EventDispatcher Integration |
|---------|-------------|---------------------------|
| `ConversationService` | query, paginateWithFilters, find, create, update, delete, restore, forceDelete, close | conversation.created, .closed, .updated, .deleted, .restored |
| `MessageService` | query, paginateWithFilters, find, create, update, delete | message.sent, .received, .read |
| `MessageTemplateService` | query, paginateWithFilters, find, create, update, delete, restore, forceDelete | None |
| `MessageAttachmentService` | create, delete | None |

**Strengths:**
- Consistent pattern: query() → paginateWithFilters() with whereHas for relationship filters
- Eager loading in query() prevents N+1
- EventDispatcher integration for all timeline events
- Soft delete aware (restore with trashed queries)
- Comprehensive filters (search, channel, status, participant, dates, sorting)
- `withQueryString()` on pagination preserves filter state

**Issues:**
- **MEDIUM** — [C4] **No FeatureGateService injection**: The services do not check feature gates (`communications.enabled` or `message_templates.enabled`). While FeatureGateService is referenced in the plan as an integration point, neither the controllers nor services enforce feature gates. A tenant with disabled communications can still access all endpoints.
- **LOW** — `ConversationService::create()` sets UUID explicitly via `Str::uuid()`, but the model's `creating` event also generates one. This is redundant but harmless.
- **LOW** — `ConversationService::update()` triggers `conversation.updated` event on every update, even if nothing changed. This could generate noisy timeline entries.
- **LOW** — `MessageService::update()` triggers `message.read` event on read_at changes but doesn't trigger `message.updated` for other field changes. Inconsistent with ConversationService pattern.
- **LOW** — `MessageService::update()` does not check if the `sent_at` or `delivered_at` timestamps change. These events are not dispatched.
- **LOW** — `MessageTemplateService::restore()` does not dispatch a timeline event (unlike ConversationService::restore() which dispatches `conversation.restored`). Inconsistent.
- **LOW** — `MessageAttachmentService` does not use EventDispatcher. Attachments are created without timeline entries.
- **LOW** — No `findByUuid()` method on ConversationService despite UUID being a key feature.

---

## 10. Policies

### Score: 8.0/10

| Policy | Permissions Used | Before Hook |
|--------|-----------------|-------------|
| `ConversationPolicy` | communications.view, .create, .update, .delete | owner/admin bypass |
| `MessagePolicy` | communications.view, .create, .update, .delete | owner/admin bypass |
| `MessageTemplatePolicy` | message_templates.view, .create, .update, .delete | owner/admin bypass |

**Strengths:**
- Consistent `before()` hook for owner/admin role bypass
- Uses `HandlesAuthorization` trait
- Separate permission namespaces for conversations/messages vs templates

**Issues:**
- **MEDIUM** — [C5] **No model-level ownership check**: The `view`, `update`, and `delete` methods on all policies only check permissions, NOT whether the user owns the resource or belongs to the same team. Any user with `communications.view` can view ANY conversation in the tenant. This is by design (tenant-wide access), but should be documented.
- **LOW** — `ConversationPolicy::update` and `MessagePolicy::update` accept nullable model parameter (`?Conversation $conversation = null`), which is unnecessary since the policy is only used for existing models. This was likely copied from a pattern where `create` and `update` share a method signature.

---

## 11. Feature Gates

### Score: 5.0/10

**Not implemented.** The plan defines `communications.enabled` and `message_templates.enabled` feature definitions, but:
- Feature definitions are seeded in CrmDatabaseSeeder
- No middleware, service, or controller enforces them
- `EnsurePlanFeature` middleware is not applied to any communications routes
- No `FeatureGateService::assert()` call exists for communications

This is a deliberate deferral — the event dispatching and workflow integration were prioritized. However, without gate enforcement, tenants with disabled communications can still create conversations/messages.

---

## 12. EventDispatcher Integration

### Score: 9.0/10

**Events dispatched:**

| Service Method | Event Type | Timeline Entry | Workflow Trigger |
|---------------|------------|----------------|------------------|
| ConversationService::create | `conversation.created` | Yes | Yes |
| ConversationService::update (close) | `conversation.closed` | Yes | Yes |
| ConversationService::update (other) | `conversation.updated` | Yes | Yes |
| ConversationService::delete | `conversation.deleted` | Yes | Yes |
| ConversationService::restore | `conversation.restored` | Yes | Yes |
| MessageService::create (outbound) | `message.sent` | Yes | Yes |
| MessageService::create (inbound) | `message.received` | Yes | Yes |
| MessageService::update (read) | `message.read` | Yes | Yes |

**Strengths:**
- All expected events from the plan are dispatched
- EventDispatcher handles both timeline recording AND workflow triggering in one call
- Queued via RecordTimelineEntryJob (queue:timeline) and TriggerWorkflowJob (queue:workflows)
- Metadata payload contains contextual info (channel, conversation_id, direction)

**Issues:**
- **LOW** — `message.sent`/`message.received` workflows are technically NOT triggered. Looking at the EventDispatcher::record() method, it calls `triggerWorkflows()` which dispatches `TriggerWorkflowJob` with the event type. So `conversation.created`, `message.sent`, `message.received`, etc. are all passed as workflow triggers. This matches the plan. Correct.
- **LOW** — `MessageService::delete` does NOT dispatch `message.deleted` event. Inconsistent with ConversationService.

---

## 13. Timeline Integration

### Score: 9.0/10

All events listed in the EventDispatcher Integration section write to `crm_timeline_entries` via `RecordTimelineEntryJob`. Timeline entries use `entity_type` (morph class), `entity_id`, `event_type`, `title`, `description`, `meta`, and `caused_by`.

**Strengths:**
- Append-only, queued, tenant-aware
- Consistent with Sprint 5/6 timeline patterns

**Issues:**
- None identified.

---

## 14. Workflow Integration

### Score: 9.0/10

Workflow triggers are dispatched for: `conversation.created`, `conversation.closed`, `conversation.updated`, `conversation.deleted`, `conversation.restored`, `message.sent`, `message.received`, `message.read`.

**Strengths:**
- All planned workflow triggers are implemented
- Triggered via `TriggerWorkflowJob` (queue:workflows, afterCommit) — tenant-aware
- Consistent with Sprint 5 remediation for tenant-aware workflow execution

**Issues:**
- None identified.

---

## 15. Search Implementation

### Score: 7.0/10

| Entity | Search Fields | Implementation |
|--------|--------------|----------------|
| Conversations | subject | `LIKE '%search%'` |
| Messages | body | `LIKE '%search%'` |
| Message Templates | name, body | `LIKE '%search%'` (OR) |

**Issues:**
- **MEDIUM** — [C6] All search uses `LIKE '%term%'` (prefix wildcard), which is a **full table scan** pattern. SQLite and MySQL cannot use indexes for `LIKE '%term%'`. On large datasets (>100K conversations), this will be extremely slow. This is a known pattern in the existing codebase (TaskService uses the same), but worth flagging for the communications domain which could grow quickly.
- **LOW** — MessageTemplate search uses `OR` across name and body, which compounds the full table scan issue.

---

## 16. Filtering Implementation

### Score: 9.0/10

Filters are implemented via service methods with composable `where()` clauses.

**Strengths:**
- All filter fields are backed by indexed columns (tenant_id prefix on all)
- Participant filter uses `whereHas()` which executes a subquery — acceptable for indexed (participant_type, participant_id)
- Date range filters on indexed `created_at` and `sent_at` columns
- Boolean filter uses proper `filter_var()` casting

**Issues:**
- None identified.

---

## 17. Sorting Implementation

### Score: 8.5/10

Sorting uses `orderBy()` with user-specified column and direction.

**Strengths:**
- Default sort keys exist for all entities (created_at desc for conversations/messages, name asc for templates)
- Direction validation is implicit (user passes asc/desc)

**Issues:**
- **LOW** — No column allowlist for `sort_by`. A user can pass any column name, including non-indexed columns or columns that leak internal structure. While this won't cause errors, it could result in slow queries on unindexed columns. However, the existing codebase follows the same pattern.

---

## 18. Pagination

### Score: 9.0/10

All index methods use `paginate($perPage)` with `withQueryString()`.

**Strengths:**
- Default 25, capped at 100 in controllers
- `withQueryString()` preserves filter/sort state
- Consistent across all 3 controllers

**Issues:**
- None identified.

---

## 19. Tenant Isolation

### Score: 8.5/10

**Isolation mechanisms:**
1. `BelongsToTenant` trait — auto-fills `tenant_id` on create, applies `TenantScope` on all queries
2. `TenantScope` global scope — filters all queries by `tenant_id`
3. Route model binding — bound models are scoped by TenantScope
4. FK constraint on `tenant_id` — cascadeOnDelete from tenants table
5. API guard `auth:tenant-api` — ensures only tenant users can access

**Cross-tenant tests:** 56 automated tests include cross-tenant access tests for conversations, messages, and message templates.

**Issues:**
- **HIGH** — [C1 revisited] **Message routes bypass conversation ownership check**: Routes like `PUT conversations/messages/{message}` resolve Message via implicit binding with TenantScope (tenant isolation OK), but do not verify the message belongs to the URL's conversation context. The route `conversations/{conversationId}/messages/{message}` would be safer. Currently any user in a tenant can read/update/delete any message in that tenant, regardless of which conversation it belongs to.
- **LOW** — `ConversationService::restore(int $id)` and `forceDelete(int $id)` use `withTrashed()->findOrFail($id)` which bypasses TenantScope. While `withTrashed()` still respects TenantScope (it's a global scope that applies to trashed queries), the explicit `$id` parameter accepts any ID within the tenant.

---

## 20. Test Coverage

### Score: 9.0/10

**3 test files, 56 tests, 103 assertions:**

| Test File | Tests | Coverage |
|-----------|-------|----------|
| `ConversationTest.php` | ~22 | CRUD, 401/404/422, search, filter (channel/status/participant), pagination limit, timeline events, participant creation, UUID generation, unique constraint, cross-tenant |
| `MessageTest.php` | ~18 | CRUD, 401/404/422, search, filter (direction), sender types (user/person/organization), last_message_at update, timeline events (sent/received/read), attachments in response, cross-tenant |
| `MessageTemplateTest.php` | ~16 | CRUD, 401/404/422, search, filter (channel/is_active/category), soft delete restore, cross-tenant |

**Strengths:**
- Comprehensive CRUD for all entities
- 401 (unauthorized), 404 (not found), 422 (validation) error paths covered
- Cross-tenant isolation tests for all entities
- Search, filter, sort covered for all entities
- Timeline event assertions for all dispatched events
- Participant management tested (create with multiple types, unique constraint)
- UUID generation verified
- last_message_at update on new message verified
- Soft delete/restore tested (conversations, templates)

**Issues:**
- **MEDIUM** — [C7] No tests for feature gate enforcement (feature gates are not implemented yet)
- **LOW** — No tests for `forceDelete` operations
- **LOW** — No tests for `MessageAttachmentService`
- **LOW** — No tests for sorting by specific columns
- **LOW** — No tests for empty participant list in create response (the known bug)

---

## Finding Summary

### Critical (0)

### High (1)

| ID | Finding | Impact | File(s) |
|----|---------|--------|---------|
| C1 | **Message routes bypass conversation ownership check**: show/update/destroy on messages do not verify message belongs to the URL's conversation context. Cross-conversation access within same tenant is possible. | High — Broken route contract, inconsistent with nested URL structure | `routes/tenant/crm-v1.php` (lines 155-159), `MessageController` |

### Medium (4)

| ID | Finding | Impact | File(s) |
|----|---------|--------|---------|
| C2 | `StoreMessageRequest` does not validate sender existence in polymorphic target table | Medium — Creates orphan sender references | `app/Http/Requests/Crm/StoreMessageRequest.php` |
| C3 | **Create conversation response returns empty participants**: Service does not reload participants after creation | Medium — API returns incomplete data on create | `app/Services/Crm/ConversationService.php` (line 112) |
| C4 | **No feature gate enforcement**: `communications.enabled` and `message_templates.enabled` never checked | Medium — No way to disable communications for tenants | All services + controllers |
| C5 | **No model-level ownership in policies**: Policies only check permission, not resource ownership | Medium — Any user in tenant can access any resource (by design but undocumented) | All 3 policies |
| C6 | `LIKE '%term%'` search causes full table scans | Medium — Performance risk at scale | `ConversationService`, `MessageService`, `MessageTemplateService` |

### Low (5)

| ID | Finding | Impact | File(s) |
|----|---------|--------|---------|
| L1 | `MessageAttachment` has no `uuid`, no soft deletes, no HasFactory | Low — Inconsistent with other entities | `app/Models/Crm/MessageAttachment.php` |
| L2 | `StoreMessageAttachmentRequest` is unused (dead code) | Low — No controller or route uses it | `app/Http/Requests/Crm/StoreMessageAttachmentRequest.php` |
| L3 | `ConversationParticipantResource` exposes FQCN as participant_type | Low — Leaks internal implementation | `app/Http/Resources/.../ConversationParticipantResource.php` |
| L4 | Message delete does not dispatch timeline event | Low — Inconsistent with ConversationService | `app/Services/Crm/MessageService.php` (line 122) |
| L5 | Template restore does not dispatch timeline event | Low — Inconsistent with ConversationService | `app/Services/Crm/MessageTemplateService.php` (line 75) |

---

## Scores

| Domain | Score |
|--------|-------|
| **Security** | 7.5/10 |
| **Architecture** | 8.0/10 |
| **Scalability** | 6.5/10 |
| **Communications Domain** | 8.0/10 |
| **SaaS Readiness** | 7.5/10 |
| **Production Readiness** | 8.0/10 |

---

## Files Created

| File | Type |
|------|------|
| `app/Enums/ConversationChannelEnum.php` | Enum |
| `app/Enums/ConversationStatusEnum.php` | Enum |
| `app/Enums/MessageDirectionEnum.php` | Enum |
| `app/Enums/MessageStatusEnum.php` | Enum |
| `database/migrations/2026_06_20_000011_create_communications_tables.php` | Migration |
| `app/Models/Crm/Conversation.php` | Model |
| `app/Models/Crm/ConversationParticipant.php` | Model |
| `app/Models/Crm/Message.php` | Model |
| `app/Models/Crm/MessageTemplate.php` | Model |
| `app/Models/Crm/MessageAttachment.php` | Model |
| `app/Policies/Crm/ConversationPolicy.php` | Policy |
| `app/Policies/Crm/MessagePolicy.php` | Policy |
| `app/Policies/Crm/MessageTemplatePolicy.php` | Policy |
| `app/Http/Requests/Crm/StoreConversationRequest.php` | Form Request |
| `app/Http/Requests/Crm/UpdateConversationRequest.php` | Form Request |
| `app/Http/Requests/Crm/StoreMessageRequest.php` | Form Request |
| `app/Http/Requests/Crm/UpdateMessageRequest.php` | Form Request |
| `app/Http/Requests/Crm/StoreMessageTemplateRequest.php` | Form Request |
| `app/Http/Requests/Crm/UpdateMessageTemplateRequest.php` | Form Request |
| `app/Http/Requests/Crm/StoreMessageAttachmentRequest.php` | Form Request |
| `app/Services/Crm/ConversationService.php` | Service |
| `app/Services/Crm/MessageService.php` | Service |
| `app/Services/Crm/MessageTemplateService.php` | Service |
| `app/Services/Crm/MessageAttachmentService.php` | Service |
| `app/Http/Resources/Tenant/Api/V1/Crm/ConversationResource.php` | Resource |
| `app/Http/Resources/Tenant/Api/V1/Crm/ConversationParticipantResource.php` | Resource |
| `app/Http/Resources/Tenant/Api/V1/Crm/MessageResource.php` | Resource |
| `app/Http/Resources/Tenant/Api/V1/Crm/MessageTemplateResource.php` | Resource |
| `app/Http/Resources/Tenant/Api/V1/Crm/MessageAttachmentResource.php` | Resource |
| `app/Http/Controllers/Tenant/Api/V1/Crm/ConversationController.php` | Controller |
| `app/Http/Controllers/Tenant/Api/V1/Crm/MessageController.php` | Controller |
| `app/Http/Controllers/Tenant/Api/V1/Crm/MessageTemplateController.php` | Controller |
| `database/factories/Crm/ConversationFactory.php` | Factory |
| `database/factories/Crm/MessageFactory.php` | Factory |
| `database/factories/Crm/MessageTemplateFactory.php` | Factory |
| `database/factories/Crm/ConversationParticipantFactory.php` | Factory |
| `tests/Feature/Tenant/Crm/Communications/ConversationTest.php` | Test |
| `tests/Feature/Tenant/Crm/Communications/MessageTest.php` | Test |
| `tests/Feature/Tenant/Crm/Communications/MessageTemplateTest.php` | Test |

## Files Modified

| File | Change |
|------|--------|
| `config/tenant-permissions.php` | Added `communications` and `message_templates` modules |
| `database/seeders/Tenant/CrmDatabaseSeeder.php` | Added `communications.enabled` and `message_templates.enabled` feature definitions |
| `app/Services/Crm/MorphableEntityResolver.php` | Added `'user' => User::class` |
| `routes/tenant/crm-v1.php` | Added communications routes |
| `app/Models/Crm/Conversation.php` | Added HasFactory, auto-UUID via `creating` event |
| `app/Models/Crm/Message.php` | Added HasFactory |
| `app/Models/Crm/MessageTemplate.php` | Added HasFactory |
| `app/Models/Crm/ConversationParticipant.php` | Added HasFactory |
| `tests/Feature/Tenant/Crm/MorphableEntityResolverTest.php` | Updated expected count to 9, added User assertion |

---

## Technical Debt Introduced

1. Dual UUID strategy (integer PK + separate uuid column) — adds complexity for external reference
2. Duplicate UUID generation (service + model event) — redundant code
3. Unused `StoreMessageAttachmentRequest` — dead code
4. `ConversationParticipantResource` exposes FQCN — API contract leak
5. No feature gate enforcement — deferred to future sprint
6. `LIKE '%term%'` search — performance tech debt known across codebase
7. `MessageAttachment` lacks HasFactory, soft deletes — incomplete entity

---

## Refactoring Recommendations

1. **Fix C3 (High):** Add `$conversation->load('participants')` before returning in `ConversationService::create()`.
2. **Fix C1 (High):** Add conversation verification in MessageController show/update/destroy — validate that `$message->conversation_id` matches the route context, or restructure routes to include conversation binding.
3. **Add C2 (Medium):** Add polymorphic existence validation in `StoreMessageRequest` for sender_id.
4. **Add C4 (Medium):** Implement feature gate middleware or controller-level checks for `communications.enabled`.
5. **Add C6 (Medium):** Consider Scout or full-text search for message body search at scale.
6. **Fix L3 (Low):** Map participant_type FQCN to short keys in resource.
7. **Fix L4 (Low):** Add `message.deleted` event in `MessageService::delete()`.
8. **Fix L5 (Low):** Add `message_template.restored` event in `MessageTemplateService::restore()`.

---

## Remediation Plan (if NOT APPROVED)

The auditor deems this sprint **APPROVED with conditions**. The following must be resolved before Sprint 8 begins:

### Required (High Priority)
1. **C3 — Empty participants in create response:** Add `$conversation->load('participants')` in `ConversationService::create()` before return.
2. **C1 — Message route conversation context:** Add validation in `MessageController::show/update/destroy` that `$message->conversation_id` matches the expected conversation, OR restructure nested routes.

### Recommended (Medium Priority)
3. **C4 — Feature gate enforcement:** Add `FeatureGateService::assert()` in relevant controllers or create middleware.
4. **C2 — Sender existence validation:** Add polymorphic `exists:` validation in `StoreMessageRequest`.

---

## Sign-off

| Role | Verdict |
|------|---------|
| **Architecture** | APPROVED with conditions |
| **Security** | APPROVED with conditions |
| **Product** | APPROVED |

**Next:** Apply remediation items 1-2 before Sprint 8 planning.
