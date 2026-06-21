# Central Admin Implementation Report

Generated from `feature/followka-central-admin` branch

---

## Overview

Complete implementation of the FollowKa Central Admin panel — 12 new modules built on top of the existing tenant management system. All modules follow existing conventions: service layer, thin controllers, Spatie policies, API resources, form requests, and Pest feature tests.

**Branch:** `feature/followka-central-admin`
**Base:** `main`

---

## Phase 1 — Security & Foundation Fixes

### 1.1 Sanctum Token Scoping

**Status:** ✅ Fixed

**Files Changed:**
- `app/Http/Resources/Central/Api/V1/Auth/LoginResource.php` — Tokens now scoped to `$user->getAllPermissions()->pluck('name')`
- `app/Http/Resources/Tenant/Api/V1/Auth/LoginResource.php` — Same fix for tenant API

Tokens no longer use wildcard `['central:*']` / `['tenant:*']` abilities. Each token carries only the specific permissions the user has.

### 1.2 TenantObserver Cascade

**Status:** ✅ Fixed

**Files Changed:** `app/Observers/TenantObserver.php`

Added cascade restore and force-delete for:
- `users` (tenant users)
- `domains`
- `subscriptions`
- Spatie pivot tables (`model_has_roles`, `model_has_permissions`)

### 1.3 Subscription Status Transitions

**Status:** ✅ Fixed

**Files Changed:** `app/Services/Central/SubscriptionService.php`

Added validation for valid status transitions:
- `trial → active`, `trial → cancelled`, `trial → expired`
- `active → suspended`, `active → cancelled`, `active → expired`
- `suspended → active`
- Auto-sets `cancelled_at` when cancelling, `suspended_at` when suspending

### 1.4 Database Indexes

**Status:** ✅ Fixed

**Migration:** `2026_06_18_231552_add_subscription_indexes_and_permissions_fk`

Added:
- Composite index on `subscriptions(tenant_id, status, ends_at)`
- Timestamp columns: `trial_ends_at`, `cancelled_at`, `suspended_at`

---

## Phase 2 — 12 Central Admin Modules

### 1. Dashboard

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/dashboard` | `DashboardController` | Real stats: tenant counts, user counts, plans, features, subscriptions, MRR/ARR, recent activity |

**Auth:** No permission gate (public within authenticated session)

---

### 2. Invoices

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/invoices` | `InvoiceController@index` | List invoices |
| `POST /api/central/v1/invoices` | `InvoiceController@store` | Create invoice |
| `GET /api/central/v1/invoices/{invoice}` | `InvoiceController@show` | Show invoice |
| `PUT /api/central/v1/invoices/{invoice}` | `InvoiceController@update` | Update invoice |
| `DELETE /api/central/v1/invoices/{invoice}` | `InvoiceController@destroy` | Soft delete |
| `POST /api/central/v1/invoices/{invoice}/restore` | `InvoiceController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/invoices/{invoice}/force` | `InvoiceController@forceDelete` | Force delete |
| `POST /api/central/v1/invoices/{invoice}/mark-paid` | `InvoiceController@markPaid` | Mark as paid |
| `POST /api/central/v1/invoices/{invoice}/mark-overdue` | `InvoiceController@markOverdue` | Mark as overdue |

**Service:** `InvoiceService` — generates invoice numbers (`INV-{year}{counter}`), handles `total_amount` calculation, status transitions
**Permissions:** `invoices.list`, `invoices.read`, `invoices.create`, `invoices.update`, `invoices.delete`, `invoices.restore`, `invoices.force.delete`

---

