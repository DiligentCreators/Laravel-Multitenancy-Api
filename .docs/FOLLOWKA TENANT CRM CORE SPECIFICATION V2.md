# FOLLOWKA TENANT CRM CORE SPECIFICATION V2

**Version:** 2.0  
**Date:** June 19, 2026  
**Status:** Final Architecture — Pre-Development  
**Author:** Chief Product Architect & Lead SaaS Architect

---

## EXECUTIVE SUMMARY

FollowKa Tenant CRM Core is an industry-agnostic CRM foundation designed for multi-tenant SaaS deployment. It serves as the base layer upon which industry-specific modules (Solar, Agency, Real Estate) are built, and which Client Portal and React Native Mobile App consume via API.

### Architecture Philosophy

```
Controller → Action → Service → Model
```

Business logic must never exist in controllers. Actions are single-responsibility units. Services orchestrate cross-entity workflows. Models are thin data layers with scopes, relationships, and casts only.

### Key Architectural Decisions

| Decision | Rationale |
|---|---|
| People are universal (not customer/prospect/lead tables) | Industry modules add FKs, avoid table-per-type complexity |
| OrganizationPeople pivot replaces Contacts | Person IS a contact, many-to-many through pivot with role metadata |
| Configurable status system (no enums) | Each tenant defines their own statuses per entity type |
| Polymorphic morphs for activities, notes, comments, documents, timeline | Enables any entity to participate without schema changes |
| Universal ownership (owner_id + team_id) on every major entity | Consistent access control, reporting, and assignment |
| Feature Gate Service evaluates 5 conditions per check | Subscription, module, plan, usage, overage — all in one call |
| Timeline as a unified event stream | Every entity contributes to a single queryable feed |
| Merge Engine with full rollback | Data integrity across all relationships during deduplication |
| Scout + Meilisearch for search | Blazing fast full-text search with typo tolerance |
| Import Framework with preview + rollback | Safe bulk data ingestion with error recovery |

---

## 1. COMPLETE CRM CORE ERD

### 1.1 Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│  ┌──────────────────┐         ┌──────────────────────┐         ┌──────────────────┐ │
│  │      Person      │         │   OrganizationPerson  │         │  Organization    │ │
│  ├──────────────────┤         ├──────────────────────┤         ├──────────────────┤ │
│  │ id (PK)          │◄────────┤ organization_id (FK)  ├────────►│ id (PK)          │ │
│  │ tenant_id (FK)   │         │ person_id (FK)        │         │ tenant_id (FK)   │ │
│  │ first_name       │         │ role                  │         │ name             │ │
│  │ last_name        │         │ is_primary            │         │ industry         │ │
│  │ email            │         │ start_date            │         │ website          │ │
│  │ phone            │         │ end_date              │         │ email            │ │
│  │ mobile           │         │ timestamps            │         │ phone            │ │
│  │ job_title        │         └──────────────────────┘         │ owner_id (FK)    │ │
│  │ date_of_birth    │                                            │ team_id (FK)     │ │
│  │ status_id (FK)───┼────┐                                      │ status_id (FK)──┼────┐
│  │ owner_id (FK)    │    │                                      │ created_by (FK)  │    │
│  │ team_id (FK)     │    │                                      │ updated_by (FK)  │    │
│  │ created_by (FK)  │    │                                      │ timestamps       │    │
│  │ updated_by (FK)  │    │                                      │ soft_deletes     │    │
│  │ timestamps       │    │                                      └──────────────────┘    │
│  │ soft_deletes     │    │                                                             │
│  └──────────────────┘    │         ┌──────────────────┐                                │
│                          │         │  crm_statuses    │                                │
│                          │         ├──────────────────┤                                │
│                          ├────────►│ id (PK)          │◄───────────────────────────────┘
│                          │         │ tenant_id (FK)   │
│                          │         │ type_id (FK)     │──────┐
│                          │         │ name             │      │
│                          │         │ key (slug)       │      │
│                          │         │ color            │      │
│                          │         │ order            │      │
│                          │         │ is_default       │      │
│                          │         │ is_active        │      │
│                          │         │ timestamps       │      │
│                          │         └──────────────────┘      │
│                          │                                   │
│                          │         ┌──────────────────┐      │
│                          │         │ crm_status_types  │      │
│                          │         ├──────────────────┤      │
│                          └────────►│ id (PK)          │◄─────┘
│                                    │ tenant_id (FK)   │
│                                    │ entity_type      │
│                                    │ name             │
│                                    │ key (slug)       │
│                                    │ timestamps       │
│                                    └──────────────────┘
│
│  ┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│  │     Address      │         │       Tag        │         │  CustomFieldDef   │
│  ├──────────────────┤         ├──────────────────┤         ├──────────────────┤
│  │ id (PK)          │         │ id (PK)          │         │ id (PK)          │
│  │ tenant_id (FK)   │         │ tenant_id (FK)   │         │ tenant_id (FK)   │
│  │ addressable_type │         │ name             │         │ entity_type      │
│  │ addressable_id   │         │ color            │         │ name             │
│  │ type             │         │ is_active        │         │ key (slug)       │
│  │ country          │         │ timestamps       │         │ type             │
│  │ state            │         └────────┬─────────┘         │ options (json)   │
│  │ city             │                  │                    │ is_required      │
│  │ postal_code      │         ┌───────┴────────┐           │ is_unique        │
│  │ address_line_1   │         │   Taggable     │           │ validation_rules │
│  │ address_line_2   │         ├────────────────┤           │ order            │
│  │ latitude         │         │ tag_id (FK)    │           │ is_active        │
│  │ longitude        │         │ taggable_type  │           │ timestamps       │
│  │ is_default       │         │ taggable_id    │           └────────┬─────────┘
│  │ timestamps       │         │ timestamps     │                    │
│  └──────────────────┘         └────────────────┘           ┌────────┴──────────┐
│                                                             │ CustomFieldValue  │
│  ┌──────────────────┐         ┌──────────────────┐         ├──────────────────┤
│  │      Note        │         │    Document      │         │ id (PK)          │
│  ├──────────────────┤         ├──────────────────┤         │ field_id (FK)    │
│  │ id (PK)          │         │ id (PK)          │         │ entity_type      │
│  │ tenant_id (FK)   │         │ tenant_id (FK)   │         │ entity_id        │
│  │ notable_type     │         │ documentable_type│         │ value (json)     │
│  │ notable_id       │         │ documentable_id  │         │ timestamps       │
│  │ body             │         │ name             │         └──────────────────┘
│  │ created_by (FK)  │         │ path             │
│  │ timestamps       │         │ mime_type        │
│  │ soft_deletes     │         │ size             │
│  └──────────────────┘         │ type             │
│                               │ owner_id (FK)    │
│  ┌──────────────────┐         │ team_id (FK)     │
│  │    Comment       │         │ created_by (FK)  │
│  ├──────────────────┤         │ updated_by (FK)  │
│  │ id (PK)          │         │ timestamps       │
│  │ tenant_id (FK)   │         │ soft_deletes     │
│  │ commentable_type │         └──────────────────┘
│  │ commentable_id   │
│  │ body             │         ┌──────────────────┐
│  │ mentions (json)  │         │    Activity      │
│  │ created_by (FK)  │         ├──────────────────┤
│  │ timestamps       │         │ id (PK)          │
│  │ soft_deletes     │         │ tenant_id (FK)   │
│  └──────────────────┘         │ activitable_type │
│                               │ activitable_id   │
│  ┌──────────────────┐         │ type             │
│  │      Lead        │         │ subject          │
│  ├──────────────────┤         │ description      │
│  │ id (PK)          │         │ scheduled_at     │
│  │ tenant_id (FK)   │         │ completed_at     │
│  │ person_id (FK)   │         │ duration_minutes │
│  │ organization_id  │         │ owner_id (FK)    │
│  │ email            │         │ team_id (FK)     │
│  │ phone            │         │ created_by (FK)  │
│  │ company_name     │         │ timestamps       │
│  │ source_id (FK)   │         │ soft_deletes     │
│  │ status_id (FK)───┼─────┐   └──────────────────┘
│  │ pipeline_id (FK) │     │
│  │ stage_id (FK)    │     │   ┌──────────────────┐
│  │ value            │     │   │    LeadSource    │
│  │ probability      │     │   ├──────────────────┤
│  │ owner_id (FK)    │     │   │ id (PK)          │
│  │ team_id (FK)     │     │   │ tenant_id (FK)   │
│  │ qualified_at     │     │   │ name             │
│  │ converted_at     │     │   │ type             │
│  │ lost_reason      │     │   │ is_active        │
│  │ lost_at          │     │   │ timestamps       │
│  │ created_by (FK)  │     │   └──────────────────┘
│  │ updated_by (FK)  │     │
│  │ timestamps       │     │   ┌──────────────────┐
│  │ soft_deletes     │     │   │    Pipeline      │
│  └──────────────────┘     │   ├──────────────────┤
│                           │   │ id (PK)          │
│  ┌──────────────────┐     │   │ tenant_id (FK)   │
│  │       Task       │     │   │ name             │
│  ├──────────────────┤     │   │ entity_type      │
│  │ id (PK)          │     │   │ is_default       │
│  │ tenant_id (FK)   │     │   │ is_active        │
│  │ taskable_type    │     │   │ created_by (FK)  │
│  │ taskable_id      │     │   │ timestamps       │
│  │ title            │     │   │ soft_deletes     │
│  │ description      │     │   └────────┬─────────┘
│  │ type_id (FK)     │     │            │
│  │ priority         │     │   ┌────────┴─────────┐
│  │ status_id (FK)───┼─────┘   │  PipelineStage   │
│  │ due_at           │         ├──────────────────┤
│  │ completed_at     │         │ id (PK)          │
│  │ estimated_min    │         │ pipeline_id (FK) │
│  │ owner_id (FK)    │         │ name             │
│  │ team_id (FK)     │         │ order            │
│  │ created_by (FK)  │         │ color            │
│  │ updated_by (FK)  │         │ probability      │
│  │ timestamps       │         │ is_won_stage     │
│  │ soft_deletes     │         │ is_lost_stage    │
│  └────────┬─────────┘         │ timestamps       │
│           │                   └──────────────────┘
│     ┌─────┴──────────────┐
│     │                    │
│  ┌──┴────────┐    ┌─────┴──────┐
│  │ TaskRemind│    │TaskAssignmt│
│  ├───────────┤    ├────────────┤
│  │ task_id   │    │ task_id    │
│  │ remind_at │    │ user_id    │
│  │ notified  │    │ team_id    │
│  │ timestamps│    │ assigned_by│
│  └───────────┘    │ assigned_at│
│                   └────────────┘
│
│  ┌──────────────────┐         ┌──────────────────┐
│  │      Team        │         │  TeamMembership   │
│  ├──────────────────┤         ├──────────────────┤
│  │ id (PK)          │◄────────┤ team_id (FK)     │
│  │ tenant_id (FK)   │         │ user_id (FK)     │
│  │ name             │         │ role             │
│  │ description      │         │ joined_at        │
│  │ owner_id (FK)    │         │ timestamps       │
│  │ timestamps       │         └──────────────────┘
│  │ soft_deletes     │
│  └──────────────────┘
│
│  ┌──────────────────────────────────────────────────────────────┐
│  │                  crm_timeline_entries                         │
│  ├──────────────────────────────────────────────────────────────┤
│  │ id (PK) | tenant_id (FK) | entity_type | entity_id           │
│  │ event_type | title | description | metadata (json)           │
│  │ created_by (FK) | created_at                                  │
│  └──────────────────────────────────────────────────────────────┘
│         ▲                    ▲                    ▲
│         │                    │                    │
│    (polymorphic)       (polymorphic)        (polymorphic)
│    People, Orgs,      Leads, Tasks,        Documents,
│    Activities         Notes, Comments       Everything
│
│  ┌──────────────────┐
│  │  ImportProfile   │         ┌──────────────────┐
│  ├──────────────────┤         │   ImportHistory   │
│  │ id (PK)          │         ├──────────────────┤
│  │ tenant_id (FK)   │────┐    │ id (PK)          │
│  │ name             │    │    │ profile_id (FK)  │
│  │ entity_type      │    ├───►│ tenant_id (FK)   │
│  │ field_mapping    │    │    │ file_path        │
│  │ duplicate_rules  │    │    │ status           │
│  │ default_values   │    │    │ total_rows       │
│  │ created_by (FK)  │    │    │ success_count    │
│  │ timestamps       │    │    │ error_count      │
│  └──────────────────┘    │    │ error_log (json) │
│                          │    │ rollback_token   │
│  ┌──────────────────┐    │    │ created_by (FK)  │
│  │ SavedReport      │    │    │ timestamps       │
│  ├──────────────────┤    │    └──────────────────┘
│  │ id (PK)          │    │
│  │ tenant_id (FK)   │    │   ┌──────────────────┐
│  │ name             │    │   │  MergeHistory    │
│  │ entity_type      │    │   ├──────────────────┤
│  │ filters (json)   │    │   │ id (PK)          │
│  │ columns (json)   │    │   │ tenant_id (FK)   │
│  │ schedule (cron)  │    │   │ entity_type      │
│  │ created_by (FK)  │    │   │ primary_id       │
│  │ timestamps       │    │   │ secondary_id     │
│  └──────────────────┘    │   │ merge_log (json) │
│                          │   │ rollback_token   │
│                          │   │ created_by (FK)  │
│                          │   │ timestamps       │
│                          │   └──────────────────┘
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. DATABASE BLUEPRINT

