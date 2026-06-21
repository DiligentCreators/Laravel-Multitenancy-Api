# FollowKa Tenant CRM Core Specification v1

**Version:** 1.0  
**Date:** June 19, 2026  
**Status:** Draft for Architecture Review  
**Author:** Chief Product Architect

---

## Table of Contents

1. [System Context & Boundaries](#1-system-context--boundaries)
2. [Folder & Domain Structure](#2-folder--domain-structure)
3. [CRM Core Data Model (ERD)](#3-crm-core-data-model-erd)
4. [Database Blueprint](#4-database-blueprint)
5. [Lead Management System](#5-lead-management-system)
6. [Task Management](#6-task-management)
7. [Team Collaboration](#7-team-collaboration)
8. [Permissions Matrix](#8-permissions-matrix)
9. [Feature Gate System](#9-feature-gate-system)
10. [Service Layer Design](#10-service-layer-design)
11. [API Design](#11-api-design)
12. [Reporting Layer](#12-reporting-layer)
13. [Module Boundaries & Extensibility](#13-module-boundaries--extensibility)
14. [Scalability Considerations](#14-scalability-considerations)
15. [Future Compatibility](#15-future-compatibility)

---

## 1. System Context & Boundaries

### 1.1 Architecture Rule

```
Controller → Action → Service → Model
```

Business logic must never exist inside controllers.

### 1.2 Product Structure

```
FollowKa
├── Central Admin            ← PRODUCTION-READY (bug fixes only)
├── Tenant CRM Core          ← THIS DESIGN (industry agnostic)
├── Industry Modules
│   ├── Solar Module         ← Extends CRM Core
│   ├── Agency Module        ← Extends CRM Core
│   └── Real Estate Module   ← Extends CRM Core
├── Client Portal            ← Consumes CRM Core APIs
└── Mobile App (React Native) ← Consumes CRM Core APIs
```

### 1.3 Design Principle

CRM Core must never contain industry-specific tables, fields, or logic. Industry modules add their own migrations, models, actions, and services that relate back to CRM Core entities via foreign keys or morph relationships. Industry modules are Composer packages or `app/Modules/{Industry}` directories with their own service providers.

### 1.4 Key Constraints

| Constraint | Decision |
|---|---|
| Database | Shared (Stancl tenancy with tenant_id scoping) |
| API | Sanctum token auth, JSON envelope, versioned (`/api/tenant/v1`) |
| File Storage | Wasabi S3 for documents |
| Polymorphic Relations | Use `morphs()` for Notes, Activities, Documents, Comments, Tags |
| Custom Fields | EAV pattern via JSON column per entity |
| Tenancy | All CRM Core tables include `tenant_id` with global scope |

---

## 2. Folder & Domain Structure

### 2.1 Laravel Domain Structure

```
app/
├── Actions/
│   └── Crm/
│       ├── People/
│       │   ├── CreatePerson.php
│       │   ├── UpdatePerson.php
│       │   ├── MergePeople.php
│       │   ├── SearchPeople.php
│       │   └── AttachOrganization.php
│       ├── Organizations/
│       │   ├── CreateOrganization.php
│       │   ├── UpdateOrganization.php
│       │   └── MergeOrganizations.php
│       ├── Contacts/
│       │   ├── AddContactToOrganization.php
│       │   ├── RemoveContactFromOrganization.php
│       │   └── SetPrimaryContact.php
│       ├── Addresses/
│       │   ├── CreateAddress.php
│       │   ├── UpdateAddress.php
│       │   └── SetDefaultAddress.php
│       ├── Tags/
│       │   ├── AttachTag.php
│       │   ├── DetachTag.php
│       │   └── CreateTag.php
│       ├── Notes/
│       │   ├── CreateNote.php
│       │   ├── UpdateNote.php
│       │   └── DeleteNote.php
│       ├── Activities/
│       │   ├── LogActivity.php
│       │   ├── CompleteActivity.php
│       │   └── RescheduleActivity.php
│       ├── Documents/
│       │   ├── UploadDocument.php
│       │   ├── DeleteDocument.php
│       │   └── ShareDocument.php
│       ├── Comments/
│       │   ├── CreateComment.php
│       │   ├── UpdateComment.php
│       │   └── DeleteComment.php
│       ├── CustomFields/
│       │   ├── DefineCustomField.php
│       │   ├── UpdateCustomField.php
│       │   └── SetCustomFieldValue.php
│       ├── Leads/
│       │   ├── CreateLead.php
│       │   ├── UpdateLead.php
│       │   ├── MoveLeadStage.php
│       │   ├── ConvertLead.php
│       │   └── QualifyLead.php
│       ├── Pipelines/
│       │   ├── CreatePipeline.php
│       │   ├── UpdatePipeline.php
│       │   ├── ReorderStages.php
│       │   └── ArchivePipeline.php
│       ├── Tasks/
│       │   ├── CreateTask.php
│       │   ├── UpdateTask.php
│       │   ├── CompleteTask.php
│       │   ├── ReassignTask.php
│       │   └── SetTaskReminder.php
│       ├── Teams/
│       │   ├── CreateTeam.php
│       │   ├── AddTeamMember.php
│       │   ├── RemoveTeamMember.php
│       │   └── UpdateTeamRole.php
│       └── Reports/
│           ├── GenerateCrmReport.php
│           ├── GenerateSalesReport.php
│           ├── GenerateActivityReport.php
│           └── GenerateTeamReport.php
│
├── Models/
│   └── Crm/
│       ├── Person.php
│       ├── Organization.php
│       ├── Contact.php              # Pivot model
│       ├── Address.php
│       ├── Tag.php
│       ├── Taggable.php             # Pivot model
│       ├── Note.php
│       ├── Activity.php
│       ├── Document.php
│       ├── Comment.php
│       ├── CustomField.php
│       ├── CustomFieldValue.php
│       ├── Lead.php
│       ├── LeadSource.php
│       ├── Pipeline.php
│       ├── PipelineStage.php
│       ├── Task.php
│       ├── TaskType.php
│       ├── TaskReminder.php
│       ├── TaskRecurrence.php
│       ├── TaskAssignment.php
│       ├── Team.php
│       ├── TeamMembership.php
│       └── CrmDashboard.php         # Read-only view model / presenter
│
├── Services/
│   └── Crm/
│       ├── PersonService.php
│       ├── OrganizationService.php
│       ├── AddressService.php
│       ├── TagService.php
│       ├── NoteService.php
│       ├── ActivityService.php
│       ├── DocumentService.php
│       ├── CommentService.php
│       ├── CustomFieldService.php
│       ├── LeadService.php
│       ├── PipelineService.php
│       ├── TaskService.php
│       ├── TeamService.php
│       ├── ReportService.php
│       ├── SearchService.php        # Unified search across CRM entities
│       ├── MergeService.php         # Deduplication logic
│       └── ImportService.php        # CSV/XLSX bulk import
│
├── Http/
│   ├── Controllers/
│   │   └── Tenant/
│   │       └── Api/
│   │           └── V1/
│   │               ├── Crm/
│   │               │   ├── PersonController.php
│   │               │   ├── OrganizationController.php
│   │               │   ├── ContactController.php
│   │               │   ├── AddressController.php
│   │               │   ├── TagController.php
│   │               │   ├── NoteController.php
│   │               │   ├── ActivityController.php
│   │               │   ├── DocumentController.php
│   │               │   ├── CommentController.php
│   │               │   ├── CustomFieldController.php
│   │               │   ├── LeadController.php
│   │               │   ├── PipelineController.php
│   │               │   ├── TaskController.php
│   │               │   ├── TeamController.php
│   │               │   ├── DashboardController.php
│   │               │   └── SearchController.php
│   │               └── ReportController.php
│   ├── Requests/
│   │   └── Crm/
│   │       ├── StorePersonRequest.php
│   │       ├── UpdatePersonRequest.php
│   │       ├── StoreOrganizationRequest.php
│   │       ├── ... (one per action per resource)
│   └── Resources/
│       └── Crm/
│           ├── PersonResource.php
│           ├── PersonCollection.php
│           ├── OrganizationResource.php
│           ├── ... (Resource + Collection per entity)
│
├── Filters/                          # Spatie Query Builder filters
│   └── Crm/
│       ├── PersonFilter.php
│       ├── OrganizationFilter.php
│       ├── LeadFilter.php
│       ├── TaskFilter.php
│       └── ActivityFilter.php
│
├── Exports/
│   └── Crm/
│       ├── PeopleExport.php
│       ├── LeadsExport.php
│       ├── TasksExport.php
│       └── ActivitiesExport.php
│
├── Imports/
│   └── Crm/
│       ├── PeopleImport.php
│       ├── OrganizationsImport.php
│       ├── LeadsImport.php
│       └── ContactsImport.php
│
├── Events/
│   └── Crm/
│       ├── PersonCreated.php
│       ├── PersonUpdated.php
│       ├── PersonMerged.php
│       ├── OrganizationCreated.php
│       ├── LeadStageChanged.php
│       ├── LeadWon.php
│       ├── LeadLost.php
│       ├── TaskCompleted.php
│       ├── ActivityLogged.php
│       ├── CommentMentioned.php
│       ├── DocumentUploaded.php
│       └── CustomFieldUpdated.php
│
├── Listeners/
│   └── Crm/
│       ├── NotifyMentionedUsers.php
│       ├── UpdateLeadScore.php
│       ├── SyncSearchIndex.php
│       ├── LogCrmActivity.php
│       └── InvalidateCrmCache.php
│
├── Observers/
│   └── Crm/
│       ├── PersonObserver.php
│       ├── OrganizationObserver.php
│       ├── LeadObserver.php
│       └── TaskObserver.php
│
├── Rules/
│   └── Crm/
│       ├── ValidPipelineStageTransition.php
│       ├── UniquePersonPerTenant.php
│       ├── ValidCustomFieldType.php
│       └── ValidTeamMemberRole.php
│
├── Enums/
│   └── Crm/
│       ├── PersonStatus.php
│       ├── ActivityType.php
│       ├── ActivityStatus.php
│       ├── DocumentType.php
│       ├── LeadStatus.php
│       ├── LeadSourceType.php
│       ├── TaskPriority.php
│       ├── TaskStatus.php
│       ├── TaskRecurrenceType.php
│       ├── TeamMemberRole.php
│       ├── CustomFieldType.php
│       └── AddressType.php
│
└── FeatureGate/
    ├── FeatureGateService.php
    ├── Feature.php                  # Value object / enum
    ├── PlanFeatureProvider.php
    ├── ModuleFeatureProvider.php
    └── UsageLimitTracker.php
```

### 2.2 Module Directory Pattern (for future industry modules)

```
app/Modules/Solar/
├── Providers/SolarServiceProvider.php
├── Models/
│   ├── SolarInstallation.php       # FK: person_id, organization_id, address_id
│   ├── SolarPanel.php
│   ├── SolarProposal.php           # FK: lead_id
│   └── SolarInspection.php
├── Actions/
│   ├── CreateSolarProposal.php
│   └── ScheduleInspection.php
├── Services/SolarLeadService.php   # Extends CRM Lead logic
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Migrations/
├── Config/
└── Database/
    └── Factories/
```

Industry modules never duplicate CRM Core tables. They add foreign keys to CRM Core entities (`person_id`, `organization_id`, `lead_id`, `address_id`, etc.) and create their own domain-specific tables.

---

## 3. CRM Core Data Model (ERD)

### 3.1 Entity Relationship Overview

```
┌───────────────┐       ┌──────────────────┐
│    Person     │       │  Organization    │
├───────────────┤       ├──────────────────┤
│ id            │       │ id               │
│ tenant_id     │       │ tenant_id        │
│ first_name    │       │ name             │
│ last_name     │       │ industry         │
│ email         │──┐    │ website          │
│ phone         │  │    │ email            │
│ mobile        │  │    │ phone            │
│ job_title     │  │    │ owner_id (User)  │
│ date_of_birth │  │    │ created_by       │
│ status        │  │    │ updated_by       │
│ owner_id(User)│  │    │ timestamps       │
│ created_by    │  │    │ soft_deletes     │
│ updated_by    │  │    └──────────────────┘
│ timestamps    │  │             │
│ soft_deletes  │  │             │
└───────┬───────┘  │             │
        │          │    ┌────────┴──────────┐
        │          │    │  Contact (pivot)   │
        │          │    ├───────────────────┤
        │          │    │ person_id         │
        │          │    │ organization_id   │
        │          │    │ job_title         │
        │          │    │ department        │
        │          │    │ is_primary        │
        │          │    │ role              │
        │          │    │ timestamps        │
        │          │    └───────────────────┘
        │          │
        │          │         ┌──────────────┐
        │          │         │  Address     │
        │          │         ├──────────────┤
        │          └─────────┤ addressable  │ (morphs)
        │                    │ type         │ (billing, shipping, etc.)
        │                    │ country      │
        │                    │ state        │
        │                    │ city         │
        │                    │ postal_code  │
        │                    │ line1        │
        │                    │ line2        │
        │                    │ lat          │
        │                    │ lng          │
        │                    │ is_default   │
        │                    │ timestamps   │
        │                    └──────┬───────┘
        │                           │
        │          ┌────────────────┴────────────────┐
        │          │                                 │
        │    ┌─────┴──────┐                 ┌────────┴────────┐
        │    │   Note     │                 │  Tag            │
        │    ├────────────┤                 ├─────────────────┤
        │    │ notable    │ (morphs)        │ taggable        │ (morphs)
        │    │ body       │                 │ tag_id          │
        │    │ created_by │                 └─────────────────┘
        │    │ timestamps │
        │    └────────────┘        ┌──────────────────┐
        │                          │  Activity         │
        │    ┌────────────┐        ├──────────────────┤
        │    │  Document  │        │ activitable      │ (morphs)
        │    ├────────────┤        │ type (call,meet) │
        │    │ documentable│       │ subject          │
        │    │ (morphs)   │        │ description      │
        │    │ name       │        │ scheduled_at     │
        │    │ path (S3)  │        │ completed_at     │
        │    │ mime_type  │        │ duration_minutes │
        │    │ size       │        │ assigned_to      │
        │    │ created_by │        │ created_by       │
        │    │ timestamps │        │ timestamps       │
        │    └────────────┘        └──────────────────┘
        │
        │    ┌────────────┐
        │    │  Comment   │
        │    ├────────────┤
        │    │ commentable│ (morphs)
        │    │ body       │
        │    │ mentions   │ (JSON array of user IDs)
        │    │ created_by │
        │    │ timestamps │
        │    └────────────┘
        │
        │    ┌──────────────────┐
        │    │  CustomFieldDef  │
        │    ├──────────────────┤
        │    │ tenant_id        │
        │    │ entity_type      │ (Person, Organization, Lead, etc.)
        │    │ name             │
        │    │ key              │ (slug)
        │    │ type             │ (text, number, date, select, etc.)
        │    │ options          │ (JSON for select/multi)
        │    │ is_required      │
        │    │ is_unique        │
        │    │ validation_rules │ (JSON)
        │    │ timestamps       │
        │    └──────┬───────────┘
        │           │
        │    ┌──────┴───────────┐
        │    │ CustomFieldValue │
        │    ├──────────────────┤
        │    │ custom_field_id  │
        │    │ entity_type      │
        │    │ entity_id        │
        │    │ value            │ (JSON — cast to native type)
        │    │ timestamps       │
        │    └──────────────────┘
        │
        │    ┌──────────────────┐
        │    │      Lead        │
        │    ├──────────────────┤
        │    │ tenant_id        │
        │    │ person_id        │ (nullable)
        │    │ organization_id  │ (nullable)
        │    │ source_id        │
        │    │ status           │
        │    │ pipeline_id      │
        │    │ stage_id         │
        │    │ owner_id (User)  │
        │    │ value            │ (decimal)
        │    │ probability      │ (percentage int)
        │    │ qualified_at     │
        │    │ converted_at     │
        │    │ lost_reason      │
        │    │ lost_at          │
        │    │ created_by       │
        │    │ updated_by       │
        │    │ timestamps       │
        │    │ soft_deletes     │
        │    └──────────────────┘
        │           │
        │    ┌──────┴──────┐
        │    │ LeadSource  │
        │    ├─────────────┤
        │    │ tenant_id   │
        │    │ name        │
        │    │ type        │ (enum: website, referral, api, etc.)
        │    │ is_active   │
        │    │ timestamps  │
        │    └─────────────┘
        │
        │    ┌──────────────────┐
        │    │    Pipeline      │
        │    ├──────────────────┤
        │    │ tenant_id        │
        │    │ name             │
        │    │ entity_type      │ (nullable — scopes to module)
        │    │ is_default       │
        │    │ is_active        │
        │    │ created_by       │
        │    │ timestamps       │
        │    │ soft_deletes     │
        │    └──────┬───────────┘
        │           │
        │    ┌──────┴───────────┐
        │    │ PipelineStage    │
        │    ├──────────────────┤
        │    │ pipeline_id      │
        │    │ name             │
        │    │ order            │
        │    │ color            │
        │    │ probability      │ (default probability %)
        │    │ is_won_stage     │
        │    │ is_lost_stage    │
        │    │ timestamps       │
        │    └──────────────────┘
        │
        │    ┌──────────────────┐
        │    │      Task        │
        │    ├──────────────────┤
        │    │ tenant_id        │
        │    │ taskable_type    │ (morphs to any entity)
        │    │ taskable_id      │
        │    │ type_id          │
        │    │ title            │
        │    │ description      │
        │    │ priority         │
        │    │ status           │
        │    │ due_at           │
        │    │ completed_at     │
        │    │ estimated_min    │
        │    │ created_by       │
        │    │ timestamps       │
        │    │ soft_deletes     │
        │    └──────┬───────────┘
        │           │
        │    ┌──────┴──────────────────┐
        │    │                         │
        │    │    ┌───────────────┐    │    ┌───────────────┐
        │    │    │ TaskReminder  │    │    │TaskRecurrence │
        │    │    ├───────────────┤    │    ├───────────────┤
        │    │    │ task_id       │    │    │ task_id       │
        │    │    │ remind_at     │    │    │ type          │
        │    │    │ notified_at   │    │    │ interval      │
        │    │    │ timestamps    │    │    │ end_at        │
        │    │    └───────────────┘    │    │ timestamps    │
        │    │                         │    └───────────────┘
        │    │    ┌───────────────┐
        │    │    │TaskAssignment │
        │    │    ├───────────────┤
        │    │    │ task_id       │
        │    │    │ user_id       │
        │    │    │ assigned_by   │
        │    │    │ assigned_at   │
        │    │    └───────────────┘
        │
        │    ┌──────────────────┐
        │    │      Team        │
        │    ├──────────────────┤
        │    │ tenant_id        │
        │    │ name             │
        │    │ description      │
        │    │ owner_id (User)  │
        │    │ timestamps       │
        │    │ soft_deletes     │
        │    └──────┬───────────┘
        │           │
        │    ┌──────┴───────────┐
        │    │ TeamMembership   │
        │    ├──────────────────┤
        │    │ team_id          │
        │    │ user_id          │
        │    │ role             │
        │    │ joined_at        │
        │    │ timestamps       │
        │    └──────────────────┘
```

---

## 4. Database Blueprint

### 4.1 Core Entity Tables

#### `crm_people`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| first_name | string(100) | required |
| last_name | string(100) | required |
| email | string(255) | nullable, unique per tenant |
| phone | string(50) | nullable |
| mobile | string(50) | nullable |
| job_title | string(200) | nullable |
| date_of_birth | date | nullable |
| status | string(50) | default 'active', enum via PersonStatus |
| owner_id | foreignId | nullable, FK → users.id |
| created_by | foreignId | nullable, FK → users.id |
| updated_by | foreignId | nullable, FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** `tenant_id`, `email`, `owner_id`, composite `(tenant_id, last_name)`, composite `(tenant_id, email)` unique where not null.

#### `crm_organizations`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| name | string(255) | required |
| industry | string(100) | nullable |
| website | string(500) | nullable |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| owner_id | foreignId | nullable, FK → users.id |
| created_by | foreignId | nullable, FK → users.id |
| updated_by | foreignId | nullable, FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** `tenant_id`, `name`, `owner_id`.

#### `crm_contacts` (pivot)

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| person_id | foreignId | FK → crm_people.id, cascade |
| organization_id | foreignId | FK → crm_organizations.id, cascade |
| job_title | string(200) | nullable |
| department | string(200) | nullable |
| is_primary | boolean | default false |
| role | string(100) | nullable (e.g., "decision maker", "gatekeeper") |
| timestamps | — | — |

**Indexes:** Unique composite `(person_id, organization_id)`, `organization_id`, `is_primary`.

#### `crm_addresses`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| addressable_type | string(100) | morph |
| addressable_id | bigInteger | morph |
| type | string(50) | default 'other' (AddressType enum) |
| country | string(100) | nullable |
| state | string(100) | nullable |
| city | string(100) | nullable |
| postal_code | string(20) | nullable |
| address_line_1 | string(500) | required |
| address_line_2 | string(500) | nullable |
| latitude | decimal(10,7) | nullable |
| longitude | decimal(10,7) | nullable |
| is_default | boolean | default false |
| timestamps | — | — |

**Indexes:** `tenant_id`, morphs `(addressable_type, addressable_id)`, composite `(tenant_id, type)`, `is_default`.

#### `crm_tags`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| name | string(100) | required |
| color | string(7) | nullable, hex code |
| is_active | boolean | default true |
| timestamps | — | — |

**Unique:** Composite `(tenant_id, name)`.

#### `crm_taggables` (pivot)

| Column | Type | Constraints |
|---|---|---|
| tag_id | foreignId | FK → crm_tags.id, cascade |
| taggable_type | string(100) | — |
| taggable_id | bigInteger | — |
| timestamps | — | — |

**Indexes:** Unique composite `(tag_id, taggable_type, taggable_id)`, morphs `(taggable_type, taggable_id)`.

#### `crm_notes`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| notable_type | string(100) | morph |
| notable_id | bigInteger | morph |
| body | text | required |
| created_by | foreignId | FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** Morphs `(notable_type, notable_id)`.

#### `crm_activities`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| activitable_type | string(100) | morph |
| activitable_id | bigInteger | morph |
| type | string(50) | ActivityType enum (call, meeting, email, visit, task, sms, whatsapp) |
| subject | string(500) | nullable |
| description | text | nullable |
| scheduled_at | datetime | nullable |
| completed_at | datetime | nullable |
| duration_minutes | integer | nullable |
| assigned_to | foreignId | nullable, FK → users.id |
| created_by | foreignId | FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** Morphs, `scheduled_at`, `assigned_to`, `type`, composite `(tenant_id, scheduled_at)`.

#### `crm_documents`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| documentable_type | string(100) | morph |
| documentable_id | bigInteger | morph |
| name | string(255) | required |
| path | string(1000) | required (Wasabi S3 path) |
| mime_type | string(100) | nullable |
| size | bigInteger | nullable (bytes) |
| type | string(50) | nullable (DocumentType enum) |
| created_by | foreignId | FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** Morphs, `type`.

#### `crm_comments`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| commentable_type | string(100) | morph |
| commentable_id | bigInteger | morph |
| body | text | required |
| mentions | json | nullable, array of user IDs |
| created_by | foreignId | FK → users.id |
| timestamps | — | — |
| soft_deletes | — | — |

**Indexes:** Morphs.

#### `crm_custom_field_definitions`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| tenant_id | foreignId | FK → tenants.id, indexed |
| entity_type | string(100) | required (Person, Organization, Lead, etc.) |
| name | string(200) | required |
| key | string(200) | auto-slug from name |
| type | string(50) | CustomFieldType enum |
| options | json | nullable (for select/multi/radio) |
| is_required | boolean | default false |
| is_unique | boolean | default false |
| validation_rules | json | nullable |
| order | integer | default 0 (display ordering) |
| is_active | boolean | default true |
| timestamps | — | — |

**Unique:** Composite `(tenant_id, entity_type, key)`.

#### `crm_custom_field_values`

| Column | Type | Constraints |
|---|---|---|
| id | bigIncrements | PK |
| custom_field_definition_id | foreignId | FK → crm_custom_field_definitions.id, cascade |
| entity_type | string(100) | morph |
| entity_id | bigInteger | morph (no FK — entity can be in any table) |
| value | json | required |
| timestamps | — | — |

**Unique:** Composite `(custom_field_definition_id, entity_type, entity_id)`.
**Indexes:** Morphs `(entity_type, entity_id)`.

---

## 5. Lead Management System

### 5.1 Architecture

Leads are the universal entry point for all sales workflows. A Lead may or may not have a Person or Organization attached. Leads progress through Pipelines via Pipeline Stages.

### 5.2 Lead to Person/Organization Relationship

```
Lead
├── person_id (nullable)        ← Becomes a Person when qualified
├── organization_id (nullable)  ← Becomes an Organization when qualified
├── email                       ← Inline contact info (when no Person yet)
├── phone                       ← Inline contact info (when no Person yet)
└── company_name                ← Inline company (when no Organization yet)
```

**Conversion Flow:**

1. **Raw Lead** — email, phone, company_name only (no Person/Organization)
2. **Qualified Lead** — person_id and/or organization_id set
3. **Converted Lead** — Lead becomes a Deal (future: Sales module), Lead is archived

### 5.3 Pipeline Architecture

Each tenant can define multiple Pipelines. Each Pipeline has ordered Stages. A Lead belongs to one Pipeline and one Stage at a time.

**Example Pipelines:**

| Pipeline | Stages |
|---|---|
| Sales Pipeline | New → Qualified → Proposal → Negotiation → Won / Lost |
| Property Pipeline | Inquiry → Viewing → Offer → Negotiation → Sold / Withdrawn |
| Solar Pipeline | Lead → Site Survey → Proposal → Installation → Active / Cancelled |
| Hiring Pipeline | Applied → Screening → Interview → Offer → Hired / Rejected |

### 5.4 Stage Transitions

Pipeline stages transition rules:

- Stages can only move forward in `order`
- Won and Lost stages are terminal (no forward movement allowed)
- A Lead can be moved back to any earlier stage (re-open) within the same pipeline
- Pipeline can be changed (move Lead to another pipeline's first stage)

### 5.5 Lead Sources

Sources are tenant-defined and categorized by type:

| Source Type | Examples |
|---|---|
| website | Contact form, Chat widget |
| social | Facebook, LinkedIn, Instagram |
| ads | Google Ads, Facebook Ads |
| referral | Existing customer, Partner |
| manual | Direct creation by user |
| import | CSV, API, Integration |
| api | Third-party API import |

---

## 6. Task Management

### 6.1 Task Entity

Tasks are morphable to any entity (Person, Organization, Lead, Deal, etc.). This allows each domain to attach tasks without modifying CRM Core.

### 6.2 Task Types

Tenant-defined categories:

- Call
- Meeting
- Follow-up
- Email
- To-do
- Custom (tenant-defined via `crm_task_types`)

### 6.3 Task Priorities

| Priority | Value |
|---|---|
| Low | 0 |
| Medium | 1 |
| High | 2 |
| Urgent | 3 |

### 6.4 Task Statuses

| Status | Meaning |
|---|---|
| pending | Not started |
| in_progress | Actively being worked on |
| completed | Done |
| cancelled | Abandoned |
| deferred | Postponed |

### 6.5 Task Reminders

- Each Task can have multiple Reminders
- Reminders fire at `remind_at` (sent via in-app notification, optionally email/SMS)
- Once notified, `notified_at` is timestamped

### 6.6 Task Recurrence

| Recurrence Type | Interval |
|---|---|
| daily | Every N days |
| weekly | Every N weeks on specific days |
| monthly | Every N months on specific day |
| yearly | Every N years |
| custom | Cron expression |

When a recurring task is completed, a new Task is created based on the recurrence definition.

### 6.7 Task Assignments

- A Task can be assigned to multiple Users
- Each assignment tracks `assigned_by` and `assigned_at`
- Assignment history is preserved (the `task_assignments` table is append-only for history; the "current" assignment set is determined by the latest non-deleted record per user)

---

## 7. Team Collaboration

### 7.1 Teams

Teams are tenant-level groups of users. A User can belong to multiple Teams with different roles in each.

### 7.2 Team Membership Roles

| Role | Scoped Permissions |
|---|---|
| Owner | Full team management, can delete team |
| Admin | Manage members, edit team settings |
| Member | View team, participate in tasks |

### 7.3 Mentions

- Stored as JSON array of user IDs in the `mentions` column on `crm_comments` (and optionally on `crm_notes`)
- Mention detection: parse `@username` patterns in comment/note body, resolve to user ID, store in `mentions` column
- On save, `CommentMentioned` event dispatches notifications to mentioned users

### 7.4 Notifications

- In-app notifications table (Laravel's `notifications` table)
- Notification types: mention, assignment, reminder, stage change, comment, document shared
- Each notification links back to the source entity via `type` and `data` JSON

### 7.5 Activity Feed

- Unified feed across all CRM entities
- Powered by a materialized view or ElasticSearch/Scout index that combines:
  - Activities (logged)
  - Comments
  - Lead stage changes
  - Task completions
  - Document uploads
- Queryable by entity, user, date range, type
- Paginated, filterable

---

## 8. Permissions Matrix

### 8.1 Tenant-Level Roles

| Permission \ Role | Owner | Admin | Manager | Sales | Support | Viewer |
|---|---|---|---|---|---|---|
| **People** | | | | | | |
| people.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| people.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| people.update | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| people.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| people.merge | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| people.export | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| people.import | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Organizations** | | | | | | |
| organizations.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| organizations.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| organizations.update | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| organizations.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| organizations.merge | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Leads** | | | | | | |
| leads.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| leads.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| leads.update | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| leads.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| leads.move_stage | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| leads.convert | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| leads.reassign | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Pipelines** | | | | | | |
| pipelines.manage | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| pipelines.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Tasks** | | | | | | |
| tasks.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| tasks.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| tasks.update | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| tasks.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| tasks.assign | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| tasks.complete | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Teams** | | | | | | |
| teams.manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| teams.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Documents** | | | | | | |
| documents.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| documents.upload | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| documents.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Activities** | | | | | | |
| activities.log | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| activities.view_all | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| activities.view_own | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notes & Comments** | | | | | | |
| notes.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| notes.view_all | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| notes.delete_all | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Custom Fields** | | | | | | |
| custom_fields.manage | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Reports** | | | | | | |
| reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| reports.export | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Settings** | | | | | | |
| settings.crm.manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### 8.2 Permission Implementation

- Use Spatie Laravel Permission with `guard_name = 'sanctum'`
- Roles are tenant-level (scoped by `tenant_id`)
- A custom `TenantTeamRole` middleware checks both the role and the team membership for team-scoped operations
- Owner role is assigned automatically when a tenant is provisioned
- Permissions are checked via Gates and Policies, never in controllers

### 8.3 Ownership-Based Visibility (Data Isolation)

| Scope | Rules |
|---|---|
| Own records | User can view/edit records they own |
| Team records | User can view records owned by their team members |
| All records | Admin, Manager, and Owner can view all records |
| Support role | View only, own-scoped |

Implemented via a `CrmScope` global scope that applies the correct visibility filter based on the authenticated user's role and team memberships.

---

## 9. Feature Gate System

### 9.1 Architecture

```
FeatureGateService
├── PlanFeatureProvider      ← Reads features from tenant's plan
├── ModuleFeatureProvider    ← Checks if industry module is enabled
├── UsageLimitTracker        ← Checks current usage vs limits
└── OverageTracker           ← Checks if overage is allowed
```

### 9.2 Feature Categories

| Category | Examples | Check |
|---|---|---|
| Plan Features | max_contacts, max_leads, documents | PlanFeatureProvider |
| Module Features | solar_enabled, real_estate_enabled | ModuleFeatureProvider |
| Usage Limits | contacts_used, storage_used | UsageLimitTracker |
| Add-ons | extra_seats, extra_storage | PlanFeatureProvider |
| Overages | overage_allowed, overage_rate | OverageTracker |

### 9.3 API Design

```php
// Simple boolean check
FeatureGate::allows('contacts.create');     // true/false
FeatureGate::allows('documents.upload');    // true/false
FeatureGate::allows('pipelines.manage');    // true/false

// With context
FeatureGate::for($tenant)->allows('contacts.create');
FeatureGate::for($tenant)->limit('contacts.max');    // returns int
FeatureGate::for($tenant)->usage('contacts.used');   // returns int

// Returns remaining allowance
FeatureGate::for($tenant)->remaining('contacts');

// Throws on violation (for middleware)
FeatureGate::for($tenant)->assert('contacts.create');

// Module check
FeatureGate::module('solar')->enabled();
FeatureGate::module('real_estate')->enabled();
```

### 9.4 Feature Definitions (stored per plan)

```php
[
    'contacts.max'        => 5000,
    'contacts.create'     => true,
    'leads.max'           => 1000,
    'pipelines.manage'    => true,
    'documents.storage_mb' => 100,
    'documents.upload'    => true,
    'tasks.enabled'       => true,
    'reports.enabled'     => true,
    'import.csv'          => true,
    'export.csv'          => true,
    'api.access'          => true,
    'teams.max'           => 5,
    'custom_fields.max'   => 20,
]
```

### 9.5 Usage Limit Tracking

Usage is tracked via:
- **Database queries** — `SELECT COUNT(*) FROM crm_people WHERE tenant_id = ?`
- **Cached counters** — Redis increment/decrement on CRUD events
- **Scheduled sync** — Hourly `php artisan crm:sync-usage-limits` recalculates from database

### 9.6 Overages

When a usage limit is exceeded:
- `FeatureGate::allows('contacts.create')` returns `false` if overage is not allowed
- If overage is allowed, `FeatureGate::allows('contacts.create')` returns `true` but flags are set for billing
- The tenant's dashboard shows "X over your plan limit" banner

### 9.7 Middleware Usage

```php
Route::middleware(['feature:contacts.create'])->group(function () {
    // ...
});

// In controller
public function __invoke(CreatePersonRequest $request)
{
    FeatureGate::assert('contacts.create');

    $person = $this->action->handle($request->validated());

    return new PersonResource($person);
}
```

---

## 10. Service Layer Design

### 10.1 Service Responsibilities

Each service encapsulates all business logic for its domain entity. Services call Actions for specific operations and coordinate cross-entity workflows.

```
Service
├── Delegates to Actions for atomic operations
├── Orchestrates cross-entity workflows
├── Handles authorization (calls FeatureGate, checks permissions)
├── Dispatches events
└── Invalidates cache
```

### 10.2 Service Examples

```php
class LeadService
{
    public function __construct(
        private CreateLead $createLead,
        private UpdateLead $updateLead,
        private MoveLeadStage $moveLeadStage,
        private QualifyLead $qualifyLead,
        private ConvertLead $convertLead,
        private PipelineService $pipelineService,
        private ActivityService $activityService,
        private FeatureGateService $featureGate,
    ) {}

    public function create(array $data): Lead
    {
        $this->featureGate->assert('leads.create');

        $lead = $this->createLead->handle($data);

        $this->activityService->log(
            $lead,
            ActivityType::Note,
            "Lead created from {$lead->source?->name}"
        );

        CrmEventDispatcher::dispatch(new LeadCreated($lead));

        return $lead;
    }

    public function moveStage(Lead $lead, PipelineStage $stage): Lead
    {
        $this->featureGate->assert('leads.move_stage');

        $oldStage = $lead->stage;

        $lead = $this->moveLeadStage->handle($lead, $stage);

        if ($stage->is_won_stage) {
            event(new LeadWon($lead));
        }

        if ($stage->is_lost_stage) {
            event(new LeadLost($lead));
        }

        event(new LeadStageChanged($lead, $oldStage, $stage));

        return $lead;
    }

    public function qualify(int $leadId, int $personId): Lead
    {
        $lead = $this->qualifyLead->handle($leadId, $personId);

        event(new LeadQualified($lead));

        return $lead;
    }
}
```

### 10.3 Action Pattern

Actions are single-purpose classes that perform exactly one operation. They are stateless and receive all dependencies via constructor injection.

```php
class MoveLeadStage
{
    public function __construct(
        private PipelineService $pipelineService,
        private ValidPipelineStageTransition $transitionRule,
    ) {}

    public function handle(Lead $lead, PipelineStage $stage): Lead
    {
        if (! $this->transitionRule->passes($lead, $stage)) {
            throw new InvalidStageTransitionException($lead, $stage);
        }

        $lead->update([
            'stage_id' => $stage->id,
            'pipeline_id' => $stage->pipeline_id,
            'probability' => $stage->probability,
        ]);

        return $lead->fresh();
    }
}
```

### 10.4 Cross-Entity Orchestration (Service Level)

These workflows span multiple entities and are orchestrated at the Service level:

| Workflow | Services Involved |
|---|---|
| Lead Qualification | LeadService + PersonService + OrganizationService |
| Lead Conversion | LeadService + DealService (future module) |
| Person Merge | MergeService + PersonService + AddressService + ActivityService |
| Activity Logging | ActivityService (polymorphic, accepts any model) |
| Document Upload | DocumentService + WasabiStorageService |
| Task Completion | TaskService + TaskRecurrenceService + ActivityService |
| Team Member Add | TeamService + PermissionSyncService |

---

## 11. API Design

### 11.1 Base URL

```
https://{tenant}.followka.com/api/tenant/v1/crm
```

### 11.2 Authentication

Sanctum token (Central Admin creates token, CRM uses it). Middleware resolves tenant from subdomain.

### 11.3 Response Envelope (same as Central Admin)

```json
{
    "status": "success",
    "message": "Person created successfully.",
    "data": { ... },
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 42,
        "last_page": 3
    }
}
```

### 11.4 API Endpoints

#### People

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/people` | List (filtered, paginated, sorted) |
| POST | `/crm/people` | Create |
| GET | `/crm/people/{id}` | Show |
| PUT | `/crm/people/{id}` | Update |
| DELETE | `/crm/people/{id}` | Soft delete |
| POST | `/crm/people/{id}/restore` | Restore |
| POST | `/crm/people/{id}/merge` | Merge with another person |
| GET | `/crm/people/{id}/organizations` | List person's organizations |
| GET | `/crm/people/{id}/activities` | List person's activities |
| GET | `/crm/people/{id}/tasks` | List person's tasks |
| GET | `/crm/people/{id}/documents` | List person's documents |
| GET | `/crm/people/{id}/notes` | List person's notes |

#### Organizations

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/organizations` | List |
| POST | `/crm/organizations` | Create |
| GET | `/crm/organizations/{id}` | Show |
| PUT | `/crm/organizations/{id}` | Update |
| DELETE | `/crm/organizations/{id}` | Soft delete |
| GET | `/crm/organizations/{id}/people` | List organization's people |
| POST | `/crm/organizations/{id}/add-person` | Attach person |
| DELETE | `/crm/organizations/{id}/remove-person/{personId}` | Detach person |

#### Contacts (Person-Organization pivot)

| Method | Endpoint | Action |
|---|---|---|
| PUT | `/crm/contacts/{id}` | Update role/department |
| PUT | `/crm/contacts/{id}/primary` | Set as primary contact |

#### Addresses (polymorphic)

| Method | Endpoint | Action |
|---|---|---|
| GET | `/{entity}/{id}/addresses` | List addresses |
| POST | `/{entity}/{id}/addresses` | Create address |
| PUT | `/crm/addresses/{id}` | Update address |
| DELETE | `/crm/addresses/{id}` | Delete address |
| PUT | `/crm/addresses/{id}/default` | Set as default |

#### Tags

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/tags` | List all tags |
| POST | `/crm/tags` | Create tag |
| PUT | `/crm/tags/{id}` | Update tag |
| DELETE | `/crm/tags/{id}` | Delete tag |
| POST | `/{entity}/{entityId}/tags` | Attach tags |
| DELETE | `/{entity}/{entityId}/tags/{tagId}` | Detach tag |

#### Notes (polymorphic)

| Method | Endpoint | Action |
|---|---|---|
| GET | `/{entity}/{id}/notes` | List |
| POST | `/{entity}/{id}/notes` | Create |
| PUT | `/crm/notes/{id}` | Update |
| DELETE | `/crm/notes/{id}` | Soft delete |

#### Activities

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/activities` | List (filterable by type, date range, assignee) |
| POST | `/{entity}/{id}/activities` | Log activity |
| PUT | `/crm/activities/{id}` | Update |
| DELETE | `/crm/activities/{id}` | Soft delete |
| PUT | `/crm/activities/{id}/complete` | Mark complete |

#### Documents

| Method | Endpoint | Action |
|---|---|---|
| GET | `/{entity}/{id}/documents` | List |
| POST | `/{entity}/{id}/documents` | Upload (multipart) |
| GET | `/crm/documents/{id}/download` | Download (signed S3 URL or stream) |
| DELETE | `/crm/documents/{id}` | Soft delete (S3 + DB) |

#### Comments

| Method | Endpoint | Action |
|---|---|---|
| GET | `/{entity}/{id}/comments` | List |
| POST | `/{entity}/{id}/comments` | Create (with @mentions) |
| PUT | `/crm/comments/{id}` | Update |
| DELETE | `/crm/comments/{id}` | Soft delete |

#### Custom Fields

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/custom-fields` | List definitions |
| POST | `/crm/custom-fields` | Create definition |
| PUT | `/crm/custom-fields/{id}` | Update definition |
| DELETE | `/crm/custom-fields/{id}` | Delete definition |
| GET | `/{entity}/{id}/custom-fields` | Get all values for entity |
| PUT | `/{entity}/{id}/custom-fields` | Batch set values |

#### Leads

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/leads` | List (filtered by pipeline, stage, owner, source) |
| POST | `/crm/leads` | Create |
| GET | `/crm/leads/{id}` | Show |
| PUT | `/crm/leads/{id}` | Update |
| DELETE | `/crm/leads/{id}` | Soft delete |
| PUT | `/crm/leads/{id}/stage` | Move stage |
| PUT | `/crm/leads/{id}/qualify` | Qualify (attach person/organization) |
| PUT | `/crm/leads/{id}/convert` | Convert (to deal) |
| PUT | `/crm/leads/{id}/reassign` | Change owner |
| POST | `/crm/leads/bulk/move-stage` | Bulk move stage |

#### Pipelines

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/pipelines` | List (with stages) |
| POST | `/crm/pipelines` | Create |
| PUT | `/crm/pipelines/{id}` | Update |
| DELETE | `/crm/pipelines/{id}` | Archive (soft delete) |
| POST | `/crm/pipelines/{id}/stages` | Create stage |
| PUT | `/crm/pipelines/{id}/stages/{stageId}` | Update stage |
| DELETE | `/crm/pipelines/{id}/stages/{stageId}` | Delete stage |
| PUT | `/crm/pipelines/{id}/stages/reorder` | Reorder stages |

#### Tasks

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/tasks` | List (filtered by status, priority, assignee, due date) |
| POST | `/crm/tasks` | Create |
| GET | `/crm/tasks/{id}` | Show |
| PUT | `/crm/tasks/{id}` | Update |
| DELETE | `/crm/tasks/{id}` | Soft delete |
| PUT | `/crm/tasks/{id}/complete` | Mark complete |
| PUT | `/crm/tasks/{id}/reassign` | Reassign |
| POST | `/crm/tasks/{id}/reminders` | Add reminder |
| PUT | `/crm/tasks/{id}/recurrence` | Set recurrence |

#### Teams

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/teams` | List |
| POST | `/crm/teams` | Create |
| PUT | `/crm/teams/{id}` | Update |
| DELETE | `/crm/teams/{id}` | Soft delete |
| GET | `/crm/teams/{id}/members` | List members |
| POST | `/crm/teams/{id}/members` | Add member |
| PUT | `/crm/teams/{id}/members/{userId}` | Update member role |
| DELETE | `/crm/teams/{id}/members/{userId}` | Remove member |

#### Search

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/search?q=term&type=people,leads` | Unified search across entities |

#### Dashboard

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/dashboard/summary` | Summary counts |
| GET | `/crm/dashboard/lead-pipeline` | Pipeline funnel data |
| GET | `/crm/dashboard/activity-timeline` | Recent activities |
| GET | `/crm/dashboard/upcoming-tasks` | Tasks due soon |

#### Reports

| Method | Endpoint | Action |
|---|---|---|
| GET | `/crm/reports/sales` | Sales report |
| GET | `/crm/reports/activity` | Activity report |
| GET | `/crm/reports/team` | Team performance report |
| GET | `/crm/reports/export/{type}` | Export CSV/PDF |

### 11.5 Filtering & Sorting

All list endpoints support Spatie Query Builder syntax:

```
GET /crm/people?filter[status]=active&filter[owner_id]=42&sort=last_name&include=organizations,addresses
```

### 11.6 Bulk Operations

```
POST /crm/leads/bulk/move-stage
{
    "ids": [1, 2, 3],
    "stage_id": 5,
    "reason": "Bulk qualification"
}
```

Returns `{status, message, data: {succeeded: [1,2], failed: [{id: 3, error: "Invalid transition"}]}}`.

### 11.7 API Versioning

- URL-based: `/api/tenant/v1/crm/...`
- Breaking changes increment the version
- Old versions are deprecated with a sunset header: `Sunset: Sat, 19 Jun 2027 00:00:00 GMT`
- Deprecation header: `Deprecation: true`

---

## 12. Reporting Layer

### 12.1 Report Architecture

```
ReportService
├── CrmReport            ← CRM-wide metrics
├── SalesReport          ← Lead/pipeline/sales metrics
├── ActivityReport       ← Activity/task metrics
├── TeamReport           ← Team performance metrics
└── ReportExporter       ← CSV/PDF generation
```

### 12.2 CRM Dashboard

| Metric | Source | Cache |
|---|---|---|
| Total People | `crm_people` count | 5 min |
| Total Organizations | `crm_organizations` count | 5 min |
| Active Leads | `crm_leads` where status != won/lost | 5 min |
| Tasks Due Today | `crm_tasks` where due_at = today | 1 min |
| Overdue Tasks | `crm_tasks` where due_at < now and status != completed | 1 min |
| Recent Activities (7d) | `crm_activities` where created_at > 7d ago | 10 min |

### 12.3 Sales Dashboard

| Metric | Source | Cache |
|---|---|---|
| Leads by Pipeline | Group by pipeline_id | 5 min |
| Leads by Stage (funnel) | Group by stage_id within pipeline | 5 min |
| Conversion Rate | Won leads / total leads (time range) | 15 min |
| Lead Velocity | Leads created per day (14d trend) | 1 hour |
| Avg Time to Close | Avg days from created_at to converted_at | 1 hour |
| Top Sources | Group by lead_source_id | 10 min |
| Top Owners | Group by owner_id | 10 min |
| Pipeline Value | SUM(value) of open leads | 5 min |

### 12.4 Activity Dashboard

| Metric | Source | Cache |
|---|---|---|
| Activities by Type | Group by type | 5 min |
| Activities per User | Group by assigned_to | 5 min |
| Day-over-Day Trend | Activities per day (14d) | 1 hour |
| Completion Rate | Completed / total tasks (time range) | 10 min |

### 12.5 Team Dashboard

| Metric | Source | Cache |
|---|---|---|
| Person-specific metrics | Per team member | 5 min |
| Team comparison | Side-by-side performance | 10 min |
| Leaderboard | By leads created, activities logged, tasks completed | 1 hour |

### 12.6 Report Generation

- Reports are generated asynchronously via Jobs (for large datasets)
- Cached for 1 hour minimum
- Exported as CSV or PDF
- Report filters: date range, user, pipeline, team, source

---

## 13. Module Boundaries & Extensibility

### 13.1 How Industry Modules Extend CRM Core

```
CRM Core (generic)                  Solar Module (specific)
─────────────────                   ─────────────────────
crm_people          ────────        solar_installations.person_id (FK)
crm_organizations   ────────        solar_installations.organization_id (FK)
crm_addresses       ────────        solar_installations.address_id (FK)
crm_leads           ────────        solar_proposals.lead_id (FK)
crm_pipelines       ────────        Entity type scoped to 'solar'
crm_activities      ────────        Solar-specific activity types
crm_documents       ────────        Solar-specific document types
crm_tags            ────────        Solar-specific tag conventions
crm_tasks           ────────        Solar-specific task types
crm_custom_fields   ────────        Solar-specific field definitions
```

### 13.2 Module Contract

Each industry module must:

1. **Register** a Service Provider that loads its routes, views, migrations
2. **Extend** CRM Core models via `hasMany` / `belongsTo` (never modify CRM Core migrations)
3. **Scope** pipelines by setting `entity_type` (e.g., `'solar'`) on the Pipeline
4. **Add** module-specific permissions prefixed (e.g., `solar.installations.view`)
5. **Register** feature gates via `FeatureGateService::registerModuleFeatures('solar', [...])`
6. **Add** module-specific activity types to the `ActivityType` enum (via config merge)
7. **Add** module-specific report metrics to the `ReportService`

### 13.3 Pipeline Entity Scoping

```php
// CRM Core Pipeline — generic (entity_type = null)
Pipeline::create(['name' => 'Sales Pipeline', 'entity_type' => null]);

// Solar Pipeline — scoped (entity_type = 'solar')
Pipeline::create(['name' => 'Solar Pipeline', 'entity_type' => 'solar']);

// Lead query scoped to module
Lead::whereHas('pipeline', fn ($q) => $q->where('entity_type', 'solar'));
```

### 13.4 Custom Field Scoping

```php
// CRM Core custom field for all people
CustomFieldDefinition::create([
    'entity_type' => 'person',
    'name' => 'Source',
]);

// Solar-specific custom field
CustomFieldDefinition::create([
    'entity_type' => 'solar::installation',
    'name' => 'Panel Count',
    'type' => 'number',
]);
```

### 13.5 What Modules CANNOT Do

- Cannot modify CRM Core migrations
- Cannot override CRM Core controllers or actions
- Cannot delete CRM Core data (only append)
- Cannot bypass CRM Core permissions (must use their own)
- Cannot introduce tenant-level breaking changes to CRM Core tables

---

## 14. Scalability Considerations

### 14.1 Database

| Entity | Estimated Growth | Index Strategy |
|---|---|---|
| crm_people | High (up to 500K/tenant) | Composite indexes on `(tenant_id, status)`, `(tenant_id, last_name)`, partial index on `email WHERE NOT NULL` |
| crm_organizations | Medium (up to 50K/tenant) | Composite `(tenant_id, name)` gin_trgm for fuzzy search |
| crm_activities | Very High (2M+/tenant) | Partition by month, composite `(tenant_id, activitable_type, activitable_id)`, TTL archive job |
| crm_tasks | Medium | Composite `(tenant_id, status, due_at)` |
| crm_notes | Medium | Composite `(tenant_id, notable_type, notable_id)` |
| crm_activity_feed (view) | High | Materialized view refresh every 5 min |

### 14.2 Caching Strategy

| Data | Cache | TTL | Invalidation |
|---|---|---|---|
| Dashboard counts | Redis tagged (`crm:{tenant_id}:dashboard`) | 5 min | Observer events |
| Pipeline stages | Redis (`crm:{tenant_id}:pipeline:{id}:stages`) | 1 hour | Pipeline observer |
| Lead stages | Redis (`crm:{tenant_id}:lead:{id}:stage`) | 15 min | Lead stage change event |
| Feature gates | Redis (`crm:{tenant_id}:features`) | 1 hour | Plan change webhook |
| Usage counters | Redis increment/decrement | Real-time | Hourly DB sync |
| Report aggregations | Redis tagged | 1 hour | Manual refresh |
| Custom field definitions | Redis (`crm:{tenant_id}:custom_fields`) | 1 hour | Definition update event |

### 14.3 Query Optimization

- All list endpoints use `paginate()` (page-based for normal usage, cursor-based for activity logs if > 100K rows)
- Spatie Query Builder allowed filters match indexed columns
- `chunk(100)` for all background jobs processing CRM data
- Eager loading via `->with()` controlled in Service layer (never in controller)
- Full-text search on people/organizations via Scout (Meilisearch recommended)

### 14.4 Background Jobs

| Job | Trigger | Purpose |
|---|---|---|
| GenerateReport | Report request | Async report generation |
| SyncUsageLimits | Hourly schedule | Recalculate usage counters |
| ArchiveOldActivities | Daily schedule | Move activities > 12 months to archive |
| ProcessDocumentUpload | Document create | Optimize image, generate thumbnail |
| SendTaskReminders | Every minute | Fire due reminders |

### 14.5 Rate Limiting

- All CRM API endpoints: 100 requests/minute per user
- Bulk operations: 10 requests/minute
- Import endpoints: 1 request/minute
- Export endpoints: 5 requests/minute
- Rate limit headers in every response: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

---

## 15. Future Compatibility

### 15.1 Solar Module Compatibility

The Solar Module will:

| Requirement | CRM Core Support |
|---|---|
| Solar leads are CRM leads | `crm_leads` with pipeline entity_type = 'solar' |
| Solar customers are CRM people | `crm_people` with solar-specific tags, custom fields |
| Solar companies are CRM organizations | `crm_organizations` |
| Site survey addresses are CRM addresses | `crm_addresses` with type = 'site_survey' |
| Solar proposals extend CRM leads | `solar_proposals.lead_id` FK → `crm_leads.id` |
| Solar installations reference people/orgs | `solar_installations.person_id`, `organization_id`, `address_id` |
| Solar pipeline is a CRM pipeline | Entity type scoped to 'solar' |
| Solar activities are CRM activities | Activity type scoped (e.g., 'site_survey', 'installation') |
| Solar documents are CRM documents | Document type scoped (e.g., 'site_plan', 'permit') |
| Solar tasks are CRM tasks | Linked via morph `taskable` |

### 15.2 Agency Module Compatibility

| Requirement | CRM Core Support |
|---|---|
| Agency contacts are CRM people | `crm_people` with role = 'agency_contact' |
| Agency clients are CRM organizations | `crm_organizations` with industry = 'agency_client' |
| Client campaigns extend CRM leads | `crm_leads` with pipeline entity_type = 'agency' |
| Agency documents are CRM documents | Document type scoped ('creative_brief', 'campaign_report') |

### 15.3 Real Estate Module Compatibility

| Requirement | CRM Core Support |
|---|---|
| Property addresses are CRM addresses | `crm_addresses` with type = 'property' |
| Property owners are CRM people | `crm_people` with tags including 'property_owner' |
| Buyer/renter prospects are CRM people | `crm_people` with tags including 'buyer', 'renter' |
| Property listings extend CRM data | `real_estate_properties` FK → `crm_addresses.id`, `crm_organizations.id` |
| Property pipeline is a CRM pipeline | Pipeline entity_type = 'real_estate' |
| Viewings are CRM activities | Activity type = 'viewing' |
| Property documents are CRM documents | Document type = 'property_doc' |

### 15.4 Client Portal Compatibility

- Client Portal consumes the same API endpoints via Sanctum tokens scoped to `client` guard
- A `ClientUser` is mapped to a `Person` via `client_user.person_id`
- Portal users see only their own Person, Organization, and related Leads/Tasks/Activities
- Permission scope for portal: `portal.people.view`, `portal.tasks.view`, `portal.documents.view`
- Portal is a separate Laravel app or SPA that calls the same API

### 15.5 Mobile App (React Native) Compatibility

- React Native app uses the same API endpoints
- Authentication via Sanctum token obtained from login endpoint
- Push notifications via Firebase Cloud Messaging
- Offline support via local SQLite + background sync queue
- Mobile-specific endpoints return trimmed payloads (fewer fields, no meta wrapper for lists)
- Mobile app headers: `X-Client: react-native`, `X-Client-Version: 1.0.0`

### 15.6 Laravel Scout Search

All major CRM entities should implement `Searchable`:

| Entity | Search Fields |
|---|---|
| Person | first_name, last_name, email, phone, mobile |
| Organization | name, email, website, phone |
| Lead | email, phone, company_name |
| Note | body |
| Comment | body |

Meilisearch recommended for speed. Database engine fallback for smaller tenants.

---

## Appendix A: Key Design Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Single `crm_people` table for all person types | Industry modules add FKs, avoid table-per-type complexity |
| 2 | Polymorphic morphs for notes/activities/documents/comments | Avoids 20+ pivot tables, enables any entity to have any attachment |
| 3 | EAV via `custom_field_definitions` + `custom_field_values` | Dynamic fields without schema changes; JSON column per value for type flexibility |
| 4 | Pipeline `entity_type` nullable | Null = generic CRM pipeline, module string = scoped pipeline |
| 5 | Task `taskable` polymorphic | Any entity can have tasks without adding FK columns |
| 6 | Wasabi S3 for documents | S3-compatible, lower cost than AWS, 11x9 durability |
| 7 | Actions pattern | Single-responsibility testable units; controllers are thin passthrough |
| 8 | Feature gates as middleware + service | Consistent enforcement at route and code level |
| 9 | Usage tracking via cached counters | Avoids COUNT queries on every create; hourly DB sync ensures accuracy |
| 10 | Separate module directory per industry | Encapsulated, independently deployable, no CRM Core changes needed |

## Appendix B: Naming Conventions

| Convention | Rule | Example |
|---|---|---|
| Table names | `crm_{plural}` | `crm_people`, `crm_organizations` |
| Pivot tables | `crm_{singular}_{singular}` | `crm_taggables`, `crm_contacts` |
| Morph columns | `{type}_type`, `{type}_id` | `notable_type`, `notable_id` |
| Foreign keys | `{singular}_id` | `person_id`, `organization_id` |
| Enums | PascalCase | `PersonStatus::Active`, `ActivityType::Call` |
| Actions | Imperative verb | `CreatePerson`, `MoveLeadStage` |
| Services | `{Entity}Service` | `PersonService`, `LeadService` |
| Controllers | `{Entity}Controller` | `PersonController`, `LeadController` |
| Form Requests | `{Action}{Entity}Request` | `StorePersonRequest`, `MoveLeadStageRequest` |
| Resources | `{Entity}Resource` | `PersonResource`, `LeadResource` |
| Events | Past tense verb | `PersonCreated`, `LeadStageChanged` |
| Permissions | `{entity}.{action}` | `people.create`, `leads.move_stage` |
| Routes | `/crm/{entities}` | `/crm/people`, `/crm/leads` |

## Appendix C: CRM Core vs Module Responsibility Matrix

| Capability | CRM Core | Solar Module | Agency Module | Real Estate Module |
|---|---|---|---|---|
| People management | ✅ | — | — | — |
| Organization management | ✅ | — | — | — |
| Address management | ✅ | — | — | — |
| Universal tagging | ✅ | — | — | — |
| Notes (polymorphic) | ✅ | — | — | — |
| Activities (polymorphic) | ✅ | — | — | — |
| Documents (polymorphic) | ✅ | — | — | — |
| Comments (polymorphic) | ✅ | — | — | — |
| Custom fields (EAV) | ✅ | — | — | — |
| Lead management | ✅ | — | — | — |
| Pipelines & stages | ✅ | — | — | — |
| Task management | ✅ | — | — | — |
| Teams & collaboration | ✅ | — | — | — |
| Search (Scout) | ✅ | — | — | — |
| Feature gates | ✅ | — | — | — |
| Reporting (generic) | ✅ | — | — | — |
| Solar-specific pipelines | — | ✅ | — | — |
| Solar proposals | — | ✅ | — | — |
| Solar installations | — | ✅ | — | — |
| Campaign management | — | — | ✅ | — |
| Client briefs | — | — | ✅ | — |
| Property listings | — | — | — | ✅ |
| Property viewings | — | — | — | ✅ |
| Lease/rental management | — | — | — | ✅ |

---

*End of FollowKa Tenant CRM Core Specification v1*

---

**Next Steps:**
1. Review and approve this specification
2. Begin Phase 1 implementation (migrations + models + factories)
3. Implement Action classes per entity
4. Implement Service layer with FeatureGate integration
5. Implement Controllers + Filters + Resources
6. Implement Tests (Pest, one test file per entity)
7. Implement Reports + Dashboard
8. Begin Solar Module implementation
