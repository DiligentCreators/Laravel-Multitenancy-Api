<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Task;
use App\Models\Crm\TaskReminder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function trTenant(): Tenant
{
    $domain = 'treminder-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function trUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedTrPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "tasks.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedTrPermissions();
    $this->tenant = trTenant();
    $this->user = trUser($this->tenant, ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);
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

it('creates a task reminder', function () {
    $this->postJson("/api/tenant/v1/crm/tasks/{$this->task->id}/reminders", [
        'remind_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists task reminders', function () {
    TaskReminder::create(['task_id' => $this->task->id, 'remind_at' => now()->addDay(), 'owner_id' => $this->user->id]);
    TaskReminder::create(['task_id' => $this->task->id, 'remind_at' => now()->addDays(2), 'owner_id' => $this->user->id]);

    $this->getJson("/api/tenant/v1/crm/tasks/{$this->task->id}/reminders")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a task reminder', function () {
    $reminder = TaskReminder::create(['task_id' => $this->task->id, 'remind_at' => now()->addDay(), 'owner_id' => $this->user->id]);

    $this->getJson("/api/tenant/v1/crm/tasks/reminders/{$reminder->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a task reminder', function () {
    $reminder = TaskReminder::create(['task_id' => $this->task->id, 'remind_at' => now()->addDay(), 'owner_id' => $this->user->id]);

    $this->deleteJson("/api/tenant/v1/crm/tasks/reminders/{$reminder->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a task reminder', function () {
    $reminder = TaskReminder::create(['task_id' => $this->task->id, 'remind_at' => now()->addDay(), 'owner_id' => $this->user->id]);

    $this->putJson("/api/tenant/v1/crm/tasks/reminders/{$reminder->id}", [
        'remind_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('returns 422 when creating reminder without remind_at', function () {
    $this->postJson("/api/tenant/v1/crm/tasks/{$this->task->id}/reminders", [])
        ->assertStatus(422)
        ->assertJson(['status' => false]);
});

it('returns 404 for non-existent task reminder', function () {
    $this->getJson('/api/tenant/v1/crm/tasks/reminders/99999')
        ->assertStatus(404);
});