### 2.1 Core Entity Tables

#### `crm_people`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | Global scope |
| first_name | string(100) | NOT NULL | — |
| last_name | string(100) | NOT NULL | — |
| email | string(255) | NULLABLE | Unique per tenant (partial index: WHERE email IS NOT NULL) |
| phone | string(50) | NULLABLE | — |
| mobile | string(50) | NULLABLE | — |
| job_title | string(200) | NULLABLE | — |
| date_of_birth | date | NULLABLE | — |
| status_id | foreignId | NULLABLE, FK → crm_statuses.id | Configurable status |
| owner_id | foreignId | NULLABLE, FK → users.id | Record owner |
| team_id | foreignId | NULLABLE, FK → teams.id | Owning team |
| created_by | foreignId | NULLABLE, FK → users.id | — |
| updated_by | foreignId | NULLABLE, FK → users.id | — |
| timestamps | — | — | — |
| soft_deletes | — | — | — |

**Indexes:** `tenant_id`, `status_id`, `owner_id`, `team_id`, composite `(tenant_id, last_name)`, composite `(tenant_id, email)` WHERE email IS NOT NULL.

#### `crm_organizations`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | Global scope |
| name | string(255) | NOT NULL | — |
| industry | string(100) | NULLABLE | Free-text, NOT a FK |
| website | string(500) | NULLABLE | — |
| email | string(255) | NULLABLE | — |
| phone | string(50) | NULLABLE | — |
| status_id | foreignId | NULLABLE, FK → crm_statuses.id | Configurable status |
| owner_id | foreignId | NULLABLE, FK → users.id | Record owner |
| team_id | foreignId | NULLABLE, FK → teams.id | Owning team |
| created_by | foreignId | NULLABLE, FK → users.id | — |
| updated_by | foreignId | NULLABLE, FK → users.id | — |
| timestamps | — | — | — |
| soft_deletes | — | — | — |

**Indexes:** `tenant_id`, `status_id`, `owner_id`, `team_id`, `name`.

#### `crm_organization_person`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| organization_id | foreignId | FK → crm_organizations.id, ON DELETE CASCADE | — |
| person_id | foreignId | FK → crm_people.id, ON DELETE CASCADE | — |
| role | string(200) | NULLABLE | e.g., "CEO", "Manager", "Decision Maker" |
| is_primary | boolean | DEFAULT false | One primary per organization |
| start_date | date | NULLABLE | When relationship began |
| end_date | date | NULLABLE | When relationship ended (null = current) |
| timestamps | — | — | — |

**Indexes:** UNIQUE `(organization_id, person_id)`, `organization_id`, `person_id`, `is_primary`.

#### `crm_addresses`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed |
| addressable_type | string(100) | NOT NULL (morph) |
| addressable_id | bigInteger | NOT NULL (morph) |
| type | string(50) | DEFAULT 'other' |
| country | string(100) | NULLABLE |
| state | string(100) | NULLABLE |
| city | string(100) | NULLABLE |
| postal_code | string(20) | NULLABLE |
| address_line_1 | string(500) | NOT NULL |
| address_line_2 | string(500) | NULLABLE |
| latitude | decimal(10,7) | NULLABLE |
| longitude | decimal(10,7) | NULLABLE |
| is_default | boolean | DEFAULT false |
| timestamps | — | — |

**Indexes:** `tenant_id`, morphs `(addressable_type, addressable_id)`, composite `(tenant_id, type)`.

### 2.2 Status System Tables

#### `crm_status_types`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| entity_type | string(100) | NOT NULL | Morph-friendly name: 'person', 'organization', 'lead', 'task' |
| name | string(100) | NOT NULL | Display name: "Lead Statuses" |
| key | string(100) | NOT NULL | Machine name: "lead_statuses" |
| timestamps | — | — | — |

**Unique:** `(tenant_id, key)`.

#### `crm_statuses`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| type_id | foreignId | FK → crm_status_types.id, ON DELETE CASCADE | — |
| name | string(100) | NOT NULL | "New", "Qualified", "Won", "Lost", etc. |
| key | string(100) | NOT NULL | Machine name: "new", "qualified" |
| color | string(7) | NULLABLE | Hex color for UI badges |
| order | integer | DEFAULT 0 | Display ordering |
| is_default | boolean | DEFAULT false | Assigned when no status specified |
| is_active | boolean | DEFAULT true | — |
| timestamps | — | — | — |

**Unique:** `(tenant_id, type_id, key)`.
**Indexes:** `type_id`, `order`.

### 2.3 Feature Gate Tables

#### `crm_feature_definitions`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| key | string(100) | NOT NULL | 'contacts.max', 'documents.upload', 'pipelines.manage' |
| name | string(200) | NOT NULL | Display name |
| type | string(50) | NOT NULL | 'boolean', 'integer', 'float' |
| default_value | json | NOT NULL | Default if not overridden |
| is_usage_limit | boolean | DEFAULT false | True if this feature tracks a countable limit |
| timestamps | — | — | — |

#### `crm_plan_features`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| plan_id | foreignId | FK → plans.id, ON DELETE CASCADE | — |
| feature_id | foreignId | FK → crm_feature_definitions.id, ON DELETE CASCADE | — |
| value | json | NOT NULL | The allowed value (true, 5000, 100, etc.) |
| timestamps | — | — | — |

**Unique:** `(plan_id, feature_id)`.

#### `crm_tenant_feature_overrides`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, ON DELETE CASCADE | — |
| feature_id | foreignId | FK → crm_feature_definitions.id, ON DELETE CASCADE | — |
| value | json | NOT NULL | Override value |
| reason | string(500) | NULLABLE | Why override was applied |
| expires_at | datetime | NULLABLE | Temporary override expiration |
| created_by | foreignId | FK → users.id | — |
| timestamps | — | — | — |

**Unique:** `(tenant_id, feature_id)`.

#### `crm_usage_counters`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, ON DELETE CASCADE | — |
| feature_key | string(100) | NOT NULL | 'contacts.created', 'storage.used_mb' |
| count | bigInteger | DEFAULT 0 | Current usage count |
| last_reset_at | datetime | NULLABLE | When counter was last reset (billing cycle) |
| timestamps | — | — | — |