### 3. Payments

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/payments` | `PaymentController@index` | List payments |
| `POST /api/central/v1/payments` | `PaymentController@store` | Create payment |
| `GET /api/central/v1/payments/{payment}` | `PaymentController@show` | Show payment |
| `PUT /api/central/v1/payments/{payment}` | `PaymentController@update` | Update payment |
| `DELETE /api/central/v1/payments/{payment}` | `PaymentController@destroy` | Soft delete |
| `POST /api/central/v1/payments/{payment}/restore` | `PaymentController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/payments/{payment}/force` | `PaymentController@forceDelete` | Force delete |
| `POST /api/central/v1/payments/{payment}/complete` | `PaymentController@markCompleted` | Mark completed (requires `transaction_id`) |
| `POST /api/central/v1/payments/{payment}/fail` | `PaymentController@markFailed` | Mark failed |
| `POST /api/central/v1/payments/{payment}/refund` | `PaymentController@markRefunded` | Mark refunded |

**Service:** `PaymentService` — validates status transitions (pending → completed/failed, completed → refunded)
**Permissions:** `payments.list`, `payments.read`, `payments.create`, `payments.update`, `payments.delete`, `payments.restore`, `payments.force.delete`

---

### 4. Coupons

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/coupons` | `CouponController@index` | List coupons |
| `POST /api/central/v1/coupons` | `CouponController@store` | Create coupon |
| `GET /api/central/v1/coupons/{coupon}` | `CouponController@show` | Show coupon |
| `PUT /api/central/v1/coupons/{coupon}` | `CouponController@update` | Update coupon |
| `DELETE /api/central/v1/coupons/{coupon}` | `CouponController@destroy` | Soft delete |
| `POST /api/central/v1/coupons/{coupon}/restore` | `CouponController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/coupons/{coupon}/force` | `CouponController@forceDelete` | Force delete |
| `POST /api/central/v1/coupons/validate` | `CouponController@validateCoupon` | Validate coupon code |
| `POST /api/central/v1/coupons/apply` | `CouponController@apply` | Apply coupon to amount |

**Service:** `CouponService` — `validateCoupon()` checks active, date range, usage limit; `applyCoupon()` validates then applies and marks used
**Model Methods:** `isValid()`, `apply(float $amount): float`, `markUsed()`
**Permissions:** `coupons.list`, `coupons.read`, `coupons.create`, `coupons.update`, `coupons.delete`, `coupons.restore`, `coupons.force.delete`

---

### 5. Announcements

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/announcements` | `AnnouncementController@index` | List announcements |
| `POST /api/central/v1/announcements` | `AnnouncementController@store` | Create announcement |
| `GET /api/central/v1/announcements/{announcement}` | `AnnouncementController@show` | Show announcement |
| `PUT /api/central/v1/announcements/{announcement}` | `AnnouncementController@update` | Update announcement |
| `DELETE /api/central/v1/announcements/{announcement}` | `AnnouncementController@destroy` | Soft delete |
| `POST /api/central/v1/announcements/{announcement}/restore` | `AnnouncementController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/announcements/{announcement}/force` | `AnnouncementController@forceDelete` | Force delete |

**Service:** `AnnouncementService` — supports audience targeting (`all`, `tenant`, `plan`, `custom`)
**Model Scopes:** `scopeActive()` — filters by `is_active`, date range
**Permissions:** `announcements.list`, `announcements.read`, `announcements.create`, `announcements.update`, `announcements.delete`, `announcements.restore`, `announcements.force.delete`

---

### 6. Support Tickets

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/tickets` | `TicketController@index` | List tickets |
| `POST /api/central/v1/tickets` | `TicketController@store` | Create ticket |
| `GET /api/central/v1/tickets/{ticket}` | `TicketController@show` | Show ticket |
| `PUT /api/central/v1/tickets/{ticket}` | `TicketController@update` | Update ticket |
| `DELETE /api/central/v1/tickets/{ticket}` | `TicketController@destroy` | Soft delete |
| `POST /api/central/v1/tickets/{ticket}/restore` | `TicketController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/tickets/{ticket}/force` | `TicketController@forceDelete` | Force delete |
| `POST /api/central/v1/tickets/{ticket}/assign` | `TicketController@assign` | Assign to admin |
| `POST /api/central/v1/tickets/{ticket}/replies` | `TicketController@addReply` | Add reply |

**Service:** `TicketService` — generates ticket numbers (`TKT-{year}{counter}`), validates assignment, creates replies with auto-status update
**Model:** `TicketReply` — child model with `ticket_id`, `user_id`, `content`
**Permissions:** `tickets.list`, `tickets.read`, `tickets.create`, `tickets.update`, `tickets.delete`, `tickets.restore`, `tickets.force.delete`

