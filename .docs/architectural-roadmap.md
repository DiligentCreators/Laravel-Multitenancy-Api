# Architectural Review & Implementation Roadmap

Generated from second-pass analysis of `D:\DiligentCreators\Laravel-Multitenancy-Api`

---

## Phase 1 — Critical Security Fixes

### 1.1 Sanctum Token Abilities Are Wildcards

**Severity:** Critical  
**Business Impact:** Any authenticated user has full API access regardless of their role/permissions. Token theft = complete account takeover.  
**Technical Impact:** `central-api` and `tenant-api` tokens use wildcard abilities `['central:*']` and `['tenant:*']` respectively. No granular scoping.

**Affected Files:**
- `app/Http/Resources/Central/Api/V1/Auth/LoginResource.php:22` — `$user->createToken('central-token', abilities: ['central:*'])`
- `app/Http/Resources/Tenant/Api/V1/Auth/LoginResource.php:22` — `$user->createToken('tenant-token', abilities: ['tenant:*'])`
- `config/abilities.php` — Defines granular abilities like `central:tenant:read`, `tenant:contacts:create` but they are never used

**Why this matters:** The `abilities` and `ability` middleware are registered in `bootstrap/app.php:24-26` but no route uses them. All authentication relies solely on `auth:central-api` and `auth:tenant-api` guards. Spatie policies (`Gate::authorize()`) provide the actual authorization, but the token has unrestricted ability scope. If an attacker obtains any valid token (XSS, leak, logs), they can call any endpoint the guard allows.

**Recommended Implementation:** Issue tokens scoped to the user's permissions or roles:

```php
// In LoginResource::toArray()
$abilities = $user->getAllPermissions()->pluck('name')->toArray();
$token = $user->createToken('central-token', $abilities);
```

Then add `CheckAbilities` middleware to route groups.

**Estimated Effort:** 2 hours  
**Dependencies:** None

---

### 1.2 Password Change Double-Hash Bug

**Severity:** Critical  
**Business Impact:** Users who change their password can never log in again with the new password. This creates permanent account lockout.  
**Technical Impact:** The `changePassword` method in `UserService` receives the already-hashed `$user->password` and calls `bcrypt()` on it again.

**Affected Files:**
- `app/Services/Central/UserService.php:99-104`
  ```php
  public function changePassword(CentralUser $user): void
  {
      $user->update([
          'password' => bcrypt($user->password), // $user->password is ALREADY hashed
      ]);
  }
  ```
- `app/Http/Controllers/Central/Api/V1/UserController.php:132` — Calls `$this->userService->changePassword($user)`

**Why this matters:** When an admin uses the "change password" endpoint for a user (via `POST /central/v1/users/{user}/change-password`), it takes the current hashed password, re-hashes it, and stores `bcrypt(bcrypt(original))`. Login with the new password will never match because Laravel's `Hash::check()` tries to verify the plaintext against the double-hashed value.

However, looking at the request flow — `UserController::changePassword` receives no request data at all. It calls `$this->userService->changePassword($user)` with no new password input. The controller docblock says "Change Password" but there's no request body, no form request, and no password field. This controller action appears to be unfinished or incorrectly implemented. The service reads `$user->password` (already hashed) and re-hashes it.

**Recommended Implementation:**
1. Create a `ChangeUserPasswordRequest` form request with `password` and `password_confirmation` fields
2. Pass the validated new password to the service
3. The service should hash the new plaintext password, not the old hash
4. Optionally revoke existing tokens for the user

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

### 1.3 Missing Rate Limiting on Auth Endpoints

**Severity:** High  
**Business Impact:** Unlimited brute-force password attempts against both central and tenant login.  
**Technical Impact:** No `throttle` middleware applied to any auth routes.

**Affected Files:**
- `routes/central/v1.php:77` — `Route::post('login', LoginController::class)->name('login')`
- `routes/central/v1.php:82` — `Route::post('forgot-password', ...)`
- `routes/central/v1.php:88` — `Route::post('reset-password', ...)`
- `routes/tenant/v1.php:70` — `Route::post('login', LoginController::class)->name('login')`
- `routes/tenant/v1.php:73` — `Route::post('forgot-password', ...)`
- `routes/tenant/v1.php:78` — `Route::post('reset-password', ...)`

**Why this matters:** All auth endpoints are publicly accessible (no auth middleware). An attacker can fire unlimited login attempts. The `exists:central_users,email` validation rule on login requests also enables email enumeration (see 1.4).

**Recommended Implementation:**
```php
// In routes/central/v1.php and routes/tenant/v1.php
Route::post('login', LoginController::class)
    ->middleware('throttle:5,1') // 5 attempts per minute
    ->name('login');
```

**Estimated Effort:** 10 minutes  
**Dependencies:** None

---

### 1.4 Email Enumeration via Login Exists Rule

**Severity:** High  
**Business Impact:** Attackers can determine which email addresses are registered.  
**Technical Impact:** The `exists` validation rule returns 422 for unknown emails and 400 for wrong passwords, creating an oracle.

**Affected Files:**
- `app/Http/Requests/Central/Api/V1/Auth/LoginRequest.php:12` — `'email' => ['required', 'email', 'exists:central_users,email']`
- `app/Http/Requests/Tenant/Api/V1/Auth/LoginRequest.php:12` — `'email' => ['required', 'email', 'exists:users,email']`

**Why this matters:** An attacker sends `POST /api/central/v1/auth/login` with `{"email": "unknown@example.com", "password": "anything"}`. The response is 422 with `{"errors": {"email": ["The selected email is invalid."]}}`. If the email exists but password is wrong, the response is 400 with `{"message": "The provided credentials are incorrect."}`. This allows building a list of valid emails.

**Recommended Implementation:** Remove the `exists` rule. The login controller already handles the "user not found" case implicitly (returns null from `::first()`), but returns error 400 instead of 422. The error message intentionally says the same thing for both wrong email and wrong password. But without the `exists` rule, `first()` returns null and the error message is identical, which is the correct behavior.

**Estimated Effort:** 5 minutes  
**Dependencies:** None

---

### 1.5 Suspended Users Can Still Log In

**Severity:** High  
**Business Impact:** Suspended users retain full API access. Suspension is effectively a no-op.  
**Technical Impact:** Neither `CentralUser` nor `User` (tenant) login controllers check the `is_suspended` flag.

**Affected Files:**
- `app/Http/Controllers/Central/Api/V1/Auth/LoginController.php:17` — `$user = CentralUser::where('email', $request->email)->first()`
- `app/Http/Controllers/Tenant/Api/V1/Auth/LoginController.php:17` — `$user = User::where('email', $request->email)->first()`

**Why this matters:** CentralUser has `is_suspended` boolean column (cast in `app/Models/CentralUser.php:48`). The policy `CentralUserPolicy::suspend()` exists at `app/Policies/CentralUserPolicy.php:117`. The `suspend` and `unsuspend` endpoints work correctly in `UserController.php:152-169` and `UserService.php:106-117`. But after suspension, the user can still log in normally. Tenant users (`User` model) don't even have an `is_suspended` column — there's no suspension mechanism for tenant users at all.

**Recommended Implementation:**
```php
// Central login
$user = CentralUser::where('email', $request->email)
    ->whereNull('deleted_at')
    ->first();

if (! $user || $user->is_suspended || ! Hash::check(...)) {
    return $this->api->error('The provided credentials are incorrect.');
}
```

For tenant users, add `is_suspended` column via migration and same login check.

**Estimated Effort:** 30 minutes  
**Dependencies:** Migration for tenant users' `is_suspended` column.

---

### 1.6 Soft-Deleted Users Can Still Log In

**Severity:** High  
**Business Impact:** "Deleted" users retain API access until their token expires.  
**Technical Impact:** Login queries don't filter out soft-deleted records.

**Affected Files:**
- `app/Http/Controllers/Central/Api/V1/Auth/LoginController.php:17` — `CentralUser::where('email', $request->email)->first()` — returns soft-deleted records
- `app/Http/Controllers/Tenant/Api/V1/Auth/LoginController.php:17` — Same issue for tenant users
- `app/Models/CentralUser.php` — Uses `SoftDeletes` trait
- `app/Models/User.php` — Uses `SoftDeletes` trait