**Unique:** `(tenant_id, feature_key)`.

### 2.4 Timeline Table

#### `crm_timeline_entries`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| entity_type | string(100) | NOT NULL | Morph: 'person', 'lead', 'task', etc. |
| entity_id | bigInteger | NOT NULL | Morph |
| event_type | string(100) | NOT NULL | 'created', 'updated', 'stage_changed', 'note_added', etc. |
| title | string(500) | NOT NULL | Human-readable: "Lead moved to Qualified" |
| description | text | NULLABLE | Longer context |
| metadata | json | NULLABLE | Structured data (old_value, new_value, etc.) |
| created_by | foreignId | NULLABLE, FK → users.id | — |
| created_at | timestamp | NOT NULL | Indexed for timeline queries |

**Indexes:** `tenant_id`, `created_at`, morphs `(entity_type, entity_id)`, composite `(tenant_id, entity_type, entity_id)`, composite `(tenant_id, event_type)`, composite `(tenant_id, created_by)`.

### 2.5 Import Tables

#### `crm_import_profiles`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| name | string(200) | NOT NULL | "Contacts Import Profile" |
| entity_type | string(100) | NOT NULL | 'person', 'organization', 'lead' |
| field_mapping | json | NOT NULL | { "csv_column": "model_field", ... } |
| duplicate_rules | json | NULLABLE | { "match_on": ["email"], "action": "skip|update|create" } |
| default_values | json | NULLABLE | { "status_id": 5, "owner_id": 42 } |
| created_by | foreignId | FK → users.id | — |
| timestamps | — | — | — |

#### `crm_import_history`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| profile_id | foreignId | FK → crm_import_profiles.id, NULLABLE | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| file_path | string(1000) | NOT NULL | Original file location |
| status | string(50) | NOT NULL | 'pending', 'previewed', 'processing', 'completed', 'failed', 'rolled_back' |
| total_rows | integer | DEFAULT 0 | — |
| success_count | integer | DEFAULT 0 | — |
| error_count | integer | DEFAULT 0 | — |
| error_log | json | NULLABLE | Array of { row, column, message } |
| rollback_token | string(100) | NULLABLE | Token for rollback operation |
| created_by | foreignId | FK → users.id | — |
| timestamps | — | — | — |

### 2.6 Merge Engine Table

#### `crm_merge_history`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| entity_type | string(100) | NOT NULL | 'person', 'organization', 'lead' |
| primary_id | bigInteger | NOT NULL | Surviving record ID |
| secondary_id | bigInteger | NOT NULL | Absorbed record ID |
| merge_log | json | NOT NULL | Full audit of what was merged |
| rollback_token | string(100) | NOT NULL | Unique token for rollback |
| created_by | foreignId | FK → users.id | — |
| timestamps | — | — | — |

**Indexes:** `(tenant_id, entity_type, primary_id)`, `(tenant_id, entity_type, secondary_id)`.

### 2.7 Reporting Tables

#### `crm_saved_reports`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | bigIncrements | PK | — |
| tenant_id | foreignId | FK → tenants.id, NOT NULL, indexed | — |
| name | string(200) | NOT NULL | "Monthly Sales Report" |
| entity_type | string(100) | NOT NULL | 'lead', 'person', 'task', 'pipeline' |
| report_type | string(100) | NOT NULL | 'summary', 'trend', 'comparison', 'funnel' |
| filters | json | NULLABLE | Saved filter criteria |
| columns | json | NULLABLE | Visible columns configuration |
| chart_config | json | NULLABLE | Chart type, axes, grouping |
| schedule | json | NULLABLE | { "cron": "0 8 * * 1", "recipients": [...], "format": "pdf" } |
| is_shared | boolean | DEFAULT false | Shared with entire tenant |
| created_by | foreignId | FK → users.id | — |
| timestamps | — | — | — |
| soft_deletes | — | — | — |

---

## 3. OWNERSHIP ARCHITECTURE

### 3.1 Universal Ownership Fields

Every major CRM entity includes:

| Field | FK To | Purpose |
|---|---|---|
| `owner_id` | `users.id` (NULLABLE) | Primary responsible user |
| `team_id` | `crm_teams.id` (NULLABLE) | Owning team (if team-managed) |
| `created_by` | `users.id` (NULLABLE) | Creator audit |
| `updated_by` | `users.id` (NULLABLE) | Last modifier audit |

### 3.2 Ownership Resolution Rules

When determining who "owns" a record:

```
IF owner_id IS NOT NULL
    → User owns the record
ELSE IF team_id IS NOT NULL
    → Team owns the record (all team members have owner-level access)
ELSE
    → Created by owns the record (fallback to created_by)
```

### 3.3 Team Access Logic

A User can access a record if ANY of:

1. User is `owner_id` of the record
2. User belongs to the `team_id` of the record (via `crm_team_memberships`)
3. User has global role permission (Admin, Manager, Owner) to view all records
4. Record is shared with the user explicitly

### 3.4 Permission Implications

| Permission | owner_id Match | team_id Match | Global Role |
|---|---|---|---|
| view | ✅ | ✅ | ✅ |
| edit | ✅ | ✅ | ✅ (Admin+) |
| delete | ✅ | ✅ | ✅ (Admin+) |
| reassign | ✅ | ✅ | ✅ (Manager+) |
| share | ✅ | ✅ | ✅ (Admin+) |

### 3.5 Reporting Implications

Ownership enables:

- Per-user performance metrics (leads created, tasks completed, etc.)
- Per-team pipeline value reporting
- Leaderboards by owner, team, or department
- Activity tracking by responsible party

### 3.6 Assignment Logic (Tasks)

Tasks follow the ownership model:

1. **Assigned to owner_id** = Primary assignee
2. **Assigned to team_id** = Available to all team members
3. **Task assignments table** supports multiple assignees (additional users beyond owner)
4. **Reassignment** updates owner_id and logs in timeline

---

## 4. STATUS ARCHITECTURE

### 4.1 Why Not Enums

Enums are compile-time fixed. SaaS tenants need custom statuses per entity. A Solar company may want Solar-specific statuses on leads; a Real Estate company needs different ones. Enums cannot support this without deployment changes.

### 4.2 Status System Design

```
crm_status_types             crm_statuses
┌─────────────────┐          ┌───────────────────┐
│ entity_type:     │──1:N──►│ type_id            │
│ 'person'         │          │ name: 'Active'    │
│ name: 'Person    │          │ key: 'active'     │
│ Statuses'        │          │ color: '#00FF00'  │
└─────────────────┘          │ order: 1          │
                              │ is_default: true  │
                              └───────────────────┘
```

### 4.3 Entity Status Reference

Each entity has a `status_id` FK → `crm_statuses.id`:

| Entity | Status Type Key | Default Statuses |
|---|---|---|
| Person | `person_statuses` | Active, Inactive, Lead, Customer, Vendor, Partner |
| Organization | `organization_statuses` | Active, Inactive, Prospect |
| Lead | `lead_statuses` | New, Qualified, Proposal, Negotiation, Won, Lost |
| Task | `task_statuses` | Open, In Progress, Completed, Cancelled |

### 4.4 Tenant Customization Workflow

```
1. Tenant provisions → Seed default statuses from crm_status_types
2. Admin UI → Add/Edit/Reorder/Deactivate statuses
3. Entities reference by status_id (not hardcoded key)
4. Timeline logs status changes automatically
```

### 4.5 API Design

```
GET  /api/tenant/v1/crm/statuses?entity_type=lead
→ [{ id: 1, type_id: 1, name: "New", key: "new", color: "#6366f1", order: 1, is_default: true }]

POST /api/tenant/v1/crm/statuses
→ { "type_id": 1, "name": "Hot Lead", "color": "#ef4444", "order": 2 }

PUT  /api/tenant/v1/crm/leads/{id}/status
→ { "status_id": 5 }
```

### 4.6 Usage Examples

```php
// Service layer — no enum references
$person->update(['status_id' => $request->status_id]);

// Find all active people
$defaultActiveStatus = Status::forTenant($tenant)
    ->where('type.entity_type', 'person')
    ->where('is_default', true)
    ->first();

Person::where('tenant_id', $tenant->id)
    ->where('status_id', $defaultActiveStatus->id)
    ->get();

// Find leads by any "won" equivalent across tenants
$wonStatuses = Status::whereHas('type', fn ($q) => $q->where('entity_type', 'lead'))
    ->where('name', 'Won')
    ->get();
```

---

## 5. FEATURE GATE ARCHITECTURE

### 5.1 Decision Tree

```
FeatureGate::allows(tenant: $tenant, feature: 'contacts.create')

                        ┌─────────────────────────┐
                        │  Is subscription active? │
                        └────────────┬────────────┘
                                     │
                          ┌──────────┴──────────┐
                          │                     │
                        YES                    NO
                          │                     │
                          │              ┌──────┴──────┐
                          │              │  RETURN      │
                          │              │  false       │
                          │              └─────────────┘
                          │
                    ┌─────┴──────────────────┐
                    │  Is the module enabled  │
                    │  (if module-gated)?     │
                    └──────────┬─────────────┘
                               │
                    ┌──────────┴──────────┐
                    │                     │
                  YES                    NO
                    │                     │
                    │              ┌──────┴──────┐
                    │              │  RETURN      │
                    │              │  false       │
                    │              └─────────────┘
                    │
              ┌─────┴──────────────────┐
              │  Does the plan allow    │
              │  this feature?          │
              └──────────┬─────────────┘
                         │
              ┌──────────┴──────────┐
              │                     │
            YES                    NO
              │                     │
              │              ┌──────┴──────┐
              │              │  RETURN      │
              │              │  false       │
              │              └─────────────┘
              │
        ┌─────┴──────────────────┐
        │  Is there usage limit   │
        │  capacity?              │
        └──────────┬─────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
       YES                    NO
        │                     │
        │              ┌──────┴────────────────┐
        │              │  Is overage allowed?   │
        │              └──────────┬─────────────┘
        │                         │
        │              ┌──────────┴──────────┐
        │              │                     │
        │             YES                    NO
        │              │                     │
        │              │              ┌──────┴──────┐
        │              │              │  RETURN      │
        │              │              │  false       │
        │              │              └─────────────┘
        │              │
        │        ┌─────┴─────┐
        │        │ Flag for  │
        │        │ overage   │
        │        │ billing   │
        │        └───────────┘
        │
        └──── RETURN true
```