---

### 7. Activity Logs

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/activity-logs` | `ActivityLogController@index` | Paginated activity logs |
| `GET /api/central/v1/activity-logs/{id}` | `ActivityLogController@show` | Single activity log |

**Package:** `spatie/laravel-activitylog` (wrapped, no new migration)
**Auth:** `users.list` permission
**Note:** Read-only — logs are created by other controllers via `activity()->log()`

---

### 8. Audit Logs

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/audit-logs` | `AuditLogController@index` | Paginated audit logs with before/after values |

**Package:** `spatie/laravel-activitylog` — filtered to `log_name = 'audit'`
**Auth:** `users.list` permission
**Note:** Read-only. Captures model attribute changes with old/new values.

---

### 9. API Keys

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/api-keys` | `ApiKeyController@index` | List API keys (key masked) |
| `POST /api/central/v1/api-keys` | `ApiKeyController@store` | Create with generated 64-char key |
| `GET /api/central/v1/api-keys/{api_key}` | `ApiKeyController@show` | Show API key |
| `PUT /api/central/v1/api-keys/{api_key}` | `ApiKeyController@update` | Update name/permissions |
| `DELETE /api/central/v1/api-keys/{api_key}` | `ApiKeyController@destroy` | Soft delete |
| `POST /api/central/v1/api-keys/{api_key}/restore` | `ApiKeyController@restore` | Restore soft-deleted |
| `DELETE /api/central/v1/api-keys/{api_key}/force` | `ApiKeyController@forceDelete` | Force delete |
| `POST /api/central/v1/api-keys/{api_key}/regenerate` | `ApiKeyController@regenerate` | Generate new key (same record) |
| `POST /api/central/v1/api-keys/{api_key}/revoke` | `ApiKeyController@revoke` | Set `expires_at` to now |

**Service:** `ApiKeyService` — generates 64-char random keys, masks key in list response (`sk-...XXXX`)
**Model Methods:** `generateKey()`, `regenerate()`, `markUsed()`
**Permissions:** `api-keys.list`, `api-keys.read`, `api-keys.create`, `api-keys.update`, `api-keys.delete`, `api-keys.restore`, `api-keys.force.delete`

---

### 10. Module Management

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/modules` | `ModuleController@index` | List modules (paginated) |
| `GET /api/central/v1/modules/{module}` | `ModuleController@show` | Show module |
| `POST /api/central/v1/modules/seed` | `ModuleController@seed` | Seed 4 default modules |
| `POST /api/central/v1/modules/{module}/enable` | `ModuleController@enable` | Enable globally |
| `POST /api/central/v1/modules/{module}/disable` | `ModuleController@disable` | Disable globally |
| `POST /api/central/v1/modules/{module}/enable-for-tenant` | `ModuleController@enableForTenant` | Enable per-tenant |
| `POST /api/central/v1/modules/{module}/disable-for-tenant` | `ModuleController@disableForTenant` | Disable per-tenant |

**Migration:** `create_modules_table` — `modules` table + `tenant_module` pivot
**Model Methods:** `enable()`, `disable()`, `enableForTenant($tenant)`, `disableForTenant($tenant)`
**Auth:** `modules.list`, `modules.read`, `modules.create`, `modules.update`
**Default Modules seeded:** CRM Core, Solar, Agency, Real Estate

---

### 11. Impersonation

| Route | Controller | Description |
|-------|------------|-------------|
| `POST /api/central/v1/impersonation/start/{tenant}` | `ImpersonationController@start` | Get tenant user token |
| `POST /api/central/v1/impersonation/stop` | `ImpersonationController@stop` | End impersonation context |

**Feature:** `Stancl\Tenancy\Features\UserImpersonation` enabled in `config/tenancy.php`
**Auth:** `tenants.read` permission (start), no gate (stop)
**Activity Log:** Logs impersonation start/stop events with tenant info in properties

---

### 12. Tenant Settings (Admin)

