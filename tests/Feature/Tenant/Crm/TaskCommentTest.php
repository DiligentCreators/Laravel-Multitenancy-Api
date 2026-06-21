<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Task;
use App\Models\Crm\TaskComment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function tcTenant(): Tenant
{
    $domain = 'tcomment-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function tcUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTcPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "tasks.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedTcPermissions();
    $this->tenant = tcTenant();
    $this->user = tcUser($this->tenant, ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);
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
    $this->task = Task::create(['title' => 'Test Task', 'priority' => 'medium']);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

it('creates a task comment', function () {
    $this->postJson("/api/tenant/v1/crm/tasks/{$this->task->id}/comments", [
        'content' => 'Great progress on this task',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists task comments', function () {
    TaskComment::create(['task_id' => $this->task->id, 'content' => 'Comment 1', 'owner_id' => $this->user->id]);
    TaskComment::create(['task_id' => $this->task->id, 'content' => 'Comment 2', 'owner_id' => $this->user->id]);

    $this->getJson("/api/tenant/v1/crm/tasks/{$this->task->id}/comments")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a task comment', function () {
    $comment = TaskComment::create(['task_id' => $this->task->id, 'content' => 'Detail', 'owner_id' => $this->user->id]);

    $this->getJson("/api/tenant/v1/crm/tasks/comments/{$comment->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a task comment', function () {
    $comment = TaskComment::create(['task_id' => $this->task->id, 'content' => 'Old', 'owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/tasks/comments/{$comment->id}", [
        'content' => 'Updated content',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a task comment', function () {
    $comment = TaskComment::create(['task_id' => $this->task->id, 'content' => 'To delete', 'owner_id' => $this->user->id]);

    $this->deleteJson("/api/tenant/v1/crm/tasks/comments/{$comment->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('returns 422 when creating task comment without content', function () {
    $this->postJson("/api/tenant/v1/crm/tasks/{$this->task->id}/comments", [])
        ->assertStatus(422)
        ->assertJson(['status' => false]);
});

it('returns 404 for non-existent task comment', function () {
    $this->getJson('/api/tenant/v1/crm/tasks/comments/99999')
        ->assertStatus(404);
});