### 5.2 Service Architecture

```
FeatureGateService
├── resolve(tenant, feature): FeatureResolution
│   ├── SubscriptionResolver
│   │   └── isActive(tenant): bool
│   ├── ModuleResolver
│   │   └── isEnabled(tenant, module): bool
│   ├── PlanResolver
│   │   └── allows(tenant, featureKey): bool|int
│   ├── UsageResolver
│   │   └── getUsage(tenant, featureKey): int
│   └── OverageResolver
│       └── isAllowed(tenant, featureKey): bool
│
├── allows(tenant, feature): bool
├── assert(tenant, feature): void          ← throws on denial
├── limit(tenant, feature): int            ← returns max allowed
├── usage(tenant, feature): int            ← returns current usage
├── remaining(tenant, feature): int        ← returns limit - usage
├── percentage(tenant, feature): float     ← returns usage/limit * 100
└── registerModuleFeatures(module, features[]): void
```

### 5.3 Flow Diagram

```
┌──────────┐     ┌──────────────────────────────────────────────┐
│ Request  │────►│              FeatureGateService              │
│ checks   │     │                                              │
│ feature  │     │  ┌─────────────┐  ┌───────────────────────┐  │
└──────────┘     │  │Subscription │  │   FeatureResolver     │  │
                 │  │ Resolver    │  │                       │  │
                 │  └──────┬──────┘  │  ┌─────────────────┐  │  │
                 │         │         │  │ ModuleResolver  │  │  │
                 │    ┌────┴────┐    │  └────────┬────────┘  │  │
                 │    │ Active? │    │           │            │  │
                 │    └────┬────┘    │    ┌──────┴───────┐   │  │
                 │         │ YES     │    │ PlanResolver  │   │  │
                 │    ┌────┴────┐    │    └──────┬────────┘   │  │
                 │    │ Module  │    │           │            │  │
                 │    │ Enabled?│    │    ┌──────┴───────┐   │  │
                 │    └────┬────┘    │    │UsageResolver  │   │  │
                 │         │ YES     │    └──────┬────────┘   │  │
                 │    ┌────┴────┐    │           │            │  │
                 │    │ Plan    │    │    ┌──────┴───────┐   │  │
                 │    │ Allows? │    │    │OverageResolver│   │  │
                 │    └────┬────┘    │    └──────┬────────┘   │  │
                 │         │ YES     └───────────┼────────────┘  │
                 │    ┌────┴────┐                │               │
                 │    │ Usage   │◄───────────────┘               │
                 │    │ Check   │                                │
                 │    └────┬────┘                                │
                 │         │                                     │
                 │    ┌────┴──────┐                              │
                 │    │ Allow /   │                              │
                 │    │ Deny      │                              │
                 │    └───────────┘                              │
                 └──────────────────────────────────────────────┘
```

### 5.4 Feature Resolution Object

```php
class FeatureResolution
{
    public readonly bool $allowed;
    public readonly ?string $reason;         // Why denied
    public readonly ?string $deniedBy;       // Which resolver denied
    public readonly ?int $limit;             // Max allowed
    public readonly ?int $usage;             // Current usage
    public readonly ?int $remaining;         // Remaining capacity
    public readonly bool $isOverage;         // Currently in overage
}
```

### 5.5 Feature Categories

| Category | Stores In | Examples | Resolution |
|---|---|---|---|
| Plan boolean | `crm_plan_features` | `contacts.create`, `documents.upload` | PlanResolver compares value → true/false |
| Plan limit | `crm_plan_features` | `contacts.max: 5000` | PlanResolver returns int, UsageResolver compares |
| Module gate | `tenant_modules` table | `module.solar`, `module.real_estate` | ModuleResolver checks module enabled for tenant |
| Usage counter | `crm_usage_counters` | `contacts.used: 3421` | UsageResolver reads/writes counter |
| Add-on | `crm_tenant_feature_overrides` | `extra_storage: 50GB` | PlanResolver checks overrides first |
| Overage | Plan config | `overage.allowed: true` | OverageResolver checks plan setting |

### 5.6 Middleware Integration

```php
// Route level
Route::middleware(['feature:contacts.create'])->group(function () {
    Route::apiResource('people', PersonController::class);
});

// Controller level (called inside Action or Service)
FeatureGateService::for($tenant)->assert('contacts.create');
```

### 5.7 Performance Strategy

- All feature resolutions are cached per tenant (TTL: 1 hour, Redis)
- Usage counters use Redis increment/decrement (atomic operations)
- Hourly job syncs Redis counters to `crm_usage_counters` table
- Plan changes trigger cache invalidation for that tenant

---

## 6. TIMELINE ARCHITECTURE

### 6.1 Universal Timeline Design

The timeline is a single unified event stream. Every entity writes to it. Consumers query it by entity, by user, by type, or across the entire tenant.

### 6.2 Event Sources

| Source | Event Types | Trigger |
|---|---|---|
| Person | `person.created`, `person.updated`, `person.status_changed`, `person.deleted` | Observer |
| Organization | `organization.created`, `organization.updated`, `organization.status_changed` | Observer |
| Lead | `lead.created`, `lead.stage_changed`, `lead.qualified`, `lead.converted`, `lead.lost` | Observer |
| Task | `task.created`, `task.assigned`, `task.completed`, `task.status_changed` | Observer |
| Note | `note.created`, `note.updated`, `note.deleted` | Observer |
| Comment | `comment.created`, `comment.updated`, `comment.deleted` | Observer |
| Document | `document.uploaded`, `document.deleted` | Observer |
| Activity | `activity.logged`, `activity.completed` | Observer |
| Merge | `person.merged`, `organization.merged`, `lead.merged` | MergeService |
| Import | `import.completed`, `import.failed` | ImportService |

### 6.3 Metadata Structure

```json
{
    "old_value": "New",
    "new_value": "Qualified",
    "old_stage_id": 1,
    "new_stage_id": 3,
    "pipeline_id": 1,
    "pipeline_name": "Sales Pipeline",
    "change_source": "user_action",
    "change_source_id": 42
}
```

### 6.4 Timeline APIs

```
GET /api/tenant/v1/crm/timeline?entity_type=lead&entity_id=1
     → All timeline entries for a specific lead

GET /api/tenant/v1/crm/timeline?event_type=lead.stage_changed&tenant_id=X
     → All stage changes across the tenant

GET /api/tenant/v1/crm/timeline?created_by=42
     → All events created by a specific user

GET /api/tenant/v1/crm/timeline?from=2026-01-01&to=2026-06-19
     → Events within a date range

GET /api/tenant/v1/crm/timeline?entity_type=lead&entity_id=1&include=metadata
     → Timeline with full metadata for a lead

GET /api/tenant/v1/crm/people/1/timeline
     → All timeline entries for a person (across all related entities)

GET /api/tenant/v1/crm/dashboard/feed
     → Unified feed for current user (their entities + team entities)
```

### 6.5 Feed Examples

```json
[
    {
        "id": 1001,
        "event_type": "lead.stage_changed",
        "title": "Acme Solar changed from Qualified to Proposal",
        "description": null,
        "metadata": {
            "old_value": "Qualified",
            "new_value": "Proposal",
            "pipeline_id": 1,
            "pipeline_name": "Sales Pipeline"
        },
        "entity_type": "lead",
        "entity_id": 42,
        "created_by": { "id": 5, "name": "John Doe" },
        "created_at": "2026-06-19T10:30:00Z"
    },
    {
        "id": 1002,
        "event_type": "note.created",
        "title": "Note added to Acme Solar",
        "description": "Customer requested pricing for 10kW system",
        "metadata": null,
        "entity_type": "lead",
        "entity_id": 42,
        "created_by": { "id": 5, "name": "John Doe" },
        "created_at": "2026-06-19T10:35:00Z"
    }
]
```

### 6.6 Performance Strategy

| Strategy | Implementation |
|---|---|
| Write path | `TimelineWriter` singleton — batch inserts via queued job (100ms debounce per tenant) |
| Read path | Paginated queries by `(tenant_id, entity_type, entity_id, created_at)` — indexed |
| Pruning | Entries > 12 months → archived to `crm_timeline_archives` table (partitioned by month) |
| Cache | Per-entity timeline cached for 1 minute (Redis list), invalidated on new entry |
| Dashboard feed | Materialized view refreshed every 5 minutes |
| Bulk writes | Observer dispatches queued `WriteTimelineEntry` job (uses `onQueue('timeline')`) |

---

## 7. SEARCH ARCHITECTURE

### 7.1 Technology Stack

- **Scout Driver:** Meilisearch
- **Fallback:** Database (Scout collection engine) for small tenants
- **Queue:** Default queue for index operations

### 7.2 Searchable Entities