| Route | Controller | Description |
|-------|------------|-------------|
| `GET /api/central/v1/tenants/{tenant}/settings` | `TenantSettingController@index` | List settings for tenant (merged with defaults) |
| `PUT /api/central/v1/tenants/{tenant}/settings` | `TenantSettingController@update` | Bulk update settings |

**Auth:** `tenant.list` (view), `tenant.update` (update)
**Note:** Reads `SettingDefinition` for schema, `TenantSetting` for values, returns merged result grouped by `group`

---

## Files Created / Modified

### New Files (Models)
| File | Type |
|------|------|
| `app/Models/Invoice.php` | Model with SoftDeletes, relationships |
| `app/Models/Payment.php` | Model with SoftDeletes, relationships |
| `app/Models/Coupon.php` | Model with SoftDeletes, `isValid()`, `apply()`, `markUsed()` |
| `app/Models/Announcement.php` | Model with SoftDeletes, `scopeActive()` |
| `app/Models/Ticket.php` | Model with SoftDeletes, relationships |
| `app/Models/TicketReply.php` | Model |
| `app/Models/ApiKey.php` | Model with SoftDeletes, `generateKey()`, `regenerate()` |
| `app/Models/Module.php` | Model with SoftDeletes, `tenants()` BelongsToMany |

### New Files (Services)
| File | Description |
|------|-------------|
| `app/Services/Central/InvoiceService.php` | Number generation, CRUD, status methods |
| `app/Services/Central/PaymentService.php` | Status transition validation |
| `app/Services/Central/CouponService.php` | Validate/apply with usage tracking |
| `app/Services/Central/AnnouncementService.php` | Audience targeting |
| `app/Services/Central/TicketService.php` | Number generation, assign, replies |
| `app/Services/Central/ApiKeyService.php` | Generate, regenerate, revoke, key masking |

### New Files (Controllers)
| File | Endpoints |
|------|-----------|
| `app/Http/Controllers/Central/Api/V1/DashboardController.php` | 1 |
| `app/Http/Controllers/Central/Api/V1/InvoiceController.php` | 9 |
| `app/Http/Controllers/Central/Api/V1/PaymentController.php` | 10 |
| `app/Http/Controllers/Central/Api/V1/CouponController.php` | 9 |
| `app/Http/Controllers/Central/Api/V1/AnnouncementController.php` | 7 |
| `app/Http/Controllers/Central/Api/V1/TicketController.php` | 9 |
| `app/Http/Controllers/Central/Api/V1/ActivityLogController.php` | 2 |
| `app/Http/Controllers/Central/Api/V1/AuditLogController.php` | 1 |
| `app/Http/Controllers/Central/Api/V1/ApiKeyController.php` | 9 |
| `app/Http/Controllers/Central/Api/V1/ModuleController.php` | 7 |
| `app/Http/Controllers/Central/Api/V1/ImpersonationController.php` | 2 |
| `app/Http/Controllers/Central/Api/V1/TenantSettingController.php` | 2 |

### New Files (Policies)
| File | Permissions |
|------|-------------|
| `app/Policies/InvoicePolicy.php` | 7 |
| `app/Policies/PaymentPolicy.php` | 7 |
| `app/Policies/CouponPolicy.php` | 7 |
| `app/Policies/AnnouncementPolicy.php` | 7 |
| `app/Policies/TicketPolicy.php` | 7 |
| `app/Policies/ApiKeyPolicy.php` | 7 |
| `app/Policies/ModulePolicy.php` | 4 |
| `app/Policies/TenantSettingPolicy.php` | 2 |

