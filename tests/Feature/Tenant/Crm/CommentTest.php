<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Comment;
use App\Models\Crm\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function commentTenant(): Tenant
{
    $domain = 'comment-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function commentUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedCommentPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "comments.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedCommentPermissions();
    $this->tenant = commentTenant();
    $this->user = commentUser($this->tenant, ['comments.view', 'comments.create', 'comments.update', 'comments.delete']);
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
    $this->organization = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates a comment', function () {
    $this->postJson('/api/tenant/v1/crm/comments', [
        'commentable_type' => Organization::class,
        'commentable_id' => $this->organization->id,
        'content' => 'Great organization to work with',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists comments', function () {
    Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Comment 1']);
    Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Comment 2']);

    $this->getJson('/api/tenant/v1/crm/comments')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a comment', function () {
    $comment = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Great organization']);

    $this->getJson("/api/tenant/v1/crm/comments/{$comment->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a comment', function () {
    $comment = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Great organization']);

    $this->putJson("/api/tenant/v1/crm/comments/{$comment->id}", [
        'content' => 'Updated comment',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a comment', function () {
    $comment = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Great organization']);

    $this->deleteJson("/api/tenant/v1/crm/comments/{$comment->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('creates a threaded reply', function () {
    $parent = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Parent comment']);

    $this->postJson('/api/tenant/v1/crm/comments', [
        'commentable_type' => Organization::class,
        'commentable_id' => $this->organization->id,
        'parent_id' => $parent->id,
        'content' => 'Reply to parent',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists replies for a comment', function () {
    $parent = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Parent']);
    Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'parent_id' => $parent->id, 'content' => 'Reply 1']);
    Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'parent_id' => $parent->id, 'content' => 'Reply 2']);

    $this->getJson("/api/tenant/v1/crm/comments/{$parent->id}/replies")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('restores a soft-deleted comment', function () {
    $comment = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $this->organization->id, 'content' => 'Comment']);
    $comment->delete();

    $this->postJson("/api/tenant/v1/crm/comments/{$comment->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures comment tenant isolation', function () {
    $tenant2 = commentTenant();
    tenancy()->initialize($tenant2);
    $org2 = Organization::create(['tenant_id' => $tenant2->id, 'name' => 'Other Corp']);
    $comment2 = Comment::create(['owner_id' => $this->user->id, 'commentable_type' => Organization::class, 'commentable_id' => $org2->id, 'content' => 'Other']);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/comments/{$comment2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for comments', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/comments')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for comments', function () {
    $guest = commentUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/comments')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent comment', function () {
    $this->getJson('/api/tenant/v1/crm/comments/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating comment without content', function () {
    $this->postJson('/api/tenant/v1/crm/comments', [
        'commentable_type' => Organization::class,
        'commentable_id' => $this->organization->id,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