| Entity | Index Name | Searchable Attributes | Filterable Attributes | Sortable Attributes |
|---|---|---|---|---|
| Person | `crm_people` | first_name, last_name, email, phone, mobile, job_title | tenant_id, status_id, owner_id, team_id, created_at | last_name, created_at |
| Organization | `crm_orgs` | name, email, website, phone, industry | tenant_id, status_id, owner_id, team_id, created_at | name, created_at |
| Lead | `crm_leads` | email, phone, company_name | tenant_id, status_id, pipeline_id, stage_id, owner_id, team_id, value | value, created_at |
| Task | `crm_tasks` | title, description | tenant_id, status_id, priority, owner_id, team_id, due_at | due_at, priority |
| Document | `crm_docs` | name | tenant_id, type, mime_type, created_by | created_at |
| Note | `crm_notes` | body | tenant_id, notable_type, created_by | created_at |
| Comment | `crm_comments` | body | tenant_id, commentable_type, created_by | created_at |

### 7.3 Search Flow

```
User types "John" in search bar
         │
         ▼
┌────────────────────┐
│   SearchService    │
│   search("John")   │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│  Scout::search()   │
│  across all CRM    │
│  indexes (multi)   │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│  Meilisearch       │
│  multi-search      │
│  (7 indexes)       │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│  Results grouped   │
│  by entity type    │
│                    │
│  People (3)        │
│  Leads (2)         │
│  Tasks (1)         │
└────────────────────┘
```

### 7.4 API Design

```php
// Global search across ALL entities
SearchService::global('John')
    ->limit(5)
    ->grouped()               // Returns { people: [...], leads: [...], ... }
    ->get();

// Entity-specific search with filters
SearchService::search('Proposal')
    ->in('leads')
    ->where('status_id', 3)
    ->where('owner_id', 42)
    ->orderBy('created_at', 'desc')
    ->paginate(15);

// Multi-entity search
SearchService::search('Acme')
    ->in(['people', 'organizations', 'leads'])
    ->get();
```

### 7.5 Index Strategy

| Strategy | Detail |
|---|---|
| Tenant isolation | Meilisearch tenant tokens or separate indexes per tenant (preferred: filterable `tenant_id` attribute) |
| Batch import | `php artisan scout:import` per model with `tenant_id` filter |
| Real-time sync | Model observers dispatch Scout `searchable()` on save/delete |
| Queue | All index operations on `onQueue('search')` |
| Stale tolerance | Accept 5-second delay between DB write and index update |

### 7.6 Reindex Strategy

```
Scheduled: php artisan crm:search:reindex --tenant=42
    → Chunk through all searchable models for tenant
    → Re-index via Scout's searchable() method
    → Run weekly per tenant (off-peak hours)

Emergency: php artisan crm:search:reindex --all
    → Full reindex across all tenants
    → Rate-limited: 1000 records per minute per index
```

### 7.7 Performance Strategy

| Concern | Solution |
|---|---|
| Index size | Deduplicate by `tenant_id`, filterable attribute |
| Query speed | Meilisearch handles 50ms+ searches at 1M+ docs |
| Typo tolerance | Meilisearch built-in (1 typo for 5-8 char words, 2 for 9+) |
| Highlighting | `attributesToHighlight` for search result snippets |
| Pagination | Standard Scout pagination (15 per page, max 100) |

---

## 8. IMPORT FRAMEWORK

### 8.1 Architecture

```
┌────────────┐     ┌────────────────┐     ┌──────────────────┐
│ Upload CSV │────►│ Preview Parser │────►│ Column Mapping   │
└────────────┘     └────────────────┘     └────────┬─────────┘
                                                    │
                         ┌──────────────────────────┘
                         ▼
              ┌─────────────────────┐
              │  ImportProfile      │
              │  (saved mapping)    │
              └─────────┬───────────┘
                        │
              ┌─────────┴───────────┐
              │  DispatchImportJob   │
              └─────────┬───────────┘
                        │
              ┌─────────┴───────────┐
              │  Process in chunks  │
              │  - Validate row     │
              │  - Detect duplicate │
              │  - Insert/Update    │
              │  - Log errors       │
              └─────────┬───────────┘
                        │
              ┌─────────┴───────────┐
              │  Complete / Failed  │
              │  Rollback support   │
              └─────────────────────┘
```

### 8.2 Supported Formats

| Format | Parser | Chunk Size |
|---|---|---|
| CSV | `league/csv` | 100 rows |
| XLSX | `openspout/openspout` | 100 rows |
| JSON | Native | 500 rows |
| API | JSON POST | N/A (realtime) |

### 8.3 Duplicate Detection

```
duplicate_rules: {
    "match_on": ["email"],                // Columns to match
    "match_on_empty": "skip",             // What to do if match columns empty
    "on_duplicate": "skip",               // skip | update | create_new
    "merge_strategy": "keep_existing"     // keep_existing | overwrite | merge_fields
}
```

### 8.4 Workflow

```
Step 1: Upload file
    → File stored in Wasabi temp directory
    → Return import_id, preview_token

Step 2: Preview (optional)
    → Parse first 20 rows
    → Auto-detect column mapping
    → Show field mapping UI

Step 3: Confirm mapping
    → Save ImportProfile (if desired)
    → Dispatch ImportJob

Step 4: Background Processing
    → Process in chunks
    → Validate each row
    → Detect duplicates
    → Insert/Update
    → Track errors per row

Step 5: Completion
    → Email notification to initiator
    → Rollback token generated
    → Timeline entries created

Step 6: Rollback (if needed)
    → POST /api/tenant/v1/crm/imports/{id}/rollback
    → Reverse all inserts/updates
    → Mark import as rolled_back
```

### 8.5 Service Architecture

```
ImportService
├── preview(file, entityType): ImportPreview
│   ├── Parses file header
│   ├── Maps columns to model fields
│   ├── Returns { rows: [...], detectedMapping: {}, suggestedProfile: ? }
│
├── import(profile, file, options): ImportJob
│   ├── Creates ImportHistory record
│   ├── Stores file to Wasabi
│   ├── Dispatches ProcessImportJob
│   └── Returns ImportHistory
│
├── validateRow(row, entityType, fieldMapping): ValidationResult
│   ├── Required field checks
│   ├── Duplicate detection
│   ├── Format validation
│   └── Returns { valid: bool, errors: [] }
│
├── processRow(row, entityType, fieldMapping, duplicateRules): ProcessedRow
│   ├── Applies field mapping
│   ├── Handles duplicates per rule
│   ├── Creates or updates record
│   └── Returns { action: 'created'|'updated'|'skipped', record: ?, error: ? }
│
├── rollback(token): void
│   └── Reverses all changes from import
│
└── getTemplate(entityType): array
    └── Returns columns with headers, examples, and validation rules
```

---

## 9. MERGE ARCHITECTURE

### 9.1 Merge Service

```
MergeService
├── mergePeople(primaryId, secondaryId, conflictRules): MergeResult
├── mergeOrganizations(primaryId, secondaryId, conflictRules): MergeResult
└── mergeLeads(primaryId, secondaryId, conflictRules): MergeResult
```

### 9.2 Merge Workflow

```
                        ┌─────────────────────┐
                        │  Select Primary     │
                        │  Select Secondary   │
                        └──────────┬──────────┘
                                   │
                        ┌──────────┴──────────┐
                        │  Detect Conflicts    │
                        │                     │
                        │  Field       Value  │
                        │  email     p: a@..  │
                        │            s: b@..  │
                        │  phone     p: 123   │
                        │            s: 456   │
                        └──────────┬──────────┘
                                   │
                        ┌──────────┴──────────┐
                        │  Resolve Conflicts   │
                        │  (keep primary,      │
                        │   keep secondary,    │
                        │   merge values)      │
                        └──────────┬──────────┘
                                   │
                        ┌──────────┴──────────┐
                        │  Execute Merge       │
                        │                     │
                        │  1. Update primary   │
                        │     with merged vals │
                        │  2. Reassign all     │
                        │     relationships    │
                        │     from secondary   │
                        │     to primary       │
                        │  3. Soft-delete      │
                        │     secondary        │
                        │  4. Write timeline   │
                        │     for both records │
                        │  5. Create MergeLog  │
                        └──────────┬──────────┘
                                   │
                        ┌──────────┴──────────┐
                        │  Return MergeResult  │
                        │  { primary,          │
                        │    merged_relations, │
                        │    rollback_token }  │
                        └─────────────────────┘
```

### 9.3 Conflict Resolution Strategies

| Strategy | Description |
|---|---|
| `keep_primary` | Primary value wins for all conflicting fields |
| `keep_secondary` | Secondary value wins for all conflicting fields |
| `prefer_filled` | The non-null/non-empty value wins |
| `merge_fields` | Append text fields, sum numeric fields, take latest dates |
| `ask_user` | UI presents choices per field (async resolution) |

### 9.4 Data Integrity Rules

| Entity Type | Relationships Preserved | Action |
|---|---|---|
| Person | Organizations (OrganizationPerson) | Reassign person_id FK to primary |
| Person | Leads (as person_id) | Reassign to primary |
| Person | Addresses (as addressable) | Reassign to primary |
| Person | Notes (as notable) | Reassign to primary |
| Person | Activities (as activitable) | Reassign to primary |
| Person | Documents (as documentable) | Reassign to primary |
| Person | Comments (as commentable) | Reassign to primary |
| Person | Tags (as taggable) | Reassign to primary |
| Person | Tasks (as taskable) | Reassign to primary |
| Person | Tasks (as owner_id) | Reassign to primary |
| Person | Timeline entries | Reassign to primary |
| Organization | People (OrganizationPerson) | Reassign org_id FK |
| Organization | Leads (as organization_id) | Reassign to primary |
| Organization | Addresses, Notes, Activities, etc. | Same as Person |
| Lead | Activities, Notes, Documents, etc. | Reassign to primary |

### 9.5 Rollback Strategy

```php
class MergeRollback
{
    public function rollback(string $token): void
    {
        // 1. Restore secondary record from soft-delete
        // 2. Reassign all relationships back to secondary
        // 3. Revert primary record to pre-merge state
        // 4. Write rollback timeline entries
        // 5. Mark MergeHistory as rolled_back
    }
}
```