### New Files (Form Requests)
| File | Rules |
|------|-------|
| `app/Http/Requests/Central/Api/V1/Invoice/StoreInvoiceRequest.php` | tenant_id, amount required |
| `app/Http/Requests/Central/Api/V1/Invoice/UpdateInvoiceRequest.php` | All optional |
| `app/Http/Requests/Central/Api/V1/Payment/StorePaymentRequest.php` | tenant_id, amount required |
| `app/Http/Requests/Central/Api/V1/Payment/UpdatePaymentRequest.php` | All optional |
| `app/Http/Requests/Central/Api/V1/Payment/CompletePaymentRequest.php` | transaction_id required |
| `app/Http/Requests/Central/Api/V1/Coupon/StoreCouponRequest.php` | code, type, amount required |
| `app/Http/Requests/Central/Api/V1/Coupon/UpdateCouponRequest.php` | All optional |
| `app/Http/Requests/Central/Api/V1/Coupon/ApplyCouponRequest.php` | code, amount required |
| `app/Http/Requests/Central/Api/V1/Announcement/StoreAnnouncementRequest.php` | title, content required |
| `app/Http/Requests/Central/Api/V1/Announcement/UpdateAnnouncementRequest.php` | All optional |
| `app/Http/Requests/Central/Api/V1/Ticket/StoreTicketRequest.php` | tenant_id, subject, description required |
| `app/Http/Requests/Central/Api/V1/Ticket/UpdateTicketRequest.php` | All optional |
| `app/Http/Requests/Central/Api/V1/Ticket/AssignTicketRequest.php` | assigned_to required |
| `app/Http/Requests/Central/Api/V1/Ticket/StoreTicketReplyRequest.php` | content required |
| `app/Http/Requests/Central/Api/V1/ApiKey/StoreApiKeyRequest.php` | name required |
| `app/Http/Requests/Central/Api/V1/ApiKey/UpdateApiKeyRequest.php` | All optional |

### New Files (API Resources)
| File | Type |
|------|------|
| `app/Http/Resources/Central/Api/V1/Invoice/InvoiceResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/Invoice/ListInvoiceResource.php` | List |
| `app/Http/Resources/Central/Api/V1/Payment/PaymentResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/Payment/ListPaymentResource.php` | List |
| `app/Http/Resources/Central/Api/V1/Coupon/CouponResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/Coupon/ListCouponResource.php` | List |
| `app/Http/Resources/Central/Api/V1/Announcement/AnnouncementResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/Announcement/ListAnnouncementResource.php` | List |
| `app/Http/Resources/Central/Api/V1/Ticket/TicketResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/Ticket/ListTicketResource.php` | List |
| `app/Http/Resources/Central/Api/V1/ApiKey/ApiKeyResource.php` | Detail |
| `app/Http/Resources/Central/Api/V1/ApiKey/ListApiKeyResource.php` | List |

### New Files (Factories)
| File | Status |
|------|--------|
| `database/factories/Central/InvoiceFactory.php` | ✅ Working |
| `database/factories/Central/PaymentFactory.php` | ✅ Working |
| `database/factories/Central/CouponFactory.php` | ✅ Working |
| `database/factories/Central/AnnouncementFactory.php` | ✅ Working |
| `database/factories/Central/TicketFactory.php` | ✅ Working |
| `database/factories/Central/ApiKeyFactory.php` | ✅ Working |
| `database/factories/Central/ModuleFactory.php` | ✅ Working |

### Migrations
| File | Purpose |
|------|---------|
| `database/migrations/2026_06_18_231552_add_subscription_indexes_and_permissions_fk.php` | Indexes + timestamps |
| `database/migrations/2026_06_18_232834_create_modules_table.php` | Modules + pivot |

### Modified Files
| File | Changes |
|------|---------|
| `routes/central/v1.php` | All 12 module routes (+127 total central routes) |
| `config/tenancy.php` | Enabled `UserImpersonation` feature |
| `app/Observers/TenantObserver.php` | Cascade restore/force-delete |
| `app/Services/Central/SubscriptionService.php` | Status transition validation |

---

## Test Suite

### New Tests (89 tests, 244 assertions)
| Test File | Tests |
|-----------|-------|
| `tests/Feature/Central/Dashboard/DashboardTest.php` | 2 |
| `tests/Feature/Central/Invoice/InvoiceTest.php` | 10 |
| `tests/Feature/Central/Payment/PaymentTest.php` | 10 |
| `tests/Feature/Central/Coupon/CouponTest.php` | 11 |
| `tests/Feature/Central/Announcement/AnnouncementTest.php` | 9 |
| `tests/Feature/Central/Ticket/TicketTest.php` | 11 |
| `tests/Feature/Central/ActivityLog/ActivityLogTest.php` | 3 |
| `tests/Feature/Central/AuditLog/AuditLogTest.php` | 2 |
| `tests/Feature/Central/ApiKey/ApiKeyTest.php` | 11 |
| `tests/Feature/Central/Module/ModuleTest.php` | 9 |
| `tests/Feature/Central/Impersonation/ImpersonationTest.php` | 4 |
| `tests/Feature/Central/TenantSetting/TenantSettingTest.php` | 4 |