**Why this matters:** Both `CentralUser` and `User` use `SoftDeletes`. The `LoginController` queries don't add `->whereNull('deleted_at')`. A user who was "deleted" (soft) can still authenticate with their token until it expires, and can generate new tokens by logging in.

**Recommended Implementation:**
```php
$user = CentralUser::where('email', $request->email)
    ->whereNull('deleted_at')
    ->first();
```

**Estimated Effort:** 5 minutes  
**Dependencies:** None

---

### 1.7 Missing Authorization on Central API Public Routes

**Severity:** Medium  
**Business Impact:** Forgot password and reset password endpoints lack input validation beyond basic rules.  
**Technical Impact:** Password reset tokens can be requested for any email address regardless of suspension status.

**Affected Files:**
- `app/Http/Controllers/Central/Api/V1/Auth/ForgotPasswordController.php:16` — Uses inline `$request->validate([...])` instead of form request
- `app/Http/Controllers/Central/Api/V1/Auth/ResetPasswordController.php:16` — Same issue
- `app/Http/Controllers/Tenant/Api/V1/Auth/ForgotPasswordController.php:16` — Same
- `app/Http/Controllers/Tenant/Api/V1/Auth/ResetPasswordController.php:16` — Same

**Why this matters:** The `ForgotPasswordController` always returns the same message (`'If the email address you entered is registered with us...'`) regardless of whether the email exists. This is correct security practice (no enumeration). However, `Password::sendResetLink()` will send an email to suspended/deleted users. The reset link grants access to reset any user's password, including suspended users. A suspended user can reset their password and regain access.

**Recommended Implementation:** Add a check in `ForgotPasswordController` for `is_suspended` and `deleted_at` before sending the reset link — or better, let the Password broker handle it and focus on the broader suspension check during login.

**Estimated Effort:** 15 minutes  
**Dependencies:** 1.5 (suspension check)

---

### 1.8 CentralUserPolicy Blocks Self-View

**Severity:** Medium  
**Business Impact:** Users cannot view their own user record via the API.  
**Technical Impact:** `CentralUserPolicy::view()` returns false when the actor and target are the same user.

**Affected Files:**
- `app/Policies/CentralUserPolicy.php:25` — `if ($centralUser->id === $target->id) { return false; }`

**Why this matters:** The `/api/central/v1/users/{user}` endpoint is used to view user profiles. When a user tries to view their own record (which is common — "view my profile" from an admin panel), the policy blocks it. The "my profile" endpoint is `me` at `/api/central/v1/me`, but the direct user endpoint should also allow self-view. Also note the same issue applies to `update()`, `delete()`, `restore()`, `forceDelete()`, `suspend()`, and `unsuspend()` — all of which correctly block self-modification via `$centralUser->id === $target->id`.

