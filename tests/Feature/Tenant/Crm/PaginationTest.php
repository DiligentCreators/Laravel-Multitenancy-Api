<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Activity;
use App\Models\Crm\Address;
use App\Models\Crm\Comment;
use App\Models\Crm\Note;
use App\Models\Crm\Organization;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function pagTenant(): Tenant
{
    $domain = 'pag-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function pagUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedPagPermissions(): void
{
    foreach (['activities.view', 'notes.view', 'comments.view', 'addresses.view', 'timeline.view'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedPagPermissions();
    $this->tenant = pagTenant();
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
    $this->organization = Organization::create(['name' => 'Acme Corp']);
    $this->user = pagUser($this->tenant, [
        'activities.view', 'notes.view', 'comments.view', 'addresses.view', 'timeline.view',
    ]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

it('paginates activities by entity', function () {
    Activity::create(['activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call 1']);
    Activity::create(['activityable_type' => Organization::class, 'activityable_id' => $this->organization->id, 'type' => 'call', 'subject' => 'Call 2']);

    $response = $this->getJson('/api/tenant/v1/crm/activities/by-entity/organization/'.$this->organization->id.'?per_page=1')
        ->assertSuccessful();

    expect($response->json('meta'))->toHaveKey('current_page');
    expect($response->json('meta')['per_page'])->toBe(1);
});

it('paginates notes by entity', function () {
    Note::create(['noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Note 1']);
    Note::create(['noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Note 2']);

    $response = $this->getJson('/api/tenant/v1/crm/notes/by-entity/organization/'.$this->organization->id.'?per_page=1')
        ->assertSuccessful();

    expect($response->json('meta'))->toHaveKey('current_page');
    expect($response->json('meta')['per_page'])->toBe(1);
});

it('paginates comments by entity', function () {
    Comment::create(['commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Comment 1']);
    Comment::create(['commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Comment 2']);

    $response = $this->getJson('/api/tenant/v1/crm/comments/by-entity/organization/'.$this->organization->id.'?per_page=1')
        ->assertSuccessful();

    expect($response->json('meta'))->toHaveKey('current_page');
    expect($response->json('meta')['per_page'])->toBe(1);
});

it('paginates timeline by entity', function () {
    TimelineEntry::create(['tenant_id' => $this->tenant->id, 'entity_type' => Organization::class, 'entity_id' => $this->organization->id, 'event_type' => 'test.entry1', 'title' => 'Entry 1', 'occurred_at' => now()]);
    TimelineEntry::create(['tenant_id' => $this->tenant->id, 'entity_type' => Organization::class, 'entity_id' => $this->organization->id, 'event_type' => 'test.entry2', 'title' => 'Entry 2', 'occurred_at' => now()]);

    $response = $this->getJson('/api/tenant/v1/crm/timeline/by-entity/'.urlencode(Organization::class).'/'.$this->organization->id.'?per_page=1')
        ->assertSuccessful();

    expect($response->json('meta'))->toHaveKey('current_page');
    expect($response->json('meta')['per_page'])->toBe(1);
});

it('paginates addresses by entity', function () {
    Address::create(['addressable_type' => Organization::class, 'addressable_id' => $this->organization->id, 'type' => 'office', 'address_line_1' => '123 Main St']);
    Address::create(['addressable_type' => Organization::class, 'addressable_id' => $this->organization->id, 'type' => 'billing', 'address_line_1' => '456 Oak Ave']);

    $response = $this->getJson('/api/tenant/v1/crm/addresses/by-entity/organization/'.$this->organization->id.'?per_page=1')
        ->assertSuccessful();

    expect($response->json('meta'))->toHaveKey('current_page');
    expect($response->json('meta')['per_page'])->toBe(1);
});

it('respects max per_page of 100', function () {
    $response = $this->getJson('/api/tenant/v1/crm/activities/by-entity/organization/'.$this->organization->id.'?per_page=999')
        ->assertSuccessful();

    expect($response->json('meta')['per_page'])->toBeLessThanOrEqual(100);
});

it('defaults to 25 per_page', function () {
    $response = $this->getJson('/api/tenant/v1/crm/activities/by-entity/organization/'.$this->organization->id)
        ->assertSuccessful();

    expect($response->json('meta')['per_page'])->toBe(25);
});