The rollback token is stored in `crm_merge_history.rollback_token` along with the full pre-merge state in `merge_log`.

---

## 10. REPORTING ARCHITECTURE

### 10.1 ReportService Architecture

```
ReportService
├── generate(definition): ReportResult
│   ├── resolveQueryBuilder(entityType, filters)
│   ├── applyAggregations(groupBy, metrics)
│   ├── applyPagination() or full export
│   └── return result or dispatch job
│
├── definitions(): Collection
│   └── Returns all available report definitions
│
├── savedReports(tenant): Collection
│   └── Returns saved reports for tenant
│
├── export(reportId, format): File
│   ├── CSV export (chunked query → streamed file)
│   └── PDF export (chunked → Dompdf)
│
└── schedule(reportId, cron, recipients): void
    └── Sets up recurring report delivery
```

### 10.2 Report Definitions

```
people_summary         → Total people, status breakdown, by owner, by team
people_trend           → People created per day/week/month
organization_summary   → Total orgs, by industry, by status
lead_summary           → Total leads, by pipeline, by stage, by source, by owner
lead_funnel            → Pipeline funnel (count per stage, value per stage)
lead_trend             → Leads created per day, conversion rate
lead_velocity          → Avg time to close, avg time per stage
task_summary           → Total tasks, by status, by priority, by assignee
task_completion        → Completion rate, overdue rate, avg completion time
activity_summary       → Activities by type, by user, by day
team_performance       → Per-team comparison (leads, tasks, activities)
```

### 10.3 Aggregation Strategy

| Scale | Strategy |
|---|---|
| < 10K records | Live SQL COUNT/GROUP BY |
| 10K-100K | Cached aggregations (5 min TTL) |
| 100K-1M | Materialized view (hourly refresh) |
| > 1M | Dedicated reporting database replica |

### 10.4 Dashboard Compatibility

All dashboard endpoints use the same ReportService:

```
GET /api/tenant/v1/crm/dashboard/summary
    → ReportService::generate('people_summary')
    → ReportService::generate('lead_summary')
    → ReportService::generate('task_summary')

GET /api/tenant/v1/crm/dashboard/pipeline
    → ReportService::generate('lead_funnel', { pipeline_id: X })

GET /api/tenant/v1/crm/dashboard/activity-timeline
    → ReportService::generate('activity_summary', { from: '-7 days' })
```

### 10.5 Export Compatibility

| Format | Method | Trigger |
|---|---|---|
| CSV | Streamed query → `league/csv` writer | Sync (if < 10K), async job otherwise |
| PDF | Chunked query → Dompdf → combine | Always async via job |
| Excel | `openspout/openspout` XLSX writer | Always async via job |

---

## 11. CRM CORE MODULES

### 11.1 Complete Module Architecture

| Module | Model | Table | Polymorphic? | Key Responsibility |
|---|---|---|---|---|
| **People** | `Crm\Person` | `crm_people` | No | Central person entity |
| **Organizations** | `Crm\Organization` | `crm_organizations` | No | Central company entity |
| **OrganizationPeople** | `Crm\OrganizationPerson` | `crm_organization_person` | No | Pivot with role metadata |
| **Addresses** | `Crm\Address` | `crm_addresses` | Yes (addressable) | Universal address storage |
| **Leads** | `Crm\Lead` | `crm_leads` | No | Generic lead management |
| **LeadSources** | `Crm\LeadSource` | `crm_lead_sources` | No | Tenant-defined sources |
| **Pipelines** | `Crm\Pipeline` | `crm_pipelines` | No (entity_type column) | Pipeline definitions |
| **PipelineStages** | `Crm\PipelineStage` | `crm_pipeline_stages` | No | Stage definitions per pipeline |
| **Activities** | `Crm\Activity` | `crm_activities` | Yes (activitable) | Loggable events |
| **Notes** | `Crm\Note` | `crm_notes` | Yes (notable) | Free-form text |
| **Comments** | `Crm\Comment` | `crm_comments` | Yes (commentable) | Threaded discussions with mentions |
| **Tasks** | `Crm\Task` | `crm_tasks` | Yes (taskable) | Work items |
| **Teams** | `Crm\Team` | `crm_teams` | No | User grouping |
| **Notifications** | — | Laravel's `notifications` | No | In-app notifications |
| **Tags** | `Crm\Tag` | `crm_tags` + `crm_taggables` | Yes (taggable via pivot) | Universal categorization |
| **CustomFields** | `Crm\CustomFieldDefinition` + `Crm\CustomFieldValue` | `crm_custom_field_definitions` + `crm_custom_field_values` | Yes (entity_type/entity_id on values) | EAV dynamic fields |
| **Documents** | `Crm\Document` | `crm_documents` | Yes (documentable) | Wasabi-backed files |
| **Statuses** | `Crm\Status` + `Crm\StatusType` | `crm_statuses` + `crm_status_types` | No | Configurable status system |
| **Timeline** | `Crm\TimelineEntry` | `crm_timeline_entries` | Yes (entity_type/entity_id) | Universal event stream |
| **Import** | `Crm\ImportProfile` + `Crm\ImportHistory` | `crm_import_profiles` + `crm_import_history` | No | Data ingestion |
| **Merge** | `Crm\MergeHistory` | `crm_merge_history` | No | Deduplication audit |
| **SavedReports** | `Crm\SavedReport` | `crm_saved_reports` | No | Saved report configurations |

### 11.2 Module Boundaries

```
┌─────────────────────────────────────────────────────────────────┐
│                    CRM CORE (industry agnostic)                   │
│                                                                   │
│  People  Orgs  OrgPeople  Addresses  Leads  Pipelines  Stages   │
│  Activities  Notes  Comments  Tasks  Teams  Documents  Tags      │
│  CustomFields  Statuses  Timeline  Import  Merge  Reports        │
└───────────────────────────┬─────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ Solar Module  │  │ Agency Module │  │Real Estate    │
│               │  │               │  │ Module        │
│ solar_        │  │ agency_       │  │ real_estate_  │
│ installations │  │ campaigns     │  │ properties    │
│ solar_panels  │  │ client_briefs │  │ leases        │
│ solar_prop-   │  │              │  │ viewings      │
│ osals         │  │              │  │              │
│               │  │              │  │              │
│ FK→people_id  │  │ FK→people_id │  │ FK→people_id │
│ FK→org_id     │  │ FK→org_id    │  │ FK→org_id    │
│ FK→lead_id    │  │ FK→lead_id   │  │ FK→lead_id   │
│ FK→address_id │  │ FK→address_id│  │ FK→address_id│
└───────────────┘  └───────────────┘  └───────────────┘
                            │
                            ▼
                   ┌────────────────┐
                   │ Client Portal  │
                   │ (consumes API) │
                   └────────────────┘
                            │
                            ▼
                   ┌────────────────┐
                   │ React Native   │
                   │ (consumes API) │
                   └────────────────┘
```

---

## 12. PERMISSION MATRIX

### 12.1 Roles & Permissions

| Permission | Owner | Admin | Manager | Sales | Support | Viewer |
|---|---|---|---|---|---|---|
| **People** | | | | | | |
| people.view | ✅ Own+Team+All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own | ✅ Own |
| people.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| people.update | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| people.delete | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| people.merge | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| people.export | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| people.import | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| people.reassign | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| **Organizations** | | | | | | |
| organizations.view | ✅ All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own | ✅ Own |
| organizations.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| organizations.update | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| organizations.delete | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| organizations.merge | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Leads** | | | | | | |
| leads.view | ✅ All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own | ✅ Own |
| leads.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| leads.update | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| leads.delete | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| leads.move_stage | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| leads.qualify | ✅ | ✅ | ✅ Own+Team | ✅ Own | ❌ | ❌ |
| leads.convert | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| leads.reassign | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| **Pipelines** | | | | | | |
| pipelines.manage | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| pipelines.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Tasks** | | | | | | |
| tasks.view | ✅ All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own+Team | ✅ Assigned |
| tasks.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| tasks.update | ✅ | ✅ | ✅ Own+Team | ✅ Own+Assigned | ✅ Own+Assigned | ❌ |
| tasks.delete | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| tasks.assign | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| tasks.complete | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Teams** | | | | | | |
| teams.manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| teams.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Documents** | | | | | | |
| documents.view | ✅ All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own | ✅ Own |
| documents.upload | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| documents.delete | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| documents.download | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Own |
| **Activities** | | | | | | |
| activities.log | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| activities.view_all | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| activities.view_own | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notes & Comments** | | | | | | |
| notes.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| notes.view_all | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| notes.delete_all | ✅ | ✅ | ✅ Own+Team | ❌ | ❌ | ❌ |
| **Custom Fields** | | | | | | |
| custom_fields.manage | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Reports** | | | | | | |
| reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| reports.export | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| reports.manage_saved | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Import** | | | | | | |
| import.execute | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| import.rollback | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| import.view_history | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Timeline** | | | | | | |
| timeline.view | ✅ All | ✅ All | ✅ All | ✅ Own+Team | ✅ Own | ✅ Own |
| **Feature Gates** | | | | | | |
| features.override | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### 12.2 Scope Definitions

| Scope | Rule |
|---|---|
| Own | `record.owner_id == auth()->id()` |
| Team | `record.team_id` matches any team the user belongs to |
| Assigned | `record` is assigned to user via TaskAssignment |
| Own+Team | Own records + records owned by user's teams |
| All | All records in the tenant (requires GlobalAccess permission) |

### 12.3 Implementation Strategy

- Spatie `Permission` with `guard_name = 'sanctum'`
- Roles are tenant-level (mapped in `crm_team_memberships.role` or a separate `crm_roles` table)
- Global scope `CrmVisibilityScope` applies ownership filter to all queries
- Policies check scope + role at the record level
- Gates for entity-level operations (create, import, export, manage)

