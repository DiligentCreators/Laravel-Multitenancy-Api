# FollowKa Domain Architecture Map v1

> **Date:** 2026-06-20  
> **Version:** 1.0  
> **Status:** Final (Post-Sprint 4)  
> **Scope:** Central Admin + CRM Sprints 1-4

---

## Table of Contents

1. [Domain Boundaries](#1-domain-boundaries)
2. [Aggregate Roots](#2-aggregate-roots)
3. [Entity Ownership Rules](#3-entity-ownership-rules)
4. [Module Dependency Graph](#4-module-dependency-graph)
5. [Event Flow Diagram](#5-event-flow-diagram)
6. [Timeline Integration Points](#6-timeline-integration-points)
7. [Workflow Integration Points](#7-workflow-integration-points)
8. [Future Client Portal Dependencies](#8-future-client-portal-dependencies)
9. [Future Mobile App Dependencies](#9-future-mobile-app-dependencies)
10. [Solar Module Dependencies](#10-solar-module-dependencies)
11. [Agency Module Dependencies](#11-agency-module-dependencies)
12. [Real Estate Module Dependencies](#12-real-estate-module-dependencies)
13. [Cross-Cutting Analysis](#13-cross-cutting-analysis)
14. [Architecture Score](#14-architecture-score)

---

## 1. Domain Boundaries

### 1.1 Domain Map

```
┌────────────────────────────────────────────────────────────────────────┐
│                        FOLLOWKA PLATFORM                               │
│                                                                        │
│  ┌─────────────────────────┐  ┌────────────────────────────────────┐  │
│  │    CENTRAL ADMIN        │  │         TENANT (CRM)               │  │
│  │    (Single Instance)    │  │    (Per-Tenant Isolated)           │  │
│  │                         │  │                                    │  │
│  │  ┌───────────────────┐  │  │  ┌──────┐ ┌──────┐ ┌───────────┐  │  │
│  │  │ Billing & Plans   │  │  │  │People│ │Orgs  │ │Addresses  │  │  │
│  │  │  - Plan           │──┼──┼──┤      │ │      │ │           │  │  │
│  │  │  - Subscription   │  │  │  └──┬───┘ └──┬───┘ └───────────┘  │  │
│  │  │  - Feature        │  │  │     │        │                    │  │
│  │  │  - UsageCounter   │  │  │     └──┬──────┘                   │  │
│  │  └───────────────────┘  │  │        │                          │  │
│  │  ┌───────────────────┐  │  │  ┌─────▼──────┐                   │  │
│  │  │ Tenant Management │  │  │  │ Org-Person │                   │  │
│  │  │  - Tenant         │──┼──┼──┤  (Pivot)   │                   │  │
│  │  │  - Domain         │  │  │  └────────────┘                   │  │
│  │  └───────────────────┘  │  │                                    │  │
│  │  ┌───────────────────┐  │  │  ┌──────┐ ┌──────────┐           │  │
│  │  │ Central Auth      │  │  │  │Leads │ │Pipelines │           │  │
│  │  │  - CentralUser    │  │  │  │      │ │  Stages  │           │  │
│  │  └───────────────────┘  │  │  └──┬───┘ └──┬───────┘           │  │
│  │  ┌───────────────────┐  │  │     │        │                   │  │
│  │  │ Support & Config  │  │  │     └───┬────┘                   │  │
│  │  │  - Ticket         │  │  │         │                         │  │
│  │  │  - Setting        │  │  │  ┌──────▼──────┐                 │  │
│  │  │  - Template       │  │  │  │StageMovement│                 │  │
│  │  │  - Announcement   │  │  │  │  (Action)   │                 │  │
│  │  └───────────────────┘  │  │  └─────────────┘                 │  │
│  │  ┌───────────────────┐  │  │                                    │  │
│  │  │ Billing Extras    │  │  │  ┌──────────┐ ┌──────┐ ┌──────┐  │  │
│  │  │  - Invoice        │  │  │  │Activities│ │Notes │ │Comments│  │  │
│  │  │  - Payment        │  │  │  │          │ │      │ │        │  │  │
│  │  │  - Coupon         │  │  │  └────┬─────┘ └──┬───┘ └───┬────┘  │  │
│  │  │  - TaxRegion      │  │  │       │          │         │       │  │
│  │  │  - TaxRate        │  │  │       └─────┬────┘         │       │  │
│  │  └───────────────────┘  │  │             │              │       │  │
│  │  ┌───────────────────┐  │  │  ┌──────────▼──────────────▼───┐   │  │
│  │  │ Module System     │  │  │  │       TimelineWriter       │   │  │
│  │  │  - Module         │  │  │  │  (Centralized Event Log)   │   │  │
│  │  └───────────────────┘  │  │  └────────────────────────────┘   │  │
│  └─────────────────────────┘  │                                    │  │
│                                │  ┌─────────┐ ┌────────────┐      │  │
│  ┌─────────────────────────┐  │  │  Tags   │ │  Sources   │      │  │
│  │    CROSS-CUTTING        │  │  │         │ │            │      │  │
│  │                         │  │  └─────────┘ └────────────┘      │  │
│  │  ┌───────────────────┐  │  │  ┌─────────┐ ┌────────────┐      │  │
│  │  │ Spatie Permissions│──┼──┼──┤ Statuses│ │CustomFields│      │  │
│  │  │  - Roles          │  │  │  └─────────┘ └────────────┘      │  │
│  │  │  - Permissions    │  │  │  ┌─────────┐ ┌────────────┐      │  │
│  │  └───────────────────┘  │  │  │Workflows│ │ Features   │      │  │
│  │  ┌───────────────────┐  │  │  └─────────┘ └────────────┘      │  │
│  │  │ ApiResponseService│  │  │                                    │  │
│  │  │ (Shared Contract) │  │  └────────────────────────────────────┘  │
│  │  └───────────────────┘  │                                          │
│  └─────────────────────────┘                                          │
└────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Central Admin Domain (Single-Instance)

| Subdomain | Responsibility | Models |
|-----------|---------------|--------|
| **Billing & Plans** | Plan management, subscription lifecycle, feature gating, usage tracking | Plan, PlanFeature, Feature, Subscription, UsageCounter, OverageCharge, ProrationRecord |
| **Tenant Management** | Tenant provisioning, domain management, tenant lifecycle | Tenant, Domain, TenantSetting |
| **Central Auth** | Central admin authentication, authorization | CentralUser, Central\Role, Central\Permission |
| **Invoicing** | Invoice generation, payment collection, dunning | Invoice, Payment, Coupon |
| **Tax** | Tax region/rate configuration | TaxRegion, TaxRate |
| **Support** | Central support tickets, announcements | Ticket, TicketReply, Announcement |
| **Configuration** | System settings, templates, module management | Setting, SettingGroup, SettingDefinition, EmailTemplate, SmsTemplate, NotificationTemplate, Module, ApiKey |
| **Audit** | Admin activity logging, tenant exports | AdminAuditLog, TenantExportRecord |

### 1.3 Tenant CRM Domain (Multi-Tenant Isolated)

| Subdomain | Responsibility | Models |
|-----------|---------------|--------|
| **People** | Contact management | Person |
| **Organizations** | Company/account management | Organization |
| **Org-People** | Many-to-many relationships with role/time context | OrganizationPerson |
| **Addresses** | Polymorphic address storage | Address |
| **Leads** | Sales pipeline management | Lead, LeadStageMovement |
| **Pipelines** | Pipeline and stage configuration | Pipeline, PipelineStage |
| **Activities** | Task, call, meeting, email logging with polymorphic attachment | Activity |
| **Notes** | Rich-text note taking with pinning | Note |
| **Comments** | Threaded discussions with polymorphic attachment | Comment |
| **Timeline** | Append-only event log for all entities | TimelineEntry, TimelineWriter |
| **Tags** | Polymorphic tagging system | Tag, Taggable |
| **Statuses** | Entity-specific status workflows | Status, StatusType |
| **Sources** | Lead/contact source tracking | Source |
| **Custom Fields** | Dynamic field definitions per entity type | CustomFieldDefinition |
| **Features** | CRM-specific feature definitions and tenant overrides | FeatureDefinition, PlanFeature, TenantFeatureOverride, UsageCounter (CRM) |
| **Workflows** | Automated trigger-action rules | WorkflowDefinition, WorkflowLog |

---

## 2. Aggregate Roots

An **Aggregate Root** is the top-level entity that guarantees consistency of a cluster of related objects. External references must always go through the root.

### 2.1 Aggregate Root Map

```
┌────────────────────────────────────────────────────────────────────┐
│ AGGREGATE ROOT  │ OWNED ENTITIES              │ INVARIANTS        │
├────────────────────────────────────────────────────────────────────┤
│ Tenant           │ Domain, TenantSetting,      │ Has at least one  │
│                  │ User, Subscription          │ active domain     │
│                  │                             │ Has one active    │
│                  │                             │ subscription      │
├────────────────────────────────────────────────────────────────────┤
│ Plan             │ PlanFeature (pivot),        │ Slug is unique    │
│                  │ Subscription (ref)          │ Price >= 0        │
├────────────────────────────────────────────────────────────────────┤
│ Subscription     │ Invoice (ref), Payment      │ Cannot overlap    │
│                  │ (ref), ProrationRecord,      │ with active subs  │
│                  │ OverageCharge               │ Status transitions│
│                  │                             │ are controlled    │
├────────────────────────────────────────────────────────────────────┤
│ Person           │ Address (via addressable),  │ Must have at      │
│                  │ Tag (via taggable),          │ least first/last  │
│                  │ OrganizationPerson (ref),    │ name              │
│                  │ Lead (ref), Activity (ref),  │                   │
│                  │ Note (ref), Comment (ref),   │                   │
│                  │ TimelineEntry (ref)          │                   │
├────────────────────────────────────────────────────────────────────┤
│ Organization     │ Address, Tag,               │ Name is required  │
│                  │ OrganizationPerson, Lead,    │                   │
│                  │ Activity, Note, Comment,     │                   │
│                  │ TimelineEntry               │                   │
├────────────────────────────────────────────────────────────────────┤
│ Lead             │ PipelineStage (ref),         │ Stage must belong │
│                  │ TimelineEntry, Activity,     │ to assigned       │
│                  │ Note, Comment, Tag           │ pipeline          │
│                  │                             │ Won/lost are      │
│                  │                             │ mutually exclusive │
├────────────────────────────────────────────────────────────────────┤
│ Pipeline         │ PipelineStage (ordered)      │ Must have at least│
│                  │ Lead (ref)                   │ one stage         │
│                  │                             │ Default flag is   │
│                  │                             │ unique per tenant │
├────────────────────────────────────────────────────────────────────┤
│ Activity         │ TimelineEntry (ref)          │ Type must be in   │
│                  │                             │ allowed types     │
├────────────────────────────────────────────────────────────────────┤
│ Comment          │ Comment (reply chain)        │ parent_id must    │
│                  │ TimelineEntry (ref)          │ exist in same     │
│                  │                             │ tenant            │
├────────────────────────────────────────────────────────────────────┤
│ Workflow         │ WorkflowLog                  │ Actions must have │
│  Definition      │                             │ valid types       │
└────────────────────────────────────────────────────────────────────┘
```

### 2.2 Key Observations

- **TimelineEntry is NOT an aggregate root** — it is a child of any entity that has timeline events. It is append-only and never modified.
- **Address/Note/Comment** are not aggregate roots — they belong to a parent entity via polymorphic relationship.
- **Tenant** is the top-level CRM aggregate root — all CRM entities are scoped to a tenant.
- **OrganizationPerson** is a pure pivot — it has no standalone identity and must always be accessed through Organization or Person.

---

## 3. Entity Ownership Rules

### 3.1 Ownership Hierarchy

```
Tenant (Root Owner)
├── User (Tenant User, Spatie Roles & Permissions)
├── Person ──────┐
│                │
├── Organization ┤
│                │
├── Address ─────┘  (polymorphic → Person, Organization)
│
├── Lead ──────────┬── Pipeline
│                  └── PipelineStage
│
├── Activity ──────┐  (polymorphic → Person, Organization, Lead)
├── Note ──────────┤  (polymorphic → Person, Organization, Lead)
├── Comment ───────┘  (polymorphic → Person, Organization, Lead)
│
├── TimelineEntry ─── (polymorphic → ALL entities)
│
├── Tag ───────────── (polymorphic → Person, Organization, Lead, etc.)
├── Status ────────── (via StatusType, typed by entity_type)
├── Source
├── CustomFieldDefinition
│
├── Pipeline ──────── HasMany PipelineStage
│
├── WorkflowDefinition
│
└── FeatureDefinition (shared, not tenant-scoped)
    ├── PlanFeature (bound to Plan, not tenant)
    └── TenantFeatureOverride (overrides per tenant)
```

### 3.2 Ownership Principles

| Principle | Description |
|-----------|-------------|
| **Tenant Isolation** | Every CRM model has `tenant_id` FK + `BelongsToTenant` trait + `TenantScope` global scope. Cross-tenant access always returns 404. |
| **User Ownership** | Entities have `owner_id` (nullable, BelongsTo User). Owner/admin roles bypass permission checks via `before()` gate. |
| **Audit Trail** | All models have `created_by`/`updated_by` nullable FK to User. Timestamped for full auditability. |
| **Soft Deletes** | Applied to: Person, Organization, Lead, Activity, Note, Comment. NOT applied to: Address, Pipeline, PipelineStage, TimelineEntry, Tag, Status, Source, CustomFieldDefinition, WorkflowDefinition. |
| **Polymorphic Ownership** | Address, Activity, Note, Comment, TimelineEntry, Tag are polymorphic — they can attach to any entity type without schema changes. |
| **Immutable Timeline** | TimelineEntry is append-only (create/update/delete return false in policy). Only `TimelineWriter` creates entries. |

---

## 4. Module Dependency Graph

### 4.1 Directed Dependency Graph

```
Legend:
  ──→  = Direct dependency (imports/injects)
  - - → = Weak dependency (polymorphic or event-based)
  ⇄     = Circular dependency (flagged)

                    ┌──────────────────────┐
                    │  ApiResponseService  │
                    └──────────┬───────────┘
                               │ (shared by all controllers)
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                        CONTROLLER LAYER                         │
│  Each controller depends on: ApiResponseService + 1 Service     │
│  ActivityController → ActivityService + TimelineWriter          │
│  NoteController    → NoteService + TimelineWriter               │
│  CommentController → CommentService + TimelineWriter            │
│  LeadController    → LeadService + MoveLeadStageAction          │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                        SERVICE LAYER                            │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │ PersonService│    │  OrgService  │    │  LeadService │      │
│  └──────────────┘    └──────────────┘    └──────┬───────┘      │
│                                                  │              │
│  ┌──────────────┐    ┌──────────────┐             │              │
│  │ ActivitySvc  │    │  NoteService │             │              │
│  └──────┬───────┘    └──────┬───────┘             │              │
│         │                   │                     │              │
│         └───────────┬───────┘                     │              │
│                     │                             │              │
│            ┌────────▼────────┐                    │              │
│            │   CommentSvc    │                    │              │
│            └────────┬────────┘                    │              │
│                     │                             │              │
│            ┌────────▼────────┐                    │              │
│            │  TimelineWriter◄├────────────────────┘              │
│            │  (writes)       │                                   │
│            └────────┬────────┘                                   │
│                     │                                            │
│            ┌────────▼────────┐                                   │
│            │ TimelineSvc     │                                   │
│            │  (reads)        │                                   │
│            └─────────────────┘                                   │
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │  TagService  │    │ StatusService│    │  SourceSvc   │      │
│  └──────┬───────┘    └──────────────┘    └──────────────┘      │
│         │                                                       │
│  ┌──────────────┐    ┌──────────────────┐                      │
│  │  CustomField │    │ WorkflowSvc      │──────┐               │
│  │  Service     │    │  (triggers)      │      │               │
│  └──────────────┘    └──────────────────┘      │               │
│                                                │               │
│  ┌──────────────┐    ┌──────────────────┐      │               │
│  │ FeatureGate  │◄───│ WorkflowSvc      │      │               │
│  │  Service     │    │  (checks gate)   │      │               │
│  └──────┬───────┘    └──────────────────┘      │               │
│         │                                      │               │
│         ▼                                      ▼               │
│  ┌──────────────────────────────────────────────────┐          │
│  │              MODEL LAYER (Eloquent)              │          │
│  │  All models → BelongsToTenant | SoftDeletes      │          │
│  └──────────────────────────────────────────────────┘          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   CROSS-CUTTING INFRASTRUCTURE                  │
│                                                                 │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐    │
│  │ Spatie Roles & │  │ Activity Log   │  │    Medialib    │    │
│  │  Permissions   │  │ (Spatie)       │  │                │    │
│  └────────────────┘  └────────────────┘  └────────────────┘    │
│  ┌────────────────┐  ┌────────────────┐                        │
│  │ stancl/tenancy │  │  TenantScope   │                        │
│  │  (multi-tenant)│  │  (global scope)│                        │
│  └────────────────┘  └────────────────┘                        │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Service Dependency Table

| Service | Depends On | Used By |
|---------|-----------|---------|
| PersonService | Person, Tag (via tag_ids) | PersonController |
| OrganizationService | Organization, Tag | OrganizationController |
| OrganizationPersonService | Organization, Person | OrganizationPersonController |
| AddressService | Address | AddressController |
| LeadService | Lead, Person, Organization, Pipeline, PipelineStage, Tag | LeadController |
| PipelineService | Pipeline | PipelineController |
| PipelineStageService | Pipeline | PipelineStageController |
| ActivityService | Activity | ActivityController |
| NoteService | Note | NoteController |
| CommentService | Comment | CommentController |
| TimelineService | TimelineEntry | TimelineController |
| TimelineWriter | TimelineEntry | 4 controllers (Activity, Note, Comment, Lead) |
| TagService | Tag | TagController |
| StatusService | Status, StatusType | StatusController, StatusTypeController |
| SourceService | Source | SourceController |
| CustomFieldService | CustomFieldDefinition | CustomFieldController |
| FeatureDefinitionService | FeatureDefinition | FeatureDefinitionController |
| FeatureGateService | PlanFeature, TenantFeatureOverride, UsageCounter | WorkflowService |
| WorkflowDefinitionService | WorkflowDefinition | WorkflowDefinitionController |
| WorkflowService | FeatureGateService, WorkflowDefinition, WorkflowLog | WorkflowDefinitionController |
| MoveLeadStageAction | Lead, PipelineStage | LeadController |

### 4.3 Detected Circular Dependency

```
WorkflowService ──→ FeatureGateService
      ↑                       │
      │                       │
      └───────────────────────┘
  (weak — not true circular)

WorkflowService.trigger()
  → FeatureGateService.allows(tenant, 'workflows')
  → reads plan features for tenant
  → no reference back to WorkflowService

**Verdict:** Not a true circular dependency. FeatureGateService is stateless and only reads plan/override data.
```

---

## 5. Event Flow Diagram

### 5.1 Current Event Flow

```
┌────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ User Action│────→│   Controller     │────→│    Service      │
│ (API Call) │     │  (Gate::auth)    │     │  (Business Logic)│
└────────────┘     └──────────────────┘     └────────┬────────┘
                                                     │
                    ┌────────────────────────────────┤
                    │                                │
                    ▼                                ▼
          ┌──────────────────┐             ┌─────────────────┐
          │    TimelineWriter│             │   Model::create │
          │  ::record()      │             │   /update/delete│
          └───────┬──────────┘             └────────┬────────┘
                  │                                 │
                  ▼                                 ▼
          ┌──────────────────┐             ┌─────────────────┐
          │  TimelineEntry   │             │  Database       │
          │  (append-only)   │             │  (SQL)          │
          └──────────────────┘             └─────────────────┘
                                                     │
                          ┌──────────────────────────┤
                          │                          │
                          ▼                          ▼
            ┌──────────────────────┐      ┌─────────────────────┐
            │  WorkflowService     │      │  Activity Log       │
            │  ::trigger(event)    │      │  (Spatie\Activitylog)│
            │  (conditional)       │      └─────────────────────┘
            └──────────┬───────────┘
                       │
                       ▼
            ┌──────────────────────┐
            │  WorkflowLog         │
            │  (execution record)  │
            └──────────────────────┘
```

### 5.2 Event Types Currently Emitted

| Event | Emitter | Timeline Entry | Workflow Trigger |
|-------|---------|:---:|:---:|
| `activity.created` | ActivityController::store | ✅ | ✅ |
| `activity.updated` | ActivityController::update | ✅ | — |
| `note.created` | NoteController::store | ✅ | ✅ |
| `comment.created` | CommentController::store | ✅ | ✅ |
| `lead.stage_moved` | LeadController::moveStage | — | — |
| `lead.created` | LeadController::store | — | — |
| `lead.updated` | LeadController::update | — | — |
| `person.created` | PersonController::store | — | — |
| `organization.created` | OrganizationController::store | — | — |

### 5.3 Missing Event Integrations

| Missing Event | Current Behavior | Priority |
|---------------|-----------------|----------|
| `lead.created` → TimelineWriter | No timeline entry created when a lead is created | **High** |
| `lead.stage_moved` → TimelineWriter | No timeline entry when lead moves stage | **High** |
| `person.created` → TimelineWriter | No timeline entry on person create | **Medium** |
| `organization.created` → TimelineWriter | No timeline entry on org create | **Medium** |
| `person.updated` → TimelineWriter | No update event tracked | **Low** |
| `organization.updated` → TimelineWriter | No update event tracked | **Low** |

> **Note:** The Sprint 3 spec identified these gaps but they remain unresolved. TimelineWriter integration in LeadController (and Person/Organization controllers) should be added as part of Sprint 5 or a dedicated integration sprint.

---

## 6. Timeline Integration Points

### 6.1 Current Integration

| Controller | TimelineWriter::record() | Event Types |
|-----------|-------------------------|-------------|
| ActivityController::store | ✅ | `activity.created` |
| ActivityController::update | ✅ | `activity.updated` |
| NoteController::store | ✅ | `note.created` |
| CommentController::store | ✅ | `comment.created` |
| LeadController | ❌ | Not integrated |
| PersonController | ❌ | Not integrated |
| OrganizationController | ❌ | Not integrated |

### 6.2 TimelineWriter API

```php
// For typed entities (models with getMorphClass())
TimelineWriter::record(
    object $entity,       // Model instance (calls getMorphClass())
    string $eventType,    // 'activity.created', 'lead.stage_moved', etc.
    string $title,        // Human-readable title
    ?string $description, // Optional description
    ?array $meta,         // Optional metadata JSON
    ?int $causedBy        // User ID who caused the event
): TimelineEntry

// For string-based entity types (when model instance isn't available)
TimelineWriter::recordGeneric(
    string $entityType,   // Fully qualified class name
    int $entityId,        // Entity ID
    string $eventType,
    string $title,
    ?string $description,
    ?array $meta,
    ?int $causedBy
): TimelineEntry
```

### 6.3 Future Integration Pattern

Every controller that modifies state should emit timeline events:

```
Controller::store()   → Service::create() → TimelineWriter::record($entity, "{$type}.created", ...)
Controller::update()  → Service::update() → TimelineWriter::record($entity, "{$type}.updated", ...)
Controller::delete()  → Service::delete() → TimelineWriter::record(...)  [optional: soft delete log]
Controller::restore() → Service::restore()→ TimelineWriter::record(...)  [optional]
```

---

## 7. Workflow Integration Points

### 7.1 Current Integration

| Trigger Event | Service | Workflow Trigger | Feature Gate Check |
|---------------|---------|:----------------:|:------------------:|
| `created` | PersonController::store | ❌ | ❌ |
| `updated` | PersonController::update | ❌ | ❌ |
| `created` | OrganizationController::store | ❌ | ❌ |
| `updated` | OrganizationController::update | ❌ | ❌ |
| `created` | LeadController::store | ❌ | ❌ |
| `stage_moved` | LeadController::moveStage | ❌ | ❌ |
| `created` | ActivityController::store | ✅ | ❌ |
| `created` | NoteController::store | ✅ | ❌ |
| `created` | CommentController::store | ✅ | ❌ |

### 7.2 WorkflowService::trigger() Dependency

`WorkflowService::trigger(string $event, Model $entity)`:
1. Calls `FeatureGateService::assert(tenant, 'workflows')` — checks plan/override
2. Queries `WorkflowDefinition` where `entity_type = $entity->getMorphClass()` AND `trigger_event = $event`
3. Evaluates `conditions` JSON against `$entity`
4. Executes `actions` (assign_owner, update_field, create_task, send_notification)

### 7.3 Missing Workflow Integrations

| Entity | Event | Not Triggered | Impact |
|--------|-------|:-------------:|--------|
| Lead | created, stage_moved, updated | ✅ | Critical — workflows cannot automate lead management |
| Person | created, updated | ✅ | High — no lead routing or assignment automation |
| Organization | created, updated | ✅ | Medium |

### 7.4 Workflow-Capable Actions Table

| Action Type | Implementation | Works For |
|-------------|---------------|-----------|
| `assign_owner` | `executeAssignOwner()` ✅ | Lead, Person, Organization |
| `update_field` | `executeUpdateField()` ✅ | Lead, Person, Organization |
| `create_task` | `executeCreateTask()` ⚠️ | Uses Activity model — should work |
| `send_notification` | `executeSendNotification()` ⚠️ | Requires notification infrastructure |

---

## 8. Future Client Portal Dependencies

### 8.1 Expected Requirements

The Client Portal will allow end-clients of FollowKa tenants to:
- View their own data (tickets, invoices, communications)
- Submit requests and ticket replies
- View activity history but not modify

### 8.2 Dependency Impact

| Current Module | Portal Dependency | Gap |
|---------------|-------------------|-----|
| **Auth** | Portal needs separate auth guard (client guard) | No client User model or auth provider |
| **Permissions** | Portal users need restricted role (e.g., `client` role) | Requires seed + policy relaxation on read |
| **Tickets** | Portal users create/reply to tickets | `TicketReply` has `central_user_id` — needs polymorphic `user` |
| **Timeline** | Portal views timeline entries (read-only) | Already read-only — compatible ✅ |
| **Activities** | Portal views activities (read-only) | Compatible ✅ |
| **Notes** | Portal views notes (read-only) | Compatible ✅ |
| **Comments** | Portal views comments (read-only) | Compatible ✅ |
| **Invoices** | Portal views/downloads invoices | Needs file URL generation for PDFs |
| **Payments** | Portal makes payments | Needs client-facing payment endpoint |
| **Files/Media** | Portal uploads attachments | Media library exists but not integrated with tickets |

### 8.3 Portal Architecture Recommendation

```
┌────────────────────────────────────────────────────────────┐
│                   CLIENT PORTAL (New)                      │
│                                                            │
│  routes/tenant/portal-v1.php (NEW)                         │
│  App/Http/Controllers/Tenant/Api/V1/Portal/ (NEW)         │
│  App/Models/ClientUser.php (NEW, BelongsToTenant)         │
│  App/Policies/Portal/ (NEW)                               │
│                                                            │
│  Depends on: TimelineService (read), InvoiceService (read) │
│              TicketService (create + read), ActivityService│
│              NoteService, CommentService                   │
└────────────────────────────────────────────────────────────┘
```

### 8.4 Blockers

| Blocker | Severity | Resolution Needed |
|---------|----------|------------------|
| No client user model/guard | **Critical** | Create `ClientUser` model with `tenant-api-clients` guard |
| TicketReply.user locked to central_user_id | **High** | Make `TicketReply.user` polymorphic or add `client_user_id` |
| No file upload on tickets | **Medium** | Add media attachment support to tickets |
| Portal-specific rate limiting | **Low** | Configurable rate limits per portal client |

---

## 9. Future Mobile App Dependencies

### 9.1 Expected Requirements

The Mobile App will provide:
- Read access to People, Organizations, Leads
- Activity creation (call logs, visit check-ins)
- Push notifications
- Offline-capable data sync

### 9.2 Dependency Impact

| Current Module | Mobile Dependency | Gap |
|---------------|-------------------|-----|
| **REST API** | All current endpoints return JSON ✅ | Compatible — no changes needed |
| **Auth** | Token-based auth via Sanctum ✅ | Compatible — Sanctum supports mobile tokens |
| **Activity** | Mobile creates call/visit activities | Compatible ✅ — ActivityController::store exists |
| **Leads** | Read + stage move | LeadController exists, MoveLeadStageAction exists |
| **Push Notifications** | Required for mobile | No push notification infrastructure |
| **Offline Sync** | Required for field use | No sync tokens, no last-sync endpoints |
| **File Uploads** | Photo capture for activities | Media library exists but not integrated |
| **GPS Location** | Address proximity search | No geo-search on addresses |

### 9.3 Mobile API Surface

```
Current API (Compatible):
  GET    /api/tenant/v1/crm/people              → List/search contacts
  GET    /api/tenant/v1/crm/organizations        → List/search orgs
  GET    /api/tenant/v1/crm/leads                → Pipeline view
  POST   /api/tenant/v1/crm/activities           → Log call/visit
  GET    /api/tenant/v1/crm/timeline             → Recent activity feed

Missing for Mobile:
  POST   /api/tenant/v1/mobile/sync              → Sync endpoint (NEW)
  GET    /api/tenant/v1/mobile/sync-status       → Last sync timestamp (NEW)
  POST   /api/tenant/v1/files/upload             → Photo upload (NEW)
  GET    /api/tenant/v1/crm/addresses/nearby     → Geo-search (NEW)
  POST   /api/tenant/v1/push/register            → Device token (NEW)
```

### 9.4 Authentication Strategy

| Auth Method | Sanctum Token | Expiry | Use Case |
|-------------|:-------------:|:------:|----------|
| Web Session | Cookie | Session | Browser |
| Mobile Token | Bearer Token | Long-lived (configurable) | Mobile app |
| API Key | Bearer Token (ApiKey model) | Manual revoke | 3rd-party |

> **Status:** Sanctum is already configured with both `tenant-api` guard for web and supports token-based auth. No changes needed for token authentication.

---

## 10. Solar Module Dependencies

### 10.1 Expected Domain

The Solar Module will manage:
- Solar project lifecycle (lead → proposal → installation → monitoring)
- Site assessments and solar potential calculations
- Equipment inventory (panels, inverters, batteries)
- Installation scheduling and crew management
- Production monitoring and performance analytics
- Incentive/tax credit tracking
- Utility interconnection management

### 10.2 Reusable CRM Entities

| CRM Entity | Solar Reuse | Customization Needed |
|------------|-------------|---------------------|
| **Person** | Customer/Site contact | New `customer` role/type, solar-specific custom fields |
| **Organization** | Installer/Partner company | Partnership tracking, contractor relationships |
| **Lead** | Solar sales lead → project | New status workflow, solar-specific pipeline stages |
| **Address** | Site address (roof location) | Add `property_type`, `roof_orientation`, `azimuth` fields or custom fields |
| **Activity** | Site visit, inspection log | New activity types: `site_survey`, `inspection`, `permit_submission` |
| **Note** | Internal project notes | No changes needed |
| **Comment** | Customer communications | No changes needed |
| **Timeline** | Project history audit | Already compatible ✅ |
| **Tag** | Equipment type, project phase | No changes needed |
| **Status** | Solar-specific workflows | New StatusTypes: `solar_project`, `solar_lead` |
| **Source** | Solar lead source tracking | New categories: `referral`, `door_knock`, `website` |
| **Custom Fields** | Solar-specific data (panel count, system size) | Already compatible ✅ |
| **Workflows** | Auto-assign leads, trigger notifications | Already compatible ✅ |

### 10.3 New Solar-Specific Models Needed

```
SolarProject (extends Lead? or standalone?)
  ├── SolarProposal (pricing, system design)
  ├── SolarInstallation (crew, schedule, completion)
  ├── SolarEquipment (panels, inverters, batteries inventory)
  ├── SolarPermit (utility interconnection, building permit)
  ├── SolarMonitoring (production data, alerts)
  ├── SolarIncentive (tax credit, rebate tracking)
  └── SolarContract (PPA, lease, purchase agreement)
```

### 10.4 Architecture Decision: Lead vs Project

| Approach | Pros | Cons |
|----------|------|------|
| **Extend Lead** (add `is_solar` flag + stage mapping) | Reuses pipeline logic, stage movement, existing endpoints | Pollutes Lead model with solar-specific fields, stage conflicts |
| **Standalone SolarProject** (new model, new endpoints) | Clean domain boundary, solar-specific logic isolated | Duplicates lead-like functionality (stage management) |

> **Recommendation:** Standalone `SolarProject` model with its own pipeline/stage structure. Do NOT reuse Lead. The lead-to-project handoff should be a Timeline event, not a model inheritance.

### 10.5 Integration Points

```
Lead ──(converted_to_solar)──→ SolarProject
  │                                │
  └── TimelineWriter::record()    └── TimelineWriter::record()

Resource Sharing:
  Person (as Customer)
  Organization (as Installer)
  Address (as Site Location)
  Activity (as Site Visit / Inspection)
  Note / Comment (as Communications)
```

---

## 11. Agency Module Dependencies

### 11.1 Expected Domain

The Agency Module will manage:
- Agency hierarchy (parent agency → sub-agents)
- Commission structures and calculations
- Agent performance tracking
- Client assignments and territory management
- Lead distribution (round-robin, geo-based)

### 11.2 Reusable CRM Entities

| CRM Entity | Agency Reuse | Customization Needed |
|------------|-------------|---------------------|
| **Person** | Agent, Client | New `agent` role, `agency_client` type |
| **Organization** | Agency company | Branch/office tracking |
| **Lead** | Sales lead for agents | Lead distribution logic (round-robin → owner_id) |
| **Activity** | Agent activity tracking | New activity types: `listing_presentation`, `client_meeting` |
| **Timeline** | Agent/client history | Already compatible ✅ |
| **Workflows** | Auto-assign leads | New trigger: `lead.assigned` |
| **Custom Fields** | Agency-specific fields | Already compatible ✅ |
| **Status** | Agency-specific workflows | New StatusTypes: `agent_status`, `lead_status` |

### 11.3 New Agency-Specific Models Needed

```
Agency
  ├── Agent (extends User — BelongsToTenant, has commission rate)
  ├── CommissionPlan (rate structure, tiers, thresholds)
  ├── CommissionPayment (calculated commission, payout status)
  ├── LeadDistribution (round-robin config, geo-fences)
  └── AgencyTerritory (zip codes, regions, exclusive areas)
```

### 11.4 Key Integration: Lead Distribution

```
LeadController::store()
  → LeadService::create()
    → (if agency module enabled)
      → LeadDistributionService::assignOwner(lead)
        → Round-robin / geo-match → sets owner_id
        → TimelineWriter::record('lead.assigned', ...)
```

### 11.5 Module Gate Checkpoint

The Agency Module should check `FeatureGateService::allows(tenant, 'agency')` before executing any agency-specific logic. This should be added as middleware or a service-layer conditional:

```php
// In LeadService::create()
$lead = Lead::create($data);

if (FeatureGate::allows(tenant(), 'agency')) {
    app(LeadDistributionService::class)->assignOwner($lead);
}
```

---

## 12. Real Estate Module Dependencies

### 12.1 Expected Domain

The Real Estate Module will manage:
- Property listings (residential, commercial, land)
- MLS integration and data synchronization
- Property showings and open houses
- Offers and negotiations
- Closing management
- Rental/lease management

### 12.2 Reusable CRM Entities

| CRM Entity | Real Estate Reuse | Customization Needed |
|------------|-------------------|---------------------|
| **Person** | Buyer, Seller, Agent, Inspector | Role-specific types |
| **Organization** | Brokerage, Title Company, Lender | Partnership types |
| **Address** | Property address | New type: `property` (exists ✅) |
| **Lead** | Buyer/Seller lead | Real estate pipeline stages |
| **Activity** | Showing, open house, inspection | New types: `showing`, `open_house`, `inspection` |
| **Note** | Property notes | No changes needed |
| **Comment** | Negotiation comments | No changes needed |
| **Timeline** | Offer → closing history | Already compatible ✅ |
| **Tag** | Property features (bedrooms, pool, garage) | Already compatible ✅ |
| **Custom Fields** | Property attributes (sqft, lot size, year built) | Already compatible ✅ |
| **Workflows** | Offer notifications, closing tasks | Already compatible ✅ |

### 12.3 New Real Estate-Specific Models Needed

```
Property (new aggregate root)
  ├── PropertyListing (price, status, MLS data)
  ├── PropertyImage (gallery, virtual tour)
  ├── PropertyShowing (scheduled showings, feedback)
  ├── PropertyOffer (offer details, contingencies, status)
  ├── PropertyClosing (closing checklist, docs, dates)
  ├── PropertyRental (lease terms, tenant info)
  └── PropertyDocument (disclosures, reports, contracts)
```

### 12.4 Integration with Address

```php
// Address already supports 'property' type
Address::TYPES = ['billing', 'shipping', 'office', 'site', 'property'];

// Property model would reference the address directly
class Property extends Model {
    // Property is the address + additional data
    // Option A: Property IS the address (extends Address)
    // Option B: Property HAS an address (morphMany Address)
    // Option C: Property has its own location columns
}
```

> **Recommendation:** Option B — Property HAS an Address via the existing polymorphic addressable relationship. This keeps address geo-data consistent with the rest of the CRM.

### 12.5 MLS Integration Impact

MLS sync would require:
- A scheduled job for periodic sync
- A `PropertySource` model (MLS source, last_synced_at)
- Webhook endpoint for MLS update notifications
- Data mapping layer (MLS fields → Custom Fields)

---

## 13. Cross-Cutting Analysis

### 13.1 Circular Dependencies

| # | Dependency Chain | Severity | Resolution |
|---|---|---|---|
| 1 | `WorkflowService` → `FeatureGateService` → (plan data) | **None** | FeatureGateService is stateless, no back-reference to WorkflowService |
| 2 | `Lead` → `Pipeline` → `PipelineStage` → (Lead pipeline_stage_id) | **None** | Stage FK to Pipeline, Lead FK to Stage — no query back to Lead from Pipeline |
| 3 | `NoteController` → `TimelineWriter` → `TimelineEntry` → (morphs to Note) | **None** | Polymorphic — no write back to Note |

> **Verdict:** Zero circular dependencies found. The layered architecture (Controller → Service → Model) prevents circular references.

### 13.2 Future Bottlenecks

| # | Bottleneck | Location | Impact | Mitigation |
|---|---|---|---|---|
| **B1** | **TimelineWriter is called synchronously in request lifecycle** | 4 controllers (Activity::store/update, Note::store, Comment::store) + should be in Lead, Person, Org | API response time increases with every new TimelineWriter integration | **Queue TimelineWriter::record() calls** — dispatch to a `RecordTimelineEntry` job (HIGH priority) |
| **B2** | **WorkflowService::trigger() is not called anywhere** | Not integrated in any controller | 0% of triggers fire | Integrate `WorkflowService::trigger('created', $entity)` in all `*Controller::store()` methods (HIGH priority) |
| **B3** | **No pagination on byEntity() endpoints** | AddressService, ActivityService, NoteService, CommentService, TimelineService — all `->get()` | Large datasets will cause memory issues | Add pagination with sensible defaults (MEDIUM priority) |
| **B4** | **BulkTagRequest validates entity_ids as integer but doesn't verify existence** | BulkTagRequest hardcodes `['required', 'integer']` for entity_ids | Can reference non-existent entities | Add `Rule::exists` validation for each entity type (LOW priority) |
| **B5** | **Notification infrastructure missing** | WorkflowService `send_notification` action | Workflow action cannot execute | Requires notification channel setup (email, SMS, push) (HIGH when needed) |
| **B6** | **Media library exists but no integration** | `database/migrations/...create_media_table.php` exists | No entity exposes file uploads | Integrate media with Activities (photo attachments) (MEDIUM) |
| **B7** | **Search uses LIKE %term% — no full-text index** | All service `search` filters | Slow on large datasets | Add full-text indexes or switch to Scout/Meilisearch (MEDIUM) |

### 13.3 Duplicate Responsibilities

| # | Duplication | Files | Severity | Recommendation |
|---|---|---|---|---|
| **D1** | **Two UsageCounter models** | `App\Models\UsageCounter` (central) + `App\Models\Crm\UsageCounter` (CRM) | **Low** | Central UsageCounter is for platform-wide billing; CRM UsageCounter is per-feature tracking. Different responsibilities but same table concept. Consider unifying under `App\Models\UsageCounter` with a `scope` field. |
| **D2** | **Two PlanFeature models** | `App\Models\PlanFeature` (pivot) + `App\Models\Crm\PlanFeature` (CRM feature pivot) | **Low** | Central one links Plan → Feature (billing). CRM one links Plan → FeatureDefinition (feature gating). Two separate domains. Keep separate. |
| **D3** | **Polymorphic `*_type` validation is string-only across all requests** | StoreActivityRequest, StoreNoteRequest, StoreCommentRequest, StoreAddressRequest | **Low** | No request restricts allowed morph classes. If the application grows, this allows attaching activities to any model even if unintended. |
| **D4** | **PipelineService and PipelineStageService share query logic** | PipelineService::query() + PipelineStageService::query() | **Low** | Both are thin wrappers around Eloquent. Not worth abstracting at this scale. |
| **D5** | **ActivityService, NoteService, CommentService have near-identical structure** | All 3 have query/paginateWithFilters/find/create/update/delete/restore/getForEntity | **Low** | The pattern is by design — service-per-module. Could extract a `BaseCrmService` abstract class but not urgent. |

### 13.4 Missing Abstractions

| # | Missing Abstraction | Why Needed | Priority |
|---|---|---|---|
| **M1** | **BaseCrmService** | All CRM services share `query()`, `paginateWithFilters()`, `find()`, CRUD methods. A base class would reduce boilerplate. | **Low** (nice-to-have) |
| **M2** | **MorphableEntityResolver** | Every polymorphic request validates `*_type` as plain string. A centralized resolver/repository that maps allowed types and validates existence would eliminate D3. | **Medium** |
| **M3** | **EventDispatcher** | Currently controllers directly call `TimelineWriter::record()` and should call `WorkflowService::trigger()`. An event dispatcher layer would decouple controllers from these services. | **High** |
| **M4** | **SyncService** | Mobile app and MLS integrations need a sync abstraction (sync tokens, delta endpoints, conflict resolution). | **Future** |
| **M5** | **NotificationDispatcher** | Workflow actions need a unified notification dispatch (email, SMS, push, in-app). Currently only the action type exists but no sender. | **High** (for Workflow completion) |

### 13.5 Codebase Metrics

| Metric | Value |
|--------|-------|
| **Total Models** | 20 Central Admin + 19 CRM = **39 models** |
| **Total Services** | 30 Central + 17 CRM = **47 services** |
| **Total Controllers** | 37 Central + 19 CRM = **56 controllers** |
| **Total Policies** | 21 Central + 19 CRM = **40 policies** |
| **Total Tests** | **497 tests, 1507 assertions** (all passing) |
| **Total Migrations** | ~52 Central + 16 CRM = **~68 migrations** |
| **Total Routes (CRM only)** | **24 routes** (Sprint 4) |

---

## 14. Architecture Score

### 14.1 Scoring Rubric

| Dimension | Weight | Score (0-10) | Weighted |
|-----------|:------:|:------------:|:--------:|
| **Domain Boundaries** | 15% | 9.0 | 1.35 |
| **Tenant Isolation** | 15% | 10.0 | 1.50 |
| **Layered Architecture** | 10% | 9.5 | 0.95 |
| **Service Pattern** | 10% | 8.5 | 0.85 |
| **Polymorphic Design** | 10% | 9.0 | 0.90 |
| **Test Coverage** | 10% | 8.5 | 0.85 |
| **Event/Workflow Readiness** | 10% | 5.0 | 0.50 |
| **Future Extensibility** | 10% | 7.5 | 0.75 |
| **No Circular Dependencies** | 5% | 10.0 | 0.50 |
| **Documentation** | 5% | 6.0 | 0.30 |
| **Total** | **100%** | | **8.45 / 10** |

### 14.2 Dimension Breakdown

**Domain Boundaries (9/10)** — Central Admin ↔ Tenant CRM is cleanly separated. Subdomain boundaries within CRM are well-defined. Polymorphic relationships enable clean cross-entity attachment. **-1** for shared UsageCounter/PlanFeature naming confusion.

**Tenant Isolation (10/10)** — Every CRM model has `tenant_id` FK + `BelongsToTenant` trait + `TenantScope` global scope. Cross-tenant access returns 404. No isolation gaps found in audit.

**Layered Architecture (9.5/10)** — Controller → Service → Model is strictly enforced. No business logic in controllers. Controllers handle only auth gate, validation, and response formatting. **-0.5** because `MoveLeadStageAction` should be called from a service layer, not directly from a controller.

**Service Pattern (8.5/10)** — Consistent method naming (`paginateWithFilters`, `find`, `create`, `update`, `delete`, `restore`). The `getForEntity()` pattern is consistent across polymorphic services. **-1.5** for:
- Some UPDATE requests lack tenant-scoped exists rules (M1 fixed in Sprint 4 audit)
- Some services lack `restore()` method where SoftDeletes is used (Pipeline, PipelineStage)
- No `forceDelete()` on all soft-deletable models

**Polymorphic Design (9/10)** — Address, Activity, Note, Comment, TimelineEntry, Tag all use polymorphic relationships. This is the correct approach for a CRM. **-1** for:
- No allowed-class restriction on polymorphic type fields
- No unified morph map configuration

**Test Coverage (8.5/10)** — 497 tests, 1507 assertions, 0 failures. Every module has CRUD + tenant isolation + 401/403/404/422 tests. **-1.5** for:
- Missing timeline event tests for Lead, Person, Organization
- Missing pagination/per_page tests
- Missing sort_by/sort_order edge case tests

**Event/Workflow Readiness (5.0/10)** — TimelineWriter exists and is integrated in 3 controllers. WorkflowService exists but is NOT integrated in any controller. **-5.0** for:
- LeadController has no TimelineWriter integration
- PersonController/OrganizationController have no TimelineWriter integration
- WorkflowService::trigger() is never called
- No event dispatcher abstraction
- Notification infrastructure missing

**Future Extensibility (7.5/10)** — Polymorphic design, custom fields, and workflow actions provide a solid foundation. **-2.5** for:
- No module loading system beyond feature gates
- No plugin architecture for Solar/Agency/RealEstate
- No event dispatcher abstraction
- No sync service for mobile/offline

**No Circular Dependencies (10/10)** — Zero circular dependencies found. Clean unidirectional dependency graph.

**Documentation (6.0/10)** — OpenAPI spec exists. Database blueprints exist. Architectural roadmap exists. **-4.0** for:
- README is minimal
- No sequence diagrams for key flows
- No deployment/ops documentation
- No API versioning strategy documented

### 14.3 Final Verdict

```
╔═══════════════════════════════════════════════════════════════╗
║              FOLLOWKA ARCHITECTURE SCORE v1                  ║
║                                                              ║
║              ╔══════════════════════════════════╗             ║
║              ║        8.45 / 10.00             ║             ║
║              ║       "SOLID FOUNDATION"        ║             ║
║              ╚══════════════════════════════════╝             ║
║                                                              ║
║  Strengths:                                                  ║
║    ✅ Perfect tenant isolation                               ║
║    ✅ Zero circular dependencies                             ║
║    ✅ Clean layered architecture                              ║
║    ✅ Comprehensive test coverage                             ║
║    ✅ Polymorphic design for extensibility                    ║
║                                                              ║
║  Weaknesses:                                                 ║
║    ⚠️ Event/Timeline integration incomplete                   ║
║    ⚠️ Workflow triggers not connected                        ║
║    ⚠️ Notification infrastructure missing                    ║
║    ⚠️ No base service abstraction                            ║
║    ⚠️ Polymorphic type validation is permissive              ║
║                                                              ║
║  Immediate Priorities:                                       ║
║    1. Integrate TimelineWriter in Lead/Person/Org controllers║
║    2. Connect WorkflowService::trigger() in all controllers  ║
║    3. Add event dispatcher abstraction                       ║
║    4. Queue TimelineWriter calls for performance             ║
║    5. Add per_page to byEntity() endpoints                   ║
║                                                              ║
╚═══════════════════════════════════════════════════════════════╝
```

---

*End of FollowKa Domain Architecture Map v1*