### Existing Tests (23 tests, all passing)
- `tests/Feature/Central/Auth/LoginTest.php`
- `tests/Feature/Central/Plan/PlanFeatureTest.php`
- `tests/Feature/Central/Subscription/SubscriptionEnforcementTest.php`

### Test Results
```
php artisan test --compact
=====================================
112 tests, 307 assertions
All passing ✅
```

---

## Postman Collection

**Files Updated:**
- `.docs/postman/Central.postman_collection.json` — 21 sections, 90+ requests
- `.docs/postman/Central.postman_environment.json` — 19 variables

All new endpoints added with bearer auth, proper request bodies, and test scripts that auto-save IDs.

---

## Factory Verification

All 14 factories verified via Tinker — both `make()` and `create()` operations:

| Factory | Type | `make()` | `create()` |
|---------|------|----------|------------|
| `CentralUserFactory` | Central | ✅ | ✅ |
| `TenantFactory` | Central | ✅ | ✅ |
| `UserFactory` | Root | n/a | ✅ |
| `PlanFactory` | Central | ✅ | ✅ |
| `FeatureFactory` | Central | ✅ | ✅ |
| `SettingDefinitionFactory` | Central | ✅ | ✅ |
| `SubscriptionFactory` | Central | ✅ | ✅ |
| `InvoiceFactory` | Central | ✅ | ✅ |
| `PaymentFactory` | Central | ✅ | ✅ |
| `CouponFactory` | Central | ✅ | ✅ |
| `AnnouncementFactory` | Central | ✅ | ✅ |
| `TicketFactory` | Central | ✅ | ✅ |
| `ApiKeyFactory` | Central | ✅ | ✅ |
| `ModuleFactory` | Central | ✅ | ✅ |

---

## Route Summary

```
php artisan route:list --except-vendor --path=api/central
=====================================
127 routes total
```

| Group | Routes |
|-------|--------|
| `auth.*` | 3 |
| `me.*` | 4 |
| `dashboard` | 1 |
| `tenants.*` | 7 |
| `tenant-settings.*` | 2 |
| `roles.*` | 5 |
| `users.*` | 9 |
| `plans.*` | 8 |
| `plans.features.*` | 4 |
| `features.*` | 7 |
| `setting-definitions.*` | 3 |
| `subscriptions.*` | 7 |
| `invoices.*` | 9 |
| `payments.*` | 10 |
| `coupons.*` | 9 |
| `announcements.*` | 7 |
| `tickets.*` | 9 |
| `activity-logs.*` | 2 |
| `audit-logs.*` | 1 |
| `api-keys.*` | 9 |
| `modules.*` | 7 |
| `impersonation.*` | 2 |

---

## Bugs Fixed During Implementation

| Bug | Fix |
|-----|-----|
| `CouponController::validate()` conflicts with `Controller::validate()` | Renamed to `validateCoupon()` |
| `resolveRouteBindingQuery` not overriding for soft-deletable models | Added `withTrashed()` to 7 models |
| `Coupon::apply()` returns string due to `decimal:2` cast | Cast to `float` before `min()` comparison |
| `TenantSettingPolicy::update()` requires model but `Gate::authorize('update', TenantSetting::class)` passes class | Made parameter nullable with default `null` |
| `activity()->performedOn($tenant)` fails because `subject_id` is bigint and tenant IDs are UUIDs | Replaced with `->withProperties()` |

---

## File Count

```
New files created:  70+
Files modified:     5
Migrations created: 2
Migration files:    8 total (all run)

Factories: 14 total (7 existed, 7 new)
```

---

*Generated from `feature/followka-central-admin` branch — June 19, 2026*
