<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\PortalUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    FeatureDefinition::create(['key' => 'portal.enabled', 'name' => 'Portal Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);

    $domain = 'portal-test-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);

    tenancy()->initialize($this->tenant);

    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);

    $this->portalUser = PortalUser::factory()->create([
        'password' => Hash::make('password123'),
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('logs in portal user with valid credentials', function () {
    $response = $this->postJson('/api/tenant/v1/portal/auth/login', [
        'email' => $this->portalUser->email,
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.type', 'Bearer');
    expect($response->json('data.token'))->not->toBeNull();
});

it('rejects login with invalid password', function () {
    $response = $this->postJson('/api/tenant/v1/portal/auth/login', [
        'email' => $this->portalUser->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(403);
});

it('rejects login for inactive portal user', function () {
    $this->portalUser->update(['is_active' => false]);

    $response = $this->postJson('/api/tenant/v1/portal/auth/login', [
        'email' => $this->portalUser->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
});

it('rejects login for deleted portal user', function () {
    $this->portalUser->delete();

    $response = $this->postJson('/api/tenant/v1/portal/auth/login', [
        'email' => $this->portalUser->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
});

it('logs out portal user and revokes token', function () {
    $token = $this->portalUser->createToken('portal-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/tenant/v1/portal/auth/logout');

    $response->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $this->portalUser->id,
        'tokenable_type' => PortalUser::class,
    ]);
});

it('shows portal user profile', function () {
    $token = $this->portalUser->createToken('portal-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/tenant/v1/portal/auth/me');

    $response->assertOk();
    $response->assertJsonPath('data.email', $this->portalUser->email);
    $response->assertJsonPath('data.name', $this->portalUser->name);
});

it('changes portal user password', function () {
    $token = $this->portalUser->createToken('portal-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/tenant/v1/portal/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk();

    $this->portalUser->refresh();
    expect(Hash::check('newpassword123', $this->portalUser->password))->toBeTrue();
});

it('rejects change password with wrong current password', function () {
    $token = $this->portalUser->createToken('portal-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/tenant/v1/portal/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertStatus(403);
});

it('requires authentication for portal resources', function () {
    $response = $this->getJson('/api/tenant/v1/portal/documents');
    $response->assertStatus(401);

    $response = $this->getJson('/api/tenant/v1/portal/conversations');
    $response->assertStatus(401);

    $response = $this->getJson('/api/tenant/v1/portal/tasks');
    $response->assertStatus(401);

    $response = $this->getJson('/api/tenant/v1/portal/calendar-events');
    $response->assertStatus(401);
});
