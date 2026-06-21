<?php

use App\Models\CentralUser;
use App\Models\NotificationTemplate;
use Spatie\Permission\Models\Permission;

function notificationTemplateAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('notification-templates.list');
    $user->givePermissionTo('notification-templates.create');
    $user->givePermissionTo('notification-templates.read');
    $user->givePermissionTo('notification-templates.update');
    $user->givePermissionTo('notification-templates.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'notification-templates.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'notification-templates.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'notification-templates.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'notification-templates.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'notification-templates.delete', 'guard_name' => 'central-api']);
});

it('lists notification templates', function () {
    NotificationTemplate::factory()->count(3)->create();

    $this->actingAs(notificationTemplateAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/notification-templates');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a notification template', function () {
    $this->actingAs(notificationTemplateAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/notification-templates', [
        'name' => 'Test Notification',
        'slug' => 'test-notification',
        'channel' => 'in_app',
        'title' => 'Test Title',
        'message' => 'Test message',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('notification_templates', ['slug' => 'test-notification']);
});

it('shows a notification template', function () {
    $this->actingAs(notificationTemplateAuthUser(), 'central-api');

    $template = NotificationTemplate::factory()->create();

    $response = $this->getJson("/api/central/v1/notification-templates/{$template->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a notification template', function () {
    $this->actingAs(notificationTemplateAuthUser(), 'central-api');

    $template = NotificationTemplate::factory()->create();

    $response = $this->putJson("/api/central/v1/notification-templates/{$template->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('notification_templates', ['id' => $template->id, 'title' => 'Updated Title']);
});

it('deletes a notification template', function () {
    $this->actingAs(notificationTemplateAuthUser(), 'central-api');

    $template = NotificationTemplate::factory()->create();

    $response = $this->deleteJson("/api/central/v1/notification-templates/{$template->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
});

it('requires authentication for notification templates', function () {
    $this->getJson('/api/central/v1/notification-templates')->assertStatus(401);
});