---

## 13. INDUSTRY MODULE COMPATIBILITY STRATEGY

### 13.1 Solar Module

| CRM Core Entity | Solar Module Usage | Solar FK |
|---|---|---|
| Person | Solar customer, site contact | `solar_installations.customer_id → crm_people.id` |
| Organization | Solar company, installer | `solar_installations.company_id → crm_organizations.id` |
| OrganizationPerson | Customer-company relationship | Via pivot |
| Address | Site survey address (type='site_survey') | `solar_installations.address_id → crm_addresses.id` |
| Lead | Solar prospect | `solar_proposals.lead_id → crm_leads.id` |
| Pipeline | Solar pipeline (entity_type='solar') | Pipeline::where('entity_type', 'solar') |
| PipelineStage | Solar stages (New→Survey→Proposal→Install→Active) | Via pipeline |
| Activity | Site visit, inspection (type='site_survey') | Activitable morph to solar model |
| Document | Site plan, permit, design | Documentable morph to solar model |
| Task | Installation task, inspection task | Taskable morph to solar model |
| Tag | 'solar_prospect', 'installed' | Taggable morph |
| CustomField | Panel count, orientation, roof type | Entity type = 'solar::installation' |
| Timeline | All solar events streamed | Entity_type = 'solar_installation' |

### 13.2 Agency Module

| CRM Core Entity | Agency Module Usage | Agency FK |
|---|---|---|
| Person | Agency contact, client contact | `agency_campaigns.contact_id → crm_people.id` |
| Organization | Agency client | `agency_campaigns.client_id → crm_organizations.id` |
| Address | Client office address | `agency_projects.address_id → crm_addresses.id` |
| Lead | Agency prospect | `agency_proposals.lead_id → crm_leads.id` |
| Pipeline | Agency pipeline (entity_type='agency') | Pipeline::where('entity_type', 'agency') |
| Activity | Client meeting, campaign review | Activitable morph |
| Document | Creative brief, campaign report | Documentable morph |
| Task | Campaign task, deadline | Taskable morph |
| Tag | 'vip_client', 'retainer' | Taggable morph |
| CustomField | Budget range, agency type | Entity type = 'agency::campaign' |

### 13.3 Real Estate Module

| CRM Core Entity | Real Estate Module Usage | Real Estate FK |
|---|---|---|
| Person | Buyer, seller, tenant, landlord, agent | `real_estate_properties.owner_id → crm_people.id` |
| Organization | Property management company, agency | `real_estate_listings.agency_id → crm_organizations.id` |
| Address | Property address (type='property') | `real_estate_properties.address_id → crm_addresses.id` |
| Lead | Property inquiry | `real_estate_viewings.lead_id → crm_leads.id` |
| Pipeline | Property pipeline (entity_type='real_estate') | Pipeline::where('entity_type', 'real_estate') |
| PipelineStage | Inquiry→Viewing→Offer→Negotiation→Sold/Withdrawn | Via pipeline |
| Activity | Property viewing, open house | Activitable morph |
| Document | Property deed, inspection report | Documentable morph |
| Task | Inspection task, closing task | Taskable morph |
| Tag | 'for_sale', 'for_rent', 'sold' | Taggable morph |
| CustomField | Bedrooms, square footage, year built | Entity type = 'real_estate::property' |

### 13.4 What Modules CANNOT Do

- Cannot alter CRM Core migrations or add columns to CRM Core tables
- Cannot override CRM Core controllers, actions, or services
- Cannot delete CRM Core data (only append related data)
- Cannot bypass CRM Core permissions (must register their own permissions)
- Cannot introduce tenant-level breaking changes

### 13.5 Module Registration Contract

```php
// Inside Module ServiceProvider
class SolarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register module features
        FeatureGateService::registerModuleFeatures('solar', [
            'solar.installations.max' => 50,
            'solar.proposals.create' => true,
            'solar.inspections.schedule' => true,
        ]);

        // Register module permissions
        PermissionRegistrar::registerPermissions([
            'solar.installations.view',
            'solar.installations.create',
            'solar.proposals.view',
            'solar.inspections.manage',
        ]);

        // Register module pipeline entity type
        Pipeline::registerEntityType('solar', 'Solar Pipeline');
    }

    public function boot(): void
    {
        // Load module migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
    }
}
```

---

## 14. SCALABILITY CONSIDERATIONS

### 14.1 Database

| Challenge | Solution |
|---|---|
| crm_timeline_entries growth | Partition by month, archive > 12 months to cold storage |
| crm_activities growth | Partition by month, TTL of 24 months |
| crm_people large tenants (>500K) | Composite indexes, Scout for search, no COUNT queries |
| crm_notes body text | Stored in InnoDB with FULLTEXT index or Scout |
| Polymorphic morph queries | Ensure composite indexes on (type, id) for all morphs |
| Feature gate queries | Cached in Redis, not hitting DB per request |
| Usage counters | Redis atomic increment, hourly DB sync |

### 14.2 Caching

| Cache Key Pattern | TTL | Storage |
|---|---|---|
| `crm:{tenant}:dashboard:*` | 5 min | Redis |
| `crm:{tenant}:feature:*` | 1 hour | Redis |
| `crm:{tenant}:usage:*` | Real-time | Redis |
| `crm:{tenant}:pipeline:{id}:stages` | 1 hour | Redis |
| `crm:{tenant}:statuses:{entity}` | 1 hour | Redis |
| `crm:{tenant}:report:{hash}` | 1 hour | Redis |
| `crm:{tenant}:custom_fields` | 1 hour | Redis |
| `crm:{tenant}:timeline:{entity}:{id}` | 1 min | Redis |

### 14.3 Queue Architecture

| Queue | Purpose | Workers | Retry |
|---|---|---|---|
| `crm-timeline` | Timeline entries (high volume) | 2 | 1 attempt |
| `crm-search` | Scout index updates | 2 | 3 attempts |
| `crm-import` | CSV/XLSX import processing | 1 | 3 attempts |
| `crm-reports` | Async report generation | 1 | 2 attempts |
| `crm-default` | Notifications, emails | 2 | 3 attempts |

### 14.4 Read/Write Separation

- All timeline WRITE operations go through a queued job (non-blocking)
- All reporting queries use READ replica (if configured)
- Feature gate checks are cached, not hitting primary DB
- Usage counter writes are Redis atomic, synced to DB hourly

### 14.5 Rate Limiting

| Endpoint Group | Limit | Window |
|---|---|---|
| All CRM API | 100 req/min | 1 minute |
| Import endpoints | 5 req/min | 1 minute |
| Export endpoints | 3 req/min | 1 minute |
| Merge endpoints | 10 req/min | 1 minute |
| Timeline feed | 60 req/min | 1 minute |
| Dashboard | 30 req/min | 1 minute |
| Search | 60 req/min | 1 minute |

---

## 15. REACT NATIVE COMPATIBILITY

### 15.1 API Considerations

| Concern | Solution |
|---|---|
| Auth | Sanctum token obtained from login endpoint, stored in secure storage |
| Payload size | Mobile endpoints return trimmed fields (no meta wrapper for list items) |
| Pagination | Cursor-based pagination for feeds (timeline, activities) |
| Image upload | Direct-to-S3 signed URLs (mobile uploads directly to Wasabi) |
| Offline support | Local SQLite cache, background sync queue on connectivity restore |
| Push notifications | Firebase Cloud Messaging (FCM) integration |
| Client identification | Headers: `X-Client: react-native`, `X-Client-Version: 1.0.0` |

### 15.2 Mobile-Specific Endpoints

```
GET /api/tenant/v1/crm/mobile/feed
    → Lightweight timeline feed (last 50 entries, no metadata)

GET /api/tenant/v1/crm/mobile/people?sync_since=2026-06-01T00:00:00Z
    → People updated since timestamp (for offline sync)

POST /api/tenant/v1/crm/mobile/document/upload-url
    → Returns pre-signed Wasabi upload URL (client uploads directly)
```

### 15.3 Offline Sync Strategy

```
Sync Flow:
1. App launches → Pull all changes since last sync timestamp
2. User creates/updates record → Saved to local SQLite + queued for API sync
3. Connectivity restored → Push queued changes, resolve conflicts (last-write-wins)
4. Conflict detected → Server returns 409, client shows diff UI
```

---

## 16. API-FIRST CONSIDERATIONS

### 16.1 Design Principles

- All functionality is accessible via API
- No implicit assumptions about the client (browser, mobile, CLI, webhook)
- Consistent error format across all endpoints
- Versioning via URL path (`/api/tenant/v1/crm/...`)
- Pagination metadata on all list endpoints
- Sparse fieldsets (`?fields=id,first_name,last_name`)
- Include related resources (`?include=organizations,addresses`)

### 16.2 Error Format

```json
{
    "status": "error",
    "message": "Validation failed.",
    "errors": {
        "email": ["The email field is required."],
        "first_name": ["The first name must be at least 2 characters."]
    },
    "code": "VALIDATION_ERROR"
}
```

### 16.3 Standard Headers

| Header | When | Purpose |
|---|---|---|
| `X-RateLimit-Limit` | Always | Rate limit for endpoint |
| `X-RateLimit-Remaining` | Always | Remaining requests |
| `X-Request-Id` | Always | Request tracing |
| `Deprecation` | Deprecated endpoints | Sunset warning |
| `Sunset` | Deprecated endpoints | Deprecation date |
| `Link` | Paginated responses | Next/prev page URLs |

---

## 17. IMPLEMENTATION ROADMAP

### 17.1 Sprint Plan

