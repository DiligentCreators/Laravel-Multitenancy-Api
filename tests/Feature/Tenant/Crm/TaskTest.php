<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use App\Models\Crm\Task;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function taskTenant(): Tenant
{
    $domain = 'task-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function taskUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTaskPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "tasks.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedTaskPermissions();
    $this->tenant = taskTenant();
    $this->user = taskUser($this->tenant, ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);
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
    $statusType = StatusType::create(['entity_type' => 'task', 'name' => 'Task Statuses', 'key' => 'task_statuses']);
    $this->openStatus = Status::create(['type_id' => $statusType->id, 'name' => 'Open', 'key' => 'open', 'color' => '#6366f1', 'order' => 1, 'is_default' => true]);
    $this->completedStatus = Status::create(['type_id' => $statusType->id, 'name' => 'Completed', 'key' => 'completed', 'color' => '#22c55e', 'order' => 3]);
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates a task', function () {
    $this->postJson('/api/tenant/v1/crm/tasks', [
        'title' => 'Complete documentation',
        'description' => 'Write API docs for the CRM module',
        'priority' => 'high',
        'due_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists tasks', function () {
    Task::create(['title' => 'Task 1', 'priority' => 'medium', 'owner_id' => $this->user->id]);
    Task::create(['title' => 'Task 2', 'priority' => 'low', 'owner_id' => $this->user->id]);

    $this->getJson('/api/tenant/v1/crm/tasks')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a task', function () {
    $task = Task::create(['title' => 'My Task', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->getJson("/api/tenant/v1/crm/tasks/{$task->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a task', function () {
    $task = Task::create(['title' => 'Old Title', 'priority' => 'low', 'owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/tasks/{$task->id}", [
        'title' => 'Updated Title',
        'priority' => 'high',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a task', function () {
    $task = Task::create(['title' => 'To Delete', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->deleteJson("/api/tenant/v1/crm/tasks/{$task->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('searches tasks', function () {
    Task::create(['title' => 'Fix login bug', 'priority' => 'urgent', 'owner_id' => $this->user->id]);
    Task::create(['title' => 'Add sorting', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->getJson('/api/tenant/v1/crm/tasks?search=login')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters tasks by priority', function () {
    Task::create(['title' => 'Urgent Task', 'priority' => 'urgent', 'owner_id' => $this->user->id]);
    Task::create(['title' => 'Normal Task', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->getJson('/api/tenant/v1/crm/tasks?priority=urgent')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters tasks by status_id', function () {
    Task::create(['title' => 'Open Task', 'priority' => 'medium', 'owner_id' => $this->user->id, 'status_id' => $this->openStatus->id]);
    Task::create(['title' => 'Completed Task', 'priority' => 'medium', 'owner_id' => $this->user->id, 'status_id' => $this->completedStatus->id]);

    $response = $this->getJson('/api/tenant/v1/crm/tasks?status_id='.$this->completedStatus->id);
    $response->assertSuccessful();
    expect(collect($response->json('data'))->pluck('id'))->toHaveCount(1);
});

it('filters tasks by owner_id', function () {
    $otherUser = taskUser($this->tenant, ['tasks.view']);
    Task::create(['title' => 'My Task', 'priority' => 'medium', 'owner_id' => $this->user->id]);
    Task::create(['title' => 'Other Task', 'priority' => 'medium', 'owner_id' => $otherUser->id]);

    $response = $this->getJson('/api/tenant/v1/crm/tasks?owner_id='.$otherUser->id);
    $response->assertSuccessful();
    expect(collect($response->json('data'))->pluck('id'))->toHaveCount(1);
});

it('restores a soft-deleted task', function () {
    $task = Task::create(['title' => 'To Restore', 'priority' => 'medium', 'owner_id' => $this->user->id]);
    $task->delete();

    $this->postJson("/api/tenant/v1/crm/tasks/{$task->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('completes a task via update', function () {
    $task = Task::create(['title' => 'To Complete', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/tasks/{$task->id}", [
        'completed_at' => now()->format('Y-m-d H:i:s'),
    ])->assertSuccessful();

    $task->refresh();
    expect($task->completed_at)->not->toBeNull();
});

// --- Tenant Isolation ---

it('ensures task tenant isolation', function () {
    $tenant2 = taskTenant();
    tenancy()->initialize($tenant2);
    $task2 = Task::create(['title' => 'Other Tenant Task', 'priority' => 'medium', 'owner_id' => $this->user->id]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/tasks/{$task2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for tasks', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/tasks')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for tasks', function () {
    $guest = taskUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/tasks')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for tasks', function () {
    $guest = taskUser($this->tenant, ['tasks.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/tasks', ['title' => 'Test'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for tasks', function () {
    $guest = taskUser($this->tenant, ['tasks.view', 'tasks.create']);
    $this->actingAs($guest, 'tenant-api');
    $task = Task::create(['title' => 'Mine', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/tasks/{$task->id}", ['title' => 'Hacked'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks delete permission for tasks', function () {
    $guest = taskUser($this->tenant, ['tasks.view', 'tasks.create', 'tasks.update']);
    $this->actingAs($guest, 'tenant-api');
    $task = Task::create(['title' => 'Mine', 'priority' => 'medium', 'owner_id' => $this->user->id]);

    $this->deleteJson("/api/tenant/v1/crm/tasks/{$task->id}")
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent task', function () {
    $this->getJson('/api/tenant/v1/crm/tasks/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating task without title', function () {
    $this->postJson('/api/tenant/v1/crm/tasks', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('handles pagination for tasks', function () {
    for ($i = 1; $i <= 30; $i++) {
        Task::create(['title' => "Task {$i}", 'priority' => 'medium', 'owner_id' => $this->user->id]);
    }

    $response = $this->getJson('/api/tenant/v1/crm/tasks?per_page=10')
        ->assertSuccessful();

    expect($response->json('meta.total'))->toBe(30);
    expect($response->json('meta.per_page'))->toBe(10);
});