For `view()`, self-view should probably be allowed. For `update()`, self-blocking is correct (users shouldn't modify their own roles). For `delete()`, self-blocking is correct.

**Recommended Implementation:**
```php
public function view(CentralUser $centralUser, CentralUser $target): bool
{
    if ($centralUser->id === $target->id) {
        return true; // Allow self-view
    }
    // ... rest of logic
}
```

**Estimated Effort:** 2 minutes  
**Dependencies:** None

---

### 1.9 Cross-Context Token Abuse

**Severity:** Medium  
**Business Impact:** A central token sent to a tenant endpoint may authenticate depending on guard resolution order.  
**Technical Impact:** Sanctum attempts guard resolution in order. If both guards have valid tokens, behavior is undefined.

**Affected Files:**
- `routes/tenant.php` — Applies `api` + `tenancy` middleware to tenant routes
- `config/auth.php:51-63` — Defines `central-api` and `tenant-api` guards
- `bootstrap/app.php` — No guard-forcing middleware

**Why this matters:** Sanctum authenticates against the first guard that succeeds. If a central token is sent to `/api/tenant/v1/dashboard` with `Authorization: Bearer <central-token>`, Sanctum will try `tenant-api` guard first (depending on route middleware), fail, then try `central-api`. If `central-api` succeeds, the request is authenticated as a CentralUser on a tenant route, which should never happen.

**Recommended Implementation:** Add a route middleware that verifies the authenticated user model matches the expected guard. Or ensure tenant routes always fail for central tokens by checking the guard name.

```php
// App\Http\Middleware\EnsureGuardMatches.php
public function handle(Request $request, Closure $next, string $guard): Response
{
    if (Auth::guard($guard)->check() === false) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    return $next($request);
}
```

**Estimated Effort:** 1 hour  
**Dependencies:** None

---

### 1.10 Missing Authorization on Role Update Permission Sync

**Severity:** High  
**Business Impact:** A user with `roles.update` can modify permissions of protected roles if they bypass the name check.  
**Technical Impact:** The `RoleController::update()` checks `RoleService::protectedRoles()` by name but doesn't verify the user has the `permissions.update` permission for the permission sync operation.

**Affected Files:**
- `app/Http/Controllers/Central/Api/V1/RoleController.php:56-60` — Syncs permissions with no additional gate
  ```php
  if ($request->filled('permissions')) {
      $this->roleService->syncRolePermission($role, $request->input('permissions'));
  }
  ```
- `app/Policies/RolePolicy.php:42-49` — Only checks `roles.update`, no separate permission check

**Why this matters:** The `roles.update` permission grants the ability to both edit role metadata AND reassign all permissions. There's no separate `permissions.update` or `roles.manage-permissions` permission. A user with `roles.update` can grant themselves or others any permission in the system.

**Recommended Implementation:** Add a separate permission check for permission sync:
```php
public function update(UpdateRoleRequest $request, Role $role): JsonResponse
{
    // ... existing code ...
    
    if ($request->filled('permissions')) {
        Gate::authorize('updatePermissions', Role::class);
        $this->roleService->syncRolePermission($role, $request->input('permissions'));
    }
}
```

**Estimated Effort:** 30 minutes  
**Dependencies:** Add `updatePermissions` to policies and permissions config

---

## Phase 2 — Data Integrity

### 2.1 Plan Deletion Cascades to Subscriptions

**Severity:** High  
**Business Impact:** Accidentally deleting a plan destroys all subscriptions for that plan, affecting paying customers.  
**Technical Impact:** Migration `2026_06_12_163440_create_subscriptions_table.php:22` defines `$table->foreignId('plan_id')->constrained()->cascadeOnDelete()`.

**Affected Files:**
- `database/migrations/2026_06_12_163440_create_subscriptions_table.php:20-22`
- `app/Http/Controllers/Central/Api/V1/PlanController.php:88-98` — `destroy()` allows deletion without checking for active subscriptions

**Why this matters:** When a Plan is soft-deleted, the `cascadeOnDelete()` does NOT fire (Laravel cascades only on hard deletes). However, `forceDelete()` on a plan WILL cascade-delete all subscriptions. The API currently allows both soft-delete and force-delete without checking if subscriptions exist.

**Recommended Implementation:**
1. In `PlanController::destroy()` and `forceDelete()`, check if any active subscriptions reference this plan
2. If active subscriptions exist, return an error: "Cannot delete a plan with active subscriptions."
3. Add a relationship on Plan: `public function subscriptions(): HasMany`

```php
public function destroy(Plan $plan): JsonResponse
{
    Gate::authorize('delete', $plan);
    
    if ($plan->subscriptions()->exists()) {
        return $this->api->error(
            'Cannot delete a plan with existing subscriptions.',
            409
        );
    }
    
    // ... existing code
}
```

**Estimated Effort:** 30 minutes  
**Dependencies:** Add `subscriptions()` relationship to `Plan` model

---

### 2.2 Feature Deletion Orphans Plan Data

**Severity:** Medium  
**Business Impact:** Deleting a feature that's referenced by plan_features removes the pivot entries, silently removing features from plans.  
**Technical Impact:** Migration `2026_06_12_210555_create_plan_features_table.php` has `cascadeOnDelete()` on both `plan_id` and `feature_id`.

**Affected Files:**
- `database/migrations/2026_06_12_210555_create_plan_features_table.php` — Cascade deletes on both FKs
- `app/Http/Controllers/Central/Api/V1/FeatureController.php:88-98` — `destroy()` allows deleting features without checking plan attachments

**Why this matters:** When a feature is deleted, all `plan_features` entries referencing it are cascade-deleted. Plans silently lose features they were configured with. There's no warning or prevention.

**Recommended Implementation:** Add a check before deleting features:
```php
public function destroy(Feature $feature): JsonResponse
{
    Gate::authorize('delete', $feature);
    
    if ($feature->plans()->exists()) {
        return $this->api->error(
            'Cannot delete a feature that is attached to plans.',
            409
        );
    }
    
    // ... existing code
}
```

**Estimated Effort:** 15 minutes  
**Dependencies:** None (Feature model already has `plans()` relationship)

---

### 2.3 Force-Delete Tenant Orphans Related Records

**Severity:** Medium  
**Business Impact:** Force-deleting a tenant leaves orphaned subscriptions, roles, and permissions.  
**Technical Impact:** `TenantObserver::forceDeleted()` at `app/Observers/TenantObserver.php:33-37` only force-deletes `domains()` and `users()`, missing `subscriptions()`, roles with `tenant_id`.

**Affected Files:**
- `app/Observers/TenantObserver.php:33-37`
  ```php
  public function forceDeleted(Tenant $tenant): void
  {
      $tenant->domains()->onlyTrashed()->forceDelete();
      $tenant->users()->onlyTrashed()->forceDelete();
  }
  ```
- `database/migrations/2026_06_12_163440_create_subscriptions_table.php:17-18` — `$table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete()`

**Why this matters:** The FK cascade on `subscriptions.tenant_id` uses `cascadeOnDelete` which fires on SQL-level delete. When `forceDelete()` is called on a Tenant, the Eloquent event fires the observer AFTER the SQL delete. The cascade should handle subscriptions at the DB level. But what about roles and permissions? The `roles.tenant_id` FK also has `cascadeOnDelete` (migration `2026_06_10_231736_add_scope_and_tenant_id_columns_in_roles_table.php:17`), and `permissions.tenant_id` has NO foreign key at all.

The real problem: `permissions` table has `tenant_id` column but no FK constraint (confirmed in migration `2026_06_10_232317_add_scope_and_tenant_id_columns_in_permissions_table.php`). `model_has_roles` and `model_has_permissions` pivot tables would also have orphaned entries.

**Recommended Implementation:**
1. Add FK constraint to `permissions.tenant_id` referencing `tenants.id`
2. In `TenantObserver::forceDeleted()`, also clean up Spatie pivot tables:
```php
public function forceDeleted(Tenant $tenant): void
{
    DB::table('model_has_roles')
        ->whereIn('model_id', $tenant->users()->withTrashed()->pluck('id'))
        ->where('model_type', User::class)
        ->delete();
    
    DB::table('model_has_permissions')
        ->whereIn('model_id', $tenant->users()->withTrashed()->pluck('id'))
        ->where('model_type', User::class)
        ->delete();
    
    $tenant->domains()->onlyTrashed()->forceDelete();
    $tenant->users()->onlyTrashed()->forceDelete();
}
```

**Estimated Effort:** 1 hour  
**Dependencies:** New migration for permissions FK

---

### 2.4 Tenant Restore Doesn't Restore Children

**Severity:** Medium  
**Business Impact:** Restoring a tenant brings back the tenant record but not its users, domains, subscriptions, or roles.  
**Technical Impact:** `TenantObserver` has all empty lifecycle hooks at `app/Observers/TenantObserver.php:11-31`.

**Affected Files:**
- `app/Observers/TenantObserver.php:11-31` — Empty `created()`, `saving()`, `restored()`, etc.
- `app/Http/Controllers/Central/Api/V1/TenantController.php:111-117` — `restore()` just restores the tenant

**Why this matters:** When a tenant is soft-deleted, its users, domains, subscriptions, and roles remain soft-deleted (assuming proper cascade on soft deletes). But restoring the tenant does not cascade-restore any of these related records. The tenant is restored but has no users, no domains, no subscriptions.

**Recommended Implementation:**
```php
// In TenantObserver.php
public function restored(Tenant $tenant): void
{
    $tenant->users()->onlyTrashed()->restore();
    $tenant->domains()->onlyTrashed()->restore();
    $tenant->subscriptions()->onlyTrashed()->restore();
}
```

**Estimated Effort:** 15 minutes  
**Dependencies:** None

---

### 2.5 Multiple Active Subscriptions Possible Per Tenant

**Severity:** Medium  
**Business Impact:** A tenant can have multiple "current" subscriptions, leading to ambiguous billing and feature state.  
**Technical Impact:** No unique constraint preventing multiple active subscriptions per tenant.

**Affected Files:**
- `database/migrations/2026_06_12_163440_create_subscriptions_table.php` — No unique constraint on `tenant_id`
- `app/Models/Tenant.php:71-78` — `activeSubscription()` uses `latest('id')` to pick one, silently ignoring others
- `app/Services/Central/SubscriptionService.php:109-115` — `create()` adds a new subscription without deactivating the previous one

**Why this matters:** Creating a new subscription for a tenant does not deactivate the existing one. Both can be `active`. The `activeSubscription()` relationship uses `latest('id')` to return one, but both exist in the database. Billing records, invoices, and feature checks will use the latest subscription's plan. If subscriptions are canceled individually, the other remains active.

**Recommended Implementation:**
```php
// In SubscriptionService::create()
public function create(array $data): Subscription
{
    // Deactivate current active subscriptions for this tenant
    Subscription::where('tenant_id', $data['tenant_id'])
        ->whereIn('status', ['active', 'trial'])
        ->update(['status' => SubscriptionStatusEnum::CANCELLED]);
    
    // ... existing creation logic
}
```

Or add a DB constraint:
```php
// Migration
$table->unique(['tenant_id', 'status']);  // Only one active/trial per tenant
// But this is fragile since it locks rows
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

### 2.6 Subscription Status Transitions Unvalidated

**Severity:** High  
**Business Impact:** Subscriptions can transition between any statuses arbitrarily (e.g., `cancelled` → `active` with no payment).  
**Technical Impact:** `SubscriptionService::update()` at `app/Services/Central/SubscriptionService.php:81-94` sets any data without validating transitions.

**Affected Files:**
- `app/Services/Central/SubscriptionService.php:81-94`
  ```php
  public function update(Subscription $subscription, array $data): Subscription
  {
      if (isset($data['billing_cycle'], $data['starts_at'])) {
          // ... recalculate ends_at
      }
      $subscription->update($data);  // Any status change allowed
      return $subscription;
  }
  ```
- `app/Http/Requests/Central/Api/V1/Subscription/UpdateSubscriptionRequest.php` — All fields required including `status`

**Why this matters:** An admin can change a subscription from `cancelled` to `active` without any billing event. A trial can be extended indefinitely by re-setting `starts_at`. The subscription model doesn't track when it was cancelled (no `cancelled_at` timestamp).

**Recommended Implementation:** Add a status transition validator:
```php
private const VALID_TRANSITIONS = [
    'trial' => ['active', 'expired', 'cancelled'],
    'active' => ['expired', 'cancelled', 'suspended'],
    'expired' => ['active'],  // Renewal
    'cancelled' => ['active'], // Re-subscribe
    'suspended' => ['active', 'expired'], // Unsuspend
];

public function update(Subscription $subscription, array $data): Subscription
{
    if (isset($data['status'])) {
        $newStatus = $data['status'];
        $allowed = self::VALID_TRANSITIONS[$subscription->status->value] ?? [];
        
        if (! in_array($newStatus, $allowed)) {
            throw new InvalidTransitionException(
                "Cannot transition from {$subscription->status->value} to {$newStatus}"
            );
        }
    }
    // ... rest of update
}
```

**Estimated Effort:** 1 hour  
**Dependencies:** Create `InvalidTransitionException`

---

## Phase 3 — Performance

### 3.1 N+1 Queries in TenantResource

**Severity:** High  
**Business Impact:** Listing 50 tenants executes 150+ database queries. API response times degrade linearly with tenant count.  
**Technical Impact:** `TenantResource` and `ListTenantResource` fire separate queries for each tenant's users and domains.

**Affected Files:**
- `app/Http/Resources/Central/Api/V1/Tenant/TenantResource.php:19-22` — FOUR queries per row:
  ```php
  'name' => $this->users()->withTrashed()->first()?->name,       // Query 1
  'username' => $this->users()->withTrashed()->first()?->username, // Query 2 (same!)
  'email' => $this->users()->withTrashed()->first()?->email,       // Query 3 (same!)
  'domain' => $this->domains()->withTrashed()->first()?->domain,   // Query 4
  ```
- `app/Http/Resources/Central/Api/V1/Tenant/ListTenantResource.php:19-24` — Same pattern

**Why this matters:** The `$this->users()` call creates a NEW query each time because `users()` is a `HasMany` relationship definition, not a loaded relationship. Calling `->first()` loads the entire collection each time. For 50 tenants: 3×50 + 1×50 = 200 queries minimum, plus the main list query. This is the #1 performance problem in the codebase.

**Recommended Implementation:**
1. Eager-load users and domains on the controller:
```php
// In TenantController::index()
$tenants = $this->tenantService->query(request())
    ->with(['users' => fn($q) => $q->withTrashed(), 'domains' => fn($q) => $q->withTrashed()])
    ->paginate($this->perPage(request()));
```
2. Cache the first user + domain in the resource:
```php
// In TenantResource
public function toArray(Request $request): array
{
    $user = $this->relationLoaded('users') ? $this->users->first() : null;
    $domain = $this->relationLoaded('domains') ? $this->domains->first() : null;
    
    return [
        'id' => $this->id,
        'company_name' => $this->company_name,
        'name' => $user?->name,
        'username' => $user?->username,
        'email' => $user?->email,
        'domain' => $domain?->domain,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

**Estimated Effort:** 1 hour  
**Dependencies:** None

---

### 3.2 N+1 Queries in SubscriptionResource

**Severity:** High  
**Business Impact:** Subscription listings degrade with subscription count.  
**Technical Impact:** `SubscriptionResource` lazy-loads relationships per row.

**Affected Files:**
- `app/Http/Resources/Central/Api/V1/Subscription/SubscriptionResource.php:27-36` — Lazy loads `tenant` and `plan` relationships:
  ```php
  'name' => $this->tenant->name,   // Lazy load
  'email' => $this->tenant->email, // Lazy load
  'name' => $this->plan->name,     // Lazy load
  ```

**Why this matters:** Each subscription resource triggers `$this->tenant` (BelongsTo lazy load) and `$this->plan` (BelongsTo lazy load). For 30 subscriptions: 60 extra queries.

**Recommended Implementation:**
```php
// In SubscriptionController::index()
$subscriptions = $this->subscriptionService->query(request())
    ->with(['tenant', 'plan'])
    ->paginate($this->perPage(request()));
```

Then verify the resource uses `$this->whenLoaded()` or just relies on eager loading:
```php
'name' => $this->whenLoaded('tenant', fn() => $this->tenant->name),
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

### 3.3 Plan::hasFeature() Fires Query Per Call

**Severity:** Medium  
**Business Impact:** Checking 5 features for a plan fires 5 queries.  
**Technical Impact:** `Plan::hasFeature()` at `app/Models/Plan.php:65-79` and `getFeatureValue()` at `app/Models/Plan.php:81-88` both call `$this->features()->where('slug', $slug)->first()`, which queries the DB each time.

**Affected Files:**
- `app/Models/Plan.php:65-79`
  ```php
  public function hasFeature(string $slug): bool
  {
      $feature = $this->features()
          ->where('slug', $slug)
          ->first();  // DB query every time
      // ...
  }
  ```
- `app/Models/Plan.php:81-88` — Same pattern for `getFeatureValue()`

**Why this matters:** In `EnsurePlanFeature` middleware at `app/Http/Middleware/EnsurePlanFeature.php:31`, `$this->subscriptionService->hasFeature($tenant, $feature)` calls `$plan->hasFeature($feature)` which fires a query. If multiple feature checks are needed during a request, each one queries the database.

**Recommended Implementation:** Eager-load features on the plan and check in-memory:
```php
public function hasFeature(string $slug): bool
{
    // Use loaded relationship if available, otherwise query
    if ($this->relationLoaded('features')) {
        $feature = $this->features->firstWhere('slug', $slug);
    } else {
        $feature = $this->features()->where('slug', $slug)->first();
    }
    
    if (! $feature) {
        return false;
    }
    
    return filter_var($feature->pivot->getAttribute('value'), FILTER_VALIDATE_BOOLEAN);
}
```

Then eager-load features when needed:
```php
// In Tenant model's activePlan() call path
$plan = $tenant->activePlan()->with('features');
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

### 3.4 Missing Index on subscriptions.tenant_id

**Severity:** Medium  
**Business Impact:** `activeSubscription()` lookup scans all subscriptions.  
**Technical Impact:** No explicit index on `subscriptions.tenant_id` for the HasOne relationship.

**Affected Files:**
- `database/migrations/2026_06_12_163440_create_subscriptions_table.php` — Only has FK constraint, no index
- `app/Models/Tenant.php:71-78` — `activeSubscription()` queries `WHERE tenant_id = ? AND status IN (?, ?) AND (ends_at >= ? OR ends_at IS NULL)`

**Why this matters:** The subscription check runs on every request with the `subscription` middleware. Without a composite index on `(tenant_id, status, ends_at)`, this query does a full table scan or index scan on the FK. As subscription count grows (hundreds of thousands), this becomes a slow query.

**Recommended Implementation:** New migration:
```php
$table->index(['tenant_id', 'status', 'ends_at'], 'subscriptions_tenant_status_ends_index');
```

**Estimated Effort:** 15 minutes  
**Dependencies:** None

---

### 3.5 Missing Index on users.tenant_id

**Severity:** Low  
**Business Impact:** Tenant user queries may be slow at scale.  
**Technical Impact:** `users.tenant_id` has a FK but no explicit index.

**Affected Files:**
- `database/migrations/2026_06_07_160001_add_tenant_id_to_users_table.php` — FK only, no index

**Why this matters:** The `TenantScope` adds `WHERE users.tenant_id = ?` to all tenant-scoped user queries. With millions of users across thousands of tenants, this index is critical.

**Recommended Implementation:**
```php
$table->index('tenant_id', 'users_tenant_id_index');
```

**Estimated Effort:** 5 minutes  
**Dependencies:** None

---

## Phase 4 — Subscription Architecture

### 4.1 Do You Need a Subscription State Machine?

**Analysis:**  
Your current `SubscriptionService::update()` at `app/Services/Central/SubscriptionService.php:81-94` allows arbitrary transitions. The `UpdateSubscriptionRequest` at `app/Http/Requests/Central/Api/V1/Subscription/UpdateSubscriptionRequest.php` requires all fields including `status`.

Your `SubscriptionStatusEnum` at `app/Enums/Central/SubscriptionStatusEnum.php` defines five states: `trial`, `active`, `expired`, `cancelled`, `suspended`.

**Verdict: YES, you need a state machine.**  
Without status transition validation:
- A trial can be extended indefinitely by an admin updating `starts_at` + keeping `status=trial`
- A cancelled subscription can become `active` without payment
- There's no `cancelled_at` timestamp — you can't track when cancellation happened
- There's no `trial_ends_at` separate from `ends_at` — trial expiration is mixed with subscription expiration

**Recommended Implementation:**

Create a `SubscriptionState` enum/class that enforces transitions:

```
Allowed transitions:
  trial     → active, expired, cancelled
  active    → expired, cancelled, suspended
  expired   → active (renewal)
  cancelled → active (re-subscribe)
  suspended → active (unsuspend), expired
```

Add timestamps:
- `trial_ends_at` — separate from subscription `ends_at`
- `cancelled_at` — when was it cancelled
- `suspended_at` — when was it suspended

**Estimated Effort:** 4 hours  
**Dependencies:** New migration for timestamp columns

---

### 4.2 Do You Need Upgrade/Downgrade Flow?

**Analysis:**  
Your `SubscriptionController` at `app/Http/Controllers/Central/Api/V1/SubscriptionController.php` has `store()`, `update()`, `destroy()`, `restore()`, `forceDelete()`. There is no dedicated "change plan" endpoint. Changing a plan currently requires updating the subscription's `plan_id` via `update()`.

Your `Plan` model at `app/Models/Plan.php` has features attached via `plan_features` pivot. Changing a plan changes which features are available.

**Verdict: YES, you need a dedicated upgrade/downgrade flow.**  
Why:
1. Plan change requires validation — can the tenant downgrade mid-cycle?
2. Feature availability changes — data created from unavailable features needs handling
3. Price proration — requires billing integration but the architecture should support it
4. Audit trail — plan changes should be tracked

**Recommended Implementation:**
```php
// SubscriptionController
public function changePlan(Request $request, Subscription $subscription): JsonResponse
{
    Gate::authorize('update', $subscription);
    
    $validated = $request->validate([
        'plan_id' => 'required|exists:plans,id',
    ]);
    
    $subscription = $this->subscriptionService->changePlan(
        $subscription, 
        (int) $validated['plan_id']
    );
    
    return $this->api->success(
        'Plan changed successfully',
        new SubscriptionResource($subscription),
    );
}
```

In `SubscriptionService::changePlan()`, validate the transition, store the old plan ID for history, update the subscription.

**Estimated Effort:** 3 hours  
**Dependencies:** 4.1 (state machine) — without status validation, upgrades/downgrades bypass state rules

---

### 4.3 Do You Need Cancellation Flow?

**Analysis:**  
Currently, cancelling a subscription is done by setting `status=cancelled` via the standard `update()` endpoint. No dedicated cancellation endpoint exists.

Your `SubscriptionStatusEnum::CANCELLED` exists but no logic around it — no `cancelled_at` timestamp, no handling of data retention period after cancellation.

**Verdict: YES, you need a dedicated cancellation flow.**  
Why:
1. Cancellation should set `cancelled_at` and optionally change `ends_at` to "end of billing period"
2. Grace period support — data retention for X days after cancellation
3. Cancellation reason tracking for churn analysis
4. Prevent immediate feature loss — tenant should retain access until end of billing period, not instantly lose it

**Recommended Implementation:**
```php
// SubscriptionController
public function cancel(Subscription $subscription): JsonResponse
{
    Gate::authorize('update', $subscription);
    
    $this->subscriptionService->cancel($subscription);
    
    return $this->api->success('Subscription cancelled successfully');
}

// In SubscriptionService
public function cancel(Subscription $subscription): Subscription
{
    // Don't change status immediately — set to cancelled at period end
    // Or cancel immediately based on business rules
    $subscription->update([
        'status' => SubscriptionStatusEnum::CANCELLED,
        'cancelled_at' => now(),
        // Keep ends_at as end of billing period for grace period
    ]);
    
    return $subscription;
}
```

**Estimated Effort:** 2 hours  
**Dependencies:** 4.1 (state machine), migration for `cancelled_at`

---

### 4.4 Do You Need Renewal Flow?

**Analysis:**  
No subscription auto-renewal mechanism exists. The `ends_at` field is set once during creation and never updated. No cron job checks for nearing expiration.

**Verdict: YES, you need a renewal flow.**  
Why:
1. Without auto-renewal, every subscription expires and tenants lose access
2. No mechanism to extend a subscription's `ends_at` based on payment
3. The `SubscriptionService::create()` calculates `ends_at` from `billing_cycle` but never updates it again

**Recommended Implementation:**
1. `SubscriptionService::renew()` — extends `ends_at` by one billing cycle
2. Artisan command `subscriptions:auto-expire` — marks expired subscriptions
3. Artisan command `subscriptions:renew-due` — processes renewals for auto-pay tenants

```php
// Artisan command (scheduled daily)
$schedule->command('subscriptions:expire-check')->daily();

// SubscriptionService
public function renew(Subscription $subscription): Subscription
{
    $newEndsAt = $subscription->ends_at
        ? $subscription->ends_at->add($this->getBillingInterval($subscription->billing_cycle))
        : now()->add($this->getBillingInterval($subscription->billing_cycle));
    
    $subscription->update([
        'ends_at' => $newEndsAt,
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);
    
    return $subscription;
}
```

**Estimated Effort:** 3 hours  
**Dependencies:** 4.1 (state machine), billing integration for paid renewals

---

### 4.5 Do You Need Trial Restrictions?

**Analysis:**  
Your `Plan` model has `trial_days` field at `database/migrations/2026_06_12_103627_create_plans_table.php` and `app/Models/Plan.php:35`. The `Subscription` migration sets default status to `trial` at `database/migrations/2026_06_12_163440_create_subscriptions_table.php:29`.

However, there's no logic enforcing "one trial per tenant" or "trial cannot exceed plan's trial_days."

**Verdict: YES, you need trial restrictions.**  
Why:
1. A tenant can get unlimited free trials by having admin create new subscriptions with `status=trial`
2. `trial_days` from Plan is read in resources but never enforced
3. No check prevents a tenant who previously had a paid subscription from starting a new trial

**Recommended Implementation:**
```php
// In SubscriptionService::create()
public function create(array $data): Subscription
{
    $tenant = Tenant::findOrFail($data['tenant_id']);
    
    if ($data['status'] === SubscriptionStatusEnum::TRIAL->value) {
        // Check if tenant has ever had a paid subscription
        $hasPriorSubscription = $tenant->subscriptions()
            ->where('status', '!=', SubscriptionStatusEnum::TRIAL)
            ->exists();
        
        if ($hasPriorSubscription) {
            throw new \RuntimeException('Tenant is not eligible for a trial.');
        }
        
        // Check if tenant already had a trial
        $hasPriorTrial = $tenant->subscriptions()
            ->where('status', SubscriptionStatusEnum::TRIAL)
            ->exists();
        
        if ($hasPriorTrial) {
            throw new \RuntimeException('Tenant has already used their trial.');
        }
    }
    
    // ... existing code
}
```

**Estimated Effort:** 1 hour  
**Dependencies:** 4.1 (state machine)

---

### 4.6 Do You Need Expiration Scheduler?

**Analysis:**  
Your `EnsureTenantSubscription` middleware at `app/Http/Middleware/EnsureTenantSubscription.php` checks expiration at runtime via `SubscriptionService::validateSubscription()`. The `Subscription::isExpired()` method at `app/Models/Subscription.php:85-88` checks `$this->ends_at->isPast()`.

**Verdict: YES, you need an expiration scheduler.**  
But NOT for access control (the middleware already handles runtime checking correctly). You need it for:
1. Automatically updating status from `active`/`trial` to `expired` so DB is accurate
2. Triggering post-expiration events (notifications, cleanup)
3. Generating analytics on churned tenants

**Recommended Implementation:**
```php
// app/Console/Commands/ExpireSubscriptions.php
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    
    public function handle(): void
    {
        Subscription::whereIn('status', ['active', 'trial'])
            ->where('ends_at', '<', now())
            ->whereNotNull('ends_at')
            ->update(['status' => SubscriptionStatusEnum::EXPIRED]);
        
        $this->info('Expired subscriptions updated.');
    }
}

// In bootstrap/app.php or routes/console.php
Schedule::command('subscriptions:expire')->daily();
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None (standalone improvement)

---

## Phase 5 — SaaS Enforcement Layer

### 5.1 Current Enforcement Analysis

**What exists:**
1. `app/Http/Middleware/EnsurePlanFeature.php` — Checks if tenant's plan has a feature (boolean toggle)
2. `app/Models/Plan.php:65-79` — `hasFeature()` checks boolean pivot value
3. `app/Models/Plan.php:81-88` — `getFeatureValue()` returns raw pivot value
4. `app/Services/Central/SubscriptionService.php:163-183` — `hasFeature()` and `featureValue()` delegate to Plan
5. `app/Services/Central/SubscriptionLimitService.php` — `checkLimit()` and `getLimit()` for numeric limits
6. `database/migrations/2026_06_12_193559_create_features_table.php` — Features table with `type` column (boolean, integer, decimal, string)

**What's missing:**
1. **No usage tracking** — There's no system that counts current usage against limits. `SubscriptionLimitService::checkLimit()` accepts `$currentUsage` as a parameter, expecting the caller to provide it. No code provides this count. 
2. **No enforcement in controllers** — Feature gating only exists as middleware. There's no code enforcing `users_limit` when creating users, `contacts_limit` when creating contacts, etc.
3. **No tenant-facing usage endpoint** — Tenants cannot see their current usage vs limits.

**Conclusion:** Feature toggle enforcement (middleware) exists. Usage limit enforcement (architecture) is designed but NOT implemented. `SubscriptionLimitService` is unused by any controller.

---

### 5.2 Feature Middleware Usage

**Current State:** `EnsurePlanFeature` at `app/Http/Middleware/EnsurePlanFeature.php` is registered as `feature` alias and works correctly. The `routes/tenant/v1.php` docblock shows example usage but no actual routes use it.

**Recommended Implementation:** Apply `feature` middleware to existing and future tenant routes:

```php
// Routes for features that are plan-gated (commented example in routes/tenant/v1.php)
Route::middleware(['auth:tenant-api', 'subscription', 'feature:users'])->group(function () {
    // Tenant user management routes
});
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

### 5.3 Usage Limit Enforcement Design

**Current Architecture:** `SubscriptionLimitService::checkLimit(Tenant $tenant, string $featureSlug, int $currentUsage)` is the right abstraction. The caller is responsible for providing the current usage count. This is correct design for single-database mode — you count from the shared database.

**Missing Integration Points:**

**5.3.1 — Enforce `users_limit` when creating Tenant Users**

Affected: `app/Http/Controllers/Tenant/Api/V1/Auth/LoginController.php` (no tenant user registration exists)
Also: Tenant provisioning at `app/Services/Central/TenantProvisioningService.php:26` creates the first tenant user.

The first user is created during provisioning, so the limit check should happen in `TenantService::create()`:
```php
// In TenantService::create()
$limitCheck = $this->subscriptionLimitService->checkLimit(
    $tenant, 
    'users_limit', 
    0 // First user
);
// During provisioning, the first user is created, so no existing count
```

For subsequent tenant user creation (when UserController exists for tenant domain):
```php
$limitCheck = $this->subscriptionLimitService->checkLimit(
    tenant(),
    'users_limit',
    User::count() // Current users
);
```

**5.3.2 — Enforce `contacts_limit`, `deals_limit`, etc.**

When corresponding tenant controllers exist, wrap store/update with:
```php
$limitCheck = $this->subscriptionLimitService->checkLimit(
    tenant(),
    'contacts_limit',
    Contact::count()
);
```

**Recommended Implementation Order:**
1. Integrate `SubscriptionLimitService` into `SubscriptionService` (or keep it independent)
2. Add limit checks to `TenantProvisioningService::provision()`
3. Add limit checks to tenant `UserController::store()` when created
4. Document the pattern for future controller creation

**Estimated Effort:** 2 hours for integration pattern  
**Dependencies:** None (architecture is already designed)

---

### 5.4 Feature Value Type Handling Fix

**Current Bug:** `Plan::hasFeature()` uses `filter_var($value, FILTER_VALIDATE_BOOLEAN)` at `app/Models/Plan.php:75-78`. This returns `false` for:
- Integer values like `"10"` (for limits)
- String values like `"monthly"` (for config)
- Empty string `""`

So `hasFeature('users_limit')` returns `false` even though the feature exists with value "10".

**Why this matters:** The method is only suitable for boolean toggle features (`type=boolean` in the `features` table). For other types, `hasFeature()` is misleading. The middleware `EnsurePlanFeature` uses `hasFeature()` to check feature availability — this breaks for non-boolean features.

**Recommended Fix:** Make `hasFeature()` type-aware:
```php
public function hasFeature(string $slug): bool
{
    $feature = $this->features()->where('slug', $slug)->first();
    
    if (! $feature) {
        return false;
    }
    
    $value = $feature->pivot->getAttribute('value');
    
    return match ($feature->type) {
        'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        'integer', 'decimal' => is_numeric($value) && (int) $value > 0,
        'string' => ! empty($value),
        default => ! is_null($value),
    };
}
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

## Phase 6 — Tenant Management

### 6.1 Do You Need Tenant Settings?

**Analysis:**  
The `Tenant` model at `app/Models/Tenant.php` has:
- `id` (UUID string)
- `company_name`
- `deleted_at`
- `data` (JSON column — from `database/migrations/2019_09_15_000010_create_tenants_table.php:36`)
- `created_at` / `updated_at`
- Relationships: `users()`, `domains()`, `subscriptions()`

The `data` JSON column exists but is never used. No tenant settings are stored.

**Verdict: YES, you need tenant settings.**  
But your architecture already supports it via the `data` JSON column. No migration needed.

**Recommended Implementation:** Create a `TenantSetting` service that reads/writes to `Tenant.data`:

```php
// Option A: Reuse the `data` JSON column
// Model accessor
public function getSetting(string $key, mixed $default = null): mixed
{
    return data_get($this->data, $key, $default);
}

public function setSetting(string $key, mixed $value): void
{
    $data = $this->data ?? [];
    data_set($data, $key, $value);
    $this->data = $data;
    $this->save();
}
```

Or create a separate `tenant_settings` table if you need indexed queries:
```php
Schema::create('tenant_settings', function (Blueprint $table) {
    $table->id();
    $table->string('tenant_id');
    $table->string('key');
    $table->text('value')->nullable();
    $table->timestamps();
    
    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    $table->unique(['tenant_id', 'key']);
});
```

**Recommended settings:**
- `timezone` — default 'UTC'
- `locale` — default 'en'
- `date_format` — default 'Y-m-d'
- `notification_email` — default tenant admin email
- `theme` — branding overrides

**Estimated Effort:** 2 hours (JSON column) or 4 hours (separate table)  
**Dependencies:** None

---

### 6.2 Do You Need Branding/Localization Settings?

**Analysis:**  
No branding or localization infrastructure exists. The `Ability` config at `config/abilities.php` lists `tenant:settings:read` and `tenant:settings:update` abilities, suggesting settings were planned but not implemented.

**Verdict: YES, but low priority.**  
Branding and localization are important for white-labeling but don't affect billing, subscriptions, or core functionality.

**Recommended Implementation:** Extend the tenant settings system (6.1) with:
- `branding` — JSON object for logo URL, primary color, accent color
- `localization` — locale, timezone, date/number formats

**Estimated Effort:** 1 hour (if settings infrastructure exists)  
**Dependencies:** 6.1

---

### 6.3 Do You Need Notification Settings?

**Analysis:**  
Your `User` model at `app/Models/User.php:37-39` has `routeNotificationForMail()` which returns `[$this->email => $this->name]`. Central users use `Notifiable` trait at `CentralUser.php`. Password reset notification is implemented at `CentralUser.php:36`.

No per-tenant notification configuration exists.

**Verdict: YES, but build on tenant settings.**  
Notification preferences should be part of tenant settings:
- `notifications.billing` — who receives billing notifications
- `notifications.subscription_expiry_days` — days before expiry to warn
- `notifications.system_updates` — opt-in for product updates

**Estimated Effort:** 1 hour  
**Dependencies:** 6.1

---

## Phase 7 — Billing Readiness

### 7.1 Is Your Architecture Ready for Payment Processing?

**Analysis:**  
Your current subscription architecture:

| Component | Ready? | Issues |
|-----------|--------|--------|
| `Plan` model | ⚠️ Partial | `monthly_price` and `yearly_price` stored as decimal(10,2) — uses decimal type in cast at `Plan.php:39-40`. Good foundation. |
| `Subscription` model | ⚠️ Partial | Has `billing_cycle` at `Subscription.php:39`. No payment processor fields. |
| `SubscriptionStatusEnum` | ⚠️ Partial | Has `active`, `expired`, `cancelled`, `suspended`, `trial`. Missing `past_due`, `incomplete`, `incomplete_expired`. |
| Price storage | ❌ Missing | Prices are stored on `Plan` model, not on `Subscription`. If a plan's price changes, existing subscriptions see the new price. |
| Payment method | ❌ Missing | No `payment_method` or `payment_provider_id` column on subscriptions. |
| Invoice model | ❌ Missing | No invoice records. |
| Webhook handling | ❌ Missing | No webhook controller or processing. |
| Receipts | ❌ Missing | No receipt/invoice storage. |

### 7.2 Required Changes for Stripe/LemonSqueezy/Paddle

**Database Migrations Needed:**

```php
// Subscriptions table additions
Schema::table('subscriptions', function (Blueprint $table) {
    $table->string('payment_provider')->nullable()->after('status');
    $table->string('payment_provider_id')->nullable()->after('payment_provider');
    $table->string('payment_provider_status')->nullable()->after('payment_provider_id');
    $table->decimal('unit_price', 10, 2)->nullable()->after('plan_id'); // Price snapshot at time of subscription
    $table->timestamp('trial_ends_at')->nullable()->after('ends_at');
    $table->timestamp('cancelled_at')->nullable()->after('trial_ends_at');
});

// New table: subscription_invoices
Schema::create('subscription_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
    $table->string('payment_provider_id');
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('status');
    $table->string('invoice_url')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->unique(['subscription_id', 'payment_provider_id']);
});
```

**Price Storage Strategy:** Store the `unit_price` on the subscription, not just the plan reference. This ensures:
- Historical accuracy — old invoices show the price at time of purchase
- Plan price changes don't affect existing subscriptions
- Upgrade/downgrade creates a new subscription price record

### 7.3 Recommended Billing Architecture

**Choose Stripe** (most mature Laravel ecosystem support):

1. **Integration Layer:** Create `app/Services/Billing/BillingService.php` as an abstract interface
2. **Provider Implementation:** Create `app/Services/Billing/Providers/StripeProvider.php`
3. **Webhook Controller:** `app/Http/Controllers/Api/V1/WebhookController.php` (no auth — validated by Stripe signature)
4. **Subscription Sync:** Keep local subscription status in sync via webhooks:
   - `customer.subscription.updated` → update `status`, `ends_at`
   - `customer.subscription.deleted` → set `status = cancelled`
   - `invoice.paid` → create invoice record
   - `invoice.payment_failed` → set `status = past_due`

```php
// StripeProvider::createCheckoutSession(Plan $plan, Tenant $tenant): string
// StripeProvider::handleWebhook(Request $request): void
// StripeProvider::cancelSubscription(Subscription $subscription): void
// StripeProvider::changePlan(Subscription $subscription, Plan $newPlan): void
```

**Estimated Effort:** 5-10 days for full Stripe integration  
**Dependencies:** 4.1 (state machine), 4.4 (renewal), Phase 4 subscription flow endpoints

---

## Phase 8 — Audit & Monitoring

### 8.1 Do You Need Audit Logs?

**Analysis:**  
No audit trail exists. Observers at `app/Observers/` are all empty — `CentralUserObserver.php`, `TenantObserver.php`, `RoleObserver.php`, `PlanObserver.php`, `FeatureObserver.php`, `SubscriptionObserver.php` have empty hooks.

There's no tracking of:
- Who created/modified a subscription
- When a plan's price was changed
- Who assigned a role to a user
- Which subscription statuses were changed and by whom

**Verdict: YES, you need audit logs.**  
For a commercial SaaS handling payments and subscriptions, audit trails are essential for:
1. PCI compliance if handling any payment data
2. SOC 2 / ISO 27001 readiness
3. Dispute resolution — "who changed this subscription?"
4. Operational debugging — "what happened at 3 AM?"

**Recommended Implementation:** Use Spatie's `laravel-activitylog` package:
```bash
composer require spatie/laravel-activitylog
```

Integrate with existing observers:
```php
// Example: SubscriptionObserver
public function updated(Subscription $subscription): void
{
    if ($subscription->wasChanged('status')) {
        activity()
            ->performedOn($subscription)
            ->causedBy(request()->user())
            ->withProperties([
                'old' => $subscription->getOriginal('status'),
                'new' => $subscription->status,
            ])
            ->log('subscription_status_changed');
    }
}
```

**Estimated Effort:** 4 hours for integration + observer updates  
**Dependencies:** Install `spatie/laravel-activitylog`

---

### 8.2 Do You Need Activity Logs?

**Analysis:**  
For tenant users (not central admins), there's no tracking of user actions (who created a contact, who deleted a deal, etc.). This is different from audit logs (admin actions) — activity logs track user actions within their tenant context.

**Verdict: YES, but build on the same infrastructure as audit logs.**  
Use the same `spatie/laravel-activitylog` package. Log tenant user actions with the tenant context:

```php
activity()
    ->inLog('tenant')
    ->performedOn($contact)
    ->causedBy(auth()->user())
    ->withProperty('tenant_id', tenant()->id)
    ->log('created_contact');
```

**Estimated Effort:** Included in 8.1  
**Dependencies:** 8.1

---

### 8.3 Do You Need Event Logs?

**Analysis:**  
Your app currently uses `Telescope` (`laravel/telescope` in composer.json at line 26, configured at `config/telescope.php`). Telescope already captures:
- Request/response data
- Query logs
- Exception details
- Mail, notifications, jobs, logs, cache, events, schedules

**Verdict: Already handled by Telescope.**  
No separate event logging infrastructure needed. Telescope gives you comprehensive debugging in development and filtered monitoring in production.

**Recommended Action:** Configure Telescope for production:
```php
// config/telescope.php
Telescope::filter(function (IncomingEntry $entry) {
    return $entry->isReportableException() ||
           $entry->isFailedJob() ||
           $entry->isScheduledTask() ||
           $entry->isSlowQuery() ||
           $entry->hasMonitoredTag();
});
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None (already installed)

---

### 8.4 Do You Need Webhooks?

**Analysis:**  
No webhook system or outgoing HTTP notifications exist. External services cannot react to subscription events.

**Verdict: YES, for billing integration.**  
But not as a standalone system. Webhooks should be integrated with the billing provider (Stripe/LemonSqueezy/Paddle sends webhooks to your app). Outgoing webhooks (your app notifying external services) are a lower priority.

**Recommended Implementation:** Billing provider webhooks first:
```php
// routes/api.php
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth:central-api', 'auth:tenant-api']);

// App\Http\Controllers\Api\V1\StripeWebhookController
public function handle(Request $request): Response
{
    $event = Stripe\Webhook::constructEvent(
        $request->getContent(),
        $request->header('Stripe-Signature'),
        config('services.stripe.webhook_secret')
    );
    
    match ($event->type) {
        'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
        'invoice.paid' => $this->handleInvoicePaid($event),
        default => null,
    };
    
    return response('OK', 200);
}
```

**Estimated Effort:** 1-2 days (part of billing integration)  
**Dependencies:** 7.3 (billing provider)

---

### 8.5 Do You Need Error Monitoring?

**Analysis:**  
No Sentry, Flare, Bugsnag, or similar error monitoring is installed. `composer.json` at line 18-31 shows no error monitoring packages. Telescope captures errors locally but doesn't alert.

**Verdict: YES, this is essential for production.**  
Without error monitoring, you won't know about:
- Failed payment webhooks
- Subscription validation errors
- 500 errors from API
- Queue job failures

**Recommended Implementation:** Install Flare (Laravel's native solution) or Sentry:
```bash
composer require flare-laravel
# or
composer require sentry/sentry-laravel
```

**Estimated Effort:** 30 minutes  
**Dependencies:** None

---

## Final Deliverables

### Production Readiness Score

| Area | Score | Rationale |
|------|-------|-----------|
| **Authentication** | 4/10 | Token abilities are wildcards, no throttling, suspended/deleted users can log in, email enumeration possible. Core auth works but has critical flaws. |
| **Authorization** | 7/10 | Policies exist for all CRUD, well-structured. Guard isolation by Spatie's guard_name is correct. But no permission granularity for role-permission sync. |
| **Multi-Tenancy** | 7/10 | `TenantScope` works, guard isolation is correct. But cross-tenant email collisions prevented by unique constraint, and soft-delete restore is incomplete. |
| **Plans** | 6/10 | CRUD correct. Plan deletion cascade to subscriptions is dangerous. Price changes affect existing subscribers. |
| **Features** | 7/10 | Flexible type system. `hasFeature()` broken for integer types. Missing in-request caching. |
| **Subscriptions** | 4/10 | CRUD exists but no state machine, no cancellation flow, no renewal, no expiration scheduler, no billing. Status transitions unvalidated. |
| **Security** | 3/10 | Double-hash bug, wildcard tokens, no rate limiting, email enumeration, no suspension check, soft-delete login bypass. |
| **Performance** | 5/10 | Critical N+1 in TenantResource/SubscriptionResource. Missing composite indexes on subscription queries. |
| **Scalability** | 5/10 | Single-database mode will bottleneck. Missing indexes. N+1 destroys pagination performance. |
| **Maintainability** | 7/10 | Good service layer, consistent controller patterns, form requests, resources. Observers are all empty. Dead code (`successWithAdditional`). |

**Overall: 5.5/10** — Solid foundation but critical security and subscription gaps prevent production deployment.

---

### Top 20 Issues Ranked

| Rank | Issue | Severity | Risk | Effort | File |
|------|-------|----------|------|--------|------|
| 1 | Token abilities are `['central:*']` / `['tenant:*']` wildcards | Critical | 9/10 | 2h | `LoginResource.php:22` |
| 2 | Double-hash password in `changePassword()` | Critical | 9/10 | 30m | `UserService.php:102` |
| 3 | No rate limiting on auth endpoints | High | 8/10 | 10m | `routes/central/v1.php:77` |
| 4 | Email enumeration via `exists` rule | High | 7/10 | 5m | `LoginRequest.php:12` |
| 5 | Suspended users can log in | High | 8/10 | 15m | `LoginController.php:17` |
| 6 | Soft-deleted users can log in | High | 8/10 | 5m | `LoginController.php:17` |
| 7 | N+1 in TenantResource — 4 queries per row | High | 7/10 | 1h | `TenantResource.php:19-22` |
| 8 | Plan deletion cascades to subscriptions | High | 8/10 | 30m | `PlanController.php:88` |
| 9 | No subscription state machine | High | 8/10 | 4h | `SubscriptionService.php:81` |
| 10 | N+1 in SubscriptionResource | High | 6/10 | 30m | `SubscriptionResource.php:27-36` |
| 11 | `hasFeature()` broken for integer features | High | 7/10 | 30m | `Plan.php:75-78` |
| 12 | Tenant restore orphans children | Medium | 5/10 | 15m | `TenantObserver.php:11-31` |
| 13 | Force-delete tenant orphans permissions | Medium | 5/10 | 1h | `TenantObserver.php:33-37` |
| 14 | Multiple active subscriptions per tenant | Medium | 6/10 | 30m | `SubscriptionService.php:66` |
| 15 | Missing composite index on subscriptions | Medium | 5/10 | 15m | Migration |
| 16 | CentralUserPolicy blocks self-view | Medium | 3/10 | 2m | `CentralUserPolicy.php:25` |
| 17 | Subscription resource shows wrong tenant name | Low | 4/10 | 5m | `SubscriptionResource.php:27` |
| 18 | Central + tenant token cross-use possible | Medium | 5/10 | 1h | Routes |
| 19 | Feature deletion orphans plan attachments | Medium | 4/10 | 15m | `FeatureController.php:88` |
| 20 | `Subscription::forceDelete()` permissions check fix | Low | 2/10 | 5m | `SubscriptionPolicy.php:69` |

---

### Top 20 Improvements Ranked

| Rank | Improvement | Business Value | Technical Value | Cost |
|------|-------------|---------------|-----------------|------|
| 1 | Fix token abilities (granular scoping) | Critical | Critical | 2h |
| 2 | Fix double-hash password bug | Critical | High | 30m |
| 3 | Add rate limiting to auth endpoints | High | Medium | 10m |
| 4 | Fix email enumeration vulnerability | High | Low | 5m |
| 5 | Add suspension + deletion checks to login | High | Medium | 15m |
| 6 | Fix N+1 in TenantResource | Medium | High | 1h |
| 7 | Prevent plan deletion with active subscriptions | High | Medium | 30m |
| 8 | Implement subscription state machine | High | High | 4h |
| 9 | Fix N+1 in SubscriptionResource | Medium | High | 30m |
| 10 | Fix `hasFeature()` for non-boolean types | Medium | High | 30m |
| 11 | Implement subscription cancellation flow | High | Medium | 2h |
| 12 | Implement subscription renewal flow | High | Medium | 3h |
| 13 | Add expiration scheduler command | Medium | Medium | 30m |
| 14 | Implement tenant settings via `data` JSON column | Medium | Low | 2h |
| 15 | Add composite index on subscriptions table | Low | High | 15m |
| 16 | Add audit logging via spatie/laravel-activitylog | Medium | High | 4h |
| 17 | Install error monitoring (Flare/Sentry) | High | Medium | 30m |
| 18 | Implement subscription plan change (upgrade/downgrade) | High | High | 3h |
| 19 | Enforce one-trial-per-tenant rule | Medium | Medium | 1h |
| 20 | Add tenant restore cascade | Low | Medium | 15m |

---

### Recommended 60-Day Implementation Roadmap

#### Week 1 — Critical Security (Days 1-5)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 1 | Fix token abilities (1.1), Fix double-hash bug (1.2) | `php artisan test --compact` |
| 2 | Add rate limiting (1.3), Fix email enumeration (1.4) | Manual brute-force test |
| 3 | Add suspension + deletion checks (1.5, 1.6) | Login as suspended user → 401 |
| 4 | Fix self-view policy (1.8), Fix role permission auth gap (1.10) | `php artisan test --compact` |
| 5 | Add guard-forcing middleware (1.9), Fix `hasFeature()` (5.4) | Integration tests |

**Deliverable:** Secure auth flow, no enumeration, no suspended/deleted login.

#### Week 2 — Data Integrity (Days 6-10)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 6 | Prevent plan/feature deletion with active subs (2.1, 2.2) | `DELETE /api/central/v1/plans/{id}` → 409 |
| 7 | Fix force-delete cascade (2.3), Add restore cascade (2.4) | `php artisan tinker` to test cascade |
| 8 | Add subscription unique constraint (2.5), Status transition validation (2.6) | Create duplicate subscription → rejected |
| 9 | Add composite index (3.4, 3.5), User tenant_id index (3.5) | `EXPLAIN SELECT` verification |
| 10 | Fix N+1 in TenantResource (3.1), SubscriptionResource (3.2) | Clockwork/Debugbar query count |

**Deliverable:** Consistent data, proper cascades, proper indexes.

#### Week 3 — Subscription Architecture (Days 11-15)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 11 | Implement subscription state machine (4.1) | Test all 12 transitions |
| 12 | Add cancellation flow (4.3), `cancelled_at` migration | `POST /subscriptions/{id}/cancel` |
| 13 | Add expiration scheduler command (4.6) | `php artisan subscriptions:expire` |
| 14 | Add trial restrictions (4.5), One-trial-per-tenant | Second trial attempt → error |
| 15 | Add plan change (upgrade/downgrade) (4.2) | `POST /subscriptions/{id}/change-plan` |

**Deliverable:** Complete subscription lifecycle management.

#### Week 4 — Renewal & Billing Prep (Days 16-20)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 16 | Implement renewal flow (4.4) | `POST /subscriptions/{id}/renew` |
| 17 | Price snapshot strategy — add `unit_price` to subscriptions | Create subscription → verify frozen price |
| 18 | Add billing provider abstraction (7.3) | Interface + config |
| 19 | Add Stripe integration — checkout session | End-to-end payment flow |
| 20 | Add Stripe webhook handling | Webhook → subscription status sync |

**Deliverable:** Stripe integration, subscriptions sync with payment provider.

#### Week 5 — Monitoring & Enforcement (Days 21-25)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 21 | Install error monitoring (8.5) | Trigger error → see in Sentry/Flare |
| 22 | Install and configure audit logging (8.1) | Check `activity_log` table |
| 23 | Configure Telescope for production (8.3) | Telescope dashboard in production |
| 24 | Integrate usage limit enforcement in controllers (5.3) | Exceed user limit → 403 |
| 25 | Apply feature middleware to routes (5.2) | Access gated route without feature → 403 |

**Deliverable:** Production monitoring, usage enforcement.

#### Week 6 — Tenant Management & Polish (Days 26-30)

| Day | Tasks | Verification |
|-----|-------|-------------|
| 26 | Implement tenant settings via `data` column (6.1) | `GET /api/central/v1/tenants/{id}/settings` |
| 27 | Add notification preferences (6.3) | Update settings → verify |
| 28 | Write comprehensive tests | `php artisan test --compact` — 80%+ coverage |
| 29 | PHPStan level 9 compliance | `vendor/bin/phpstan analyse` |
| 30 | Performance load testing, final review | k6/ab load test |

**Deliverable:** Production-ready SaaS platform.

---

**End of Roadmap**