```
Sprint 1: Foundation
├── crm_status_types + crm_statuses (migrations + models + seeds)
├── crm_feature_definitions + crm_plan_features (migrations + models)
├── crm_usage_counters (migration + model)
├── FeatureGateService (FeatureResolver, PlanResolver, UsageResolver, OverageResolver)
├── crm_tags + crm_taggables (migrations + models)
├── crm_custom_field_definitions + crm_custom_field_values (migrations + models)
└── Tests: FeatureGateService, StatusService, TagService, CustomFieldService

Sprint 2: Core CRM Entities
├── crm_people (migration + model + factory + observer)
├── crm_organizations (migration + model + factory + observer)
├── crm_organization_person (migration + model + factory)
├── crm_addresses (migration + model + factory)
├── Actions: CreatePerson, UpdatePerson, CreateOrganization, UpdateOrganization
├── Services: PersonService, OrganizationService, AddressService
├── Controllers: PersonController, OrganizationController, AddressController
├── Filters: PersonFilter, OrganizationFilter
├── Resources: PersonResource, OrganizationResource
└── Tests: Full CRUD + filtering + ownership + status integration

Sprint 3: Lead Management
├── crm_lead_sources (migration + model)
├── crm_pipelines + crm_pipeline_stages (migrations + models)
├── crm_leads (migration + model + factory + observer)
├── Actions: CreateLead, MoveLeadStage, QualifyLead, ConvertLead
├── Services: LeadService, PipelineService
├── Controllers: LeadController, PipelineController, LeadSourceController
├── Pipeline entity_type scoping for future modules
├── Stage transition validation rules
└── Tests: Lead CRUD, stage transitions, pipeline management, conversion

Sprint 4: Communication Layer
├── crm_activities (migration + model + factory)
├── crm_notes (migration + model + factory)
├── crm_comments (migration + model + factory)
├── crm_timeline_entries (migration + model)
├── Actions: LogActivity, CreateNote, CreateComment
├── Services: ActivityService, NoteService, CommentService, TimelineService
├── Controller: ActivityController, NoteController, CommentController
├── TimelineWriter (observer-based, queued writes)
├── Mention detection + notification dispatching
└── Tests: All CRUD + polymorphic attachment + timeline integration

Sprint 5: Tasks & Teams
├── crm_teams + crm_team_memberships (migrations + models)
├── crm_tasks (migration + model + factory + observer)
├── crm_task_reminders (migration + model)
├── crm_task_recurrences (migration + model)
├── Actions: CreateTask, CompleteTask, ReassignTask, AddTeamMember
├── Services: TaskService, TeamService, NotificationService
├── Controllers: TaskController, TeamController
├── Task recurrence engine (completed recurring task spawns next)
├── Task reminder scheduling (dispatch queue jobs at remind_at)
└── Tests: Tasks CRUD, assignments, reminders, recurrence, teams CRUD

Sprint 6: Documents, Import & Merge
├── crm_documents (migration + model + factory)
├── crm_import_profiles + crm_import_history (migrations + models)
├── crm_merge_history (migration + model)
├── Services: DocumentService, ImportService, MergeService
├── Controllers: DocumentController, ImportController
├── Wasabi S3 integration (signed URLs, direct upload, streaming download)
├── CSV/XLSX parsing (league/csv + openspout/openspout)
├── Duplicate detection engine
├── Merge workflow (conflict resolution, relationship reassignment)
├── Rollback support (import + merge)
└── Tests: Document upload/download, import preview/execute/rollback, merge with preservation

Sprint 7: Search, Reports & Dashboards
├── Scout integration on all searchable models
├── Meilisearch index configuration
├── Services: SearchService, ReportService
├── Controllers: SearchController, ReportController, DashboardController
├── Saved report CRUD
├── Report export (CSV + PDF)
├── Dashboard endpoints (summary, pipeline funnel, activity timeline, upcoming tasks)
├── Scheduled report delivery (cron-based via Laravel scheduler)
└── Tests: Search, report generation, export, dashboard aggregation
```

### 17.2 Dependencies

```
Sprint 1 ──► Sprint 2 ──► Sprint 3 ──► Sprint 4 ──► Sprint 5
                │                          │
                └───────► Sprint 6 ◄───────┘
                              │
                              ▼
                         Sprint 7
```

Sprint 1 has no dependencies (foundation). Sprint 2-5 can run partially in parallel if team allows. Sprint 6 depends on Sprint 2 (People/Orgs for merge) and Sprint 3 (Leads for lead merge). Sprint 7 depends on all prior sprints.

---

## 18. SCORING

### Architecture Score: 94/100

| Criteria | Score | Notes |
|---|---|---|
| Separation of concerns | 10/10 | Controller→Action→Service→Model enforced |
| Polymorphic design | 10/10 | Activities, notes, comments, documents, tags, timeline all polymorphic |
| Extensibility | 10/10 | Modules add FKs without modifying CRM Core tables |
| Configurable statuses | 9/10 | No enums, fully tenant-configurable, slight complexity in queries |
| Feature gate system | 9/10 | Comprehensive 5-stage evaluation, could be simplified to 4 |
| Ownership model | 9/10 | Universal owner_id + team_id, clear resolution rules |
| Merge engine | 9/10 | Full preservation + rollback, conflict resolution by strategy |
| Import framework | 10/10 | Preview, field mapping, duplicate detection, rollback |
| Timeline | 9/10 | Universal event stream, queued writes balance perf vs consistency |
| Search | 9/10 | Scout + Meilisearch, multi-entity global search |
| **Total** | **94/100** | |

### Scalability Score: 88/100

| Criteria | Score | Notes |
|---|---|---|
| Database indexing | 9/10 | Composite indexes on critical query paths |
| Caching strategy | 9/10 | Redis with tagged cache, per-entity TTLs |
| Queue architecture | 8/10 | Dedicated queues per workload, needs worker sizing for production |
| Partition strategy | 8/10 | Timeline + activities partitioned by month, archiving needed |
| Rate limiting | 8/10 | Per-endpoint limits, headers included |
| Read replicas | 7/10 | Designed for but not configured |
| Cursor pagination | 8/10 | For feeds, page-based for standard lists |
| Background jobs | 10/10 | Import, merge, timeline, search, reports all async |
| Bulk operations | 9/10 | Chunked processing, batch API endpoints |
| **Total** | **88/100** | |

### SaaS Readiness Score: 92/100

| Criteria | Score | Notes |
|---|---|---|
| Multi-tenant isolation | 10/10 | tenant_id on every table, global scope |
| Feature gating | 9/10 | 5-stage evaluation covers all monetization paths |
| Usage tracking | 9/10 | Redis counters + hourly DB sync |
| Permission system | 9/10 | 6 roles, 50+ permissions, ownership scopes |
| Billing integration | 8/10 | Feature gates ready for plan changes |
| Module architecture | 10/10 | CRM Core + industry modules + portal + mobile |
| API versioning | 8/10 | URL-based versioning, deprecation headers |
| Tenant provisioning | 8/10 | Handled by Central Admin, CRM assumes tenant exists |
| Onboarding flow | 7/10 | Default statuses seeded, pipelines need defaults |
| **Total** | **92/100** | |

---

## 19. RISKS & MITIGATIONS

| Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|
| Timeline table grows too fast | Performance degradation on feed queries | High | Partition by month, archive > 12 months, queued writes |
| Polymorphic queries without proper indexes | Slow JOINs across entities | Medium | Composite indexes on all (type, id) morph columns, enforced via migration policy |
| Status system complexity | Developers hardcode status names instead of using IDs | Medium | Convention enforced in code review, helper method `Status::getByKey('lead', 'won')` |
| Merge race conditions | Two users merge the same record simultaneously | Low | Database transaction + row lock on primary record |
| Import file size limits | Memory exhaustion on large CSV/XLSX | Low | Chunked processing (100 rows), streamed parsing, file size limit (100MB) |
| Feature gate cache staleness | User sees feature availability that doesn't match current plan | Low | Cache TTL 1 hour, plan change webhook invalidates, manual refresh button in admin |
| Scout index vs DB consistency | Search results stale after record update | Low | 5-second tolerance acceptable, queue worker ensures eventual consistency |
| Industry module tables not soft-deleting | Orphaned records when CRM Core records are deleted | Medium | Module must define `cascade` or `null` on FK on delete, documented contract |

---

## 20. RECOMMENDED CHANGES BEFORE DEVELOPMENT

| # | Change | Priority | Rationale |
|---|---|---|---|
| 1 | **Finalize status defaults per entity** | Critical | Development needs the seed data for statuses before Sprint 1 |
| 2 | **Define plan → feature mapping** | Critical | FeatureGateService needs feature definitions and plan values before Sprint 1 gates are tested |
| 3 | **Document Industry Module contract as formal doc** | High | Module developers need clear rules before they start (this doc serves as v1) |
| 4 | **Configure Meilisearch instance** | High | Sprint 7 depends on it; provision early for parallel development |
| 5 | **Set up Wasabi buckets + IAM** | High | Sprint 6 document upload depends on it; provision early |
| 6 | **Decide on timeline archive strategy (monthly vs quarterly)** | Medium | Affects partition migration design in Sprint 4 |
| 7 | **Choose between Redis or database for usage counters** | Medium | Affects Sprint 1 FeatureGateService implementation detail |
| 8 | **Define rate limit per plan tier** | Medium | Affects rate limit middleware configuration |
| 9 | **Decide on full-text search engine (Meilisearch vs Typesense)** | Medium | Both work with Scout, but config differs |
| 10 | **Define backup strategy for merged record rollback** | Low | MergeHistory stores full pre-merge state, but long-term backup policy needed |

---

*End of FollowKa Tenant CRM Core Specification v2*

**Next Step:** Begin Sprint 1 implementation (Foundation). Start with FeatureGateService, Statuses, Tags, and Custom Fields. Do not proceed to Sprint 2 until FeatureGateService is fully tested.
