<?php

use App\Models\Announcement;
use App\Models\CentralUser;
use Spatie\Permission\Models\Permission;

function announcementAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('announcements.list');
    $user->givePermissionTo('announcements.read');
    $user->givePermissionTo('announcements.create');
    $user->givePermissionTo('announcements.update');
    $user->givePermissionTo('announcements.delete');
    $user->givePermissionTo('announcements.restore');
    $user->givePermissionTo('announcements.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'announcements.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'announcements.force.delete', 'guard_name' => 'central-api']);
});

it('lists announcements', function () {
    Announcement::factory()->count(3)->create();

    $this->actingAs(announcementAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/announcements')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/announcements', [
        'title' => 'System Update',
        'content' => 'Scheduled maintenance tonight.',
        'type' => 'info',
        'audience_type' => 'all',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(7)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('announcements', ['title' => 'System Update']);
});

it('shows an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $announcement = Announcement::factory()->create();

    $this->getJson("/api/central/v1/announcements/{$announcement->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $announcement = Announcement::factory()->create();

    $this->putJson("/api/central/v1/announcements/{$announcement->id}", ['title' => 'Updated Title'])
        ->assertSuccessful();

    $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'title' => 'Updated Title']);
});

it('deletes an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $announcement = Announcement::factory()->create();
    $this->deleteJson("/api/central/v1/announcements/{$announcement->id}")->assertSuccessful();

    $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
});

it('restores an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $announcement = Announcement::factory()->create();
    $announcement->delete();

    $this->postJson("/api/central/v1/announcements/{$announcement->id}/restore")->assertSuccessful();

    $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'deleted_at' => null]);
});

it('force deletes an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $announcement = Announcement::factory()->create();
    $announcement->delete();

    $this->deleteJson("/api/central/v1/announcements/{$announcement->id}/force")->assertSuccessful();

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('requires authentication for announcements', function () {
    $this->getJson('/api/central/v1/announcements')->assertStatus(401);
});

it('validates required fields when creating an announcement', function () {
    $this->actingAs(announcementAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/announcements', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'content']);
});
