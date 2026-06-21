<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\Ticket;
use Spatie\Permission\Models\Permission;

function ticketAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('tickets.list');
    $user->givePermissionTo('tickets.read');
    $user->givePermissionTo('tickets.create');
    $user->givePermissionTo('tickets.update');
    $user->givePermissionTo('tickets.delete');
    $user->givePermissionTo('tickets.restore');
    $user->givePermissionTo('tickets.force.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'tickets.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.delete', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.restore', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'tickets.force.delete', 'guard_name' => 'central-api']);
});

it('lists tickets', function () {
    Ticket::factory()->count(3)->create();

    $this->actingAs(ticketAuthUser(), 'central-api');

    $this->getJson('/api/central/v1/tickets')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/central/v1/tickets', [
        'tenant_id' => $tenant->id,
        'subject' => 'Login issue',
        'description' => 'Unable to log in to the dashboard.',
        'priority' => 'high',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('tickets', ['subject' => 'Login issue']);
});

it('shows a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();

    $this->getJson("/api/central/v1/tickets/{$ticket->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();

    $this->putJson("/api/central/v1/tickets/{$ticket->id}", ['subject' => 'Updated subject'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'subject' => 'Updated subject']);
});

it('deletes a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();
    $this->deleteJson("/api/central/v1/tickets/{$ticket->id}")->assertSuccessful();

    $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
});

it('restores a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();
    $ticket->delete();

    $this->postJson("/api/central/v1/tickets/{$ticket->id}/restore")->assertSuccessful();

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'deleted_at' => null]);
});

it('force deletes a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();
    $ticket->delete();

    $this->deleteJson("/api/central/v1/tickets/{$ticket->id}/force")->assertSuccessful();

    $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
});

it('assigns a ticket to a user', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();
    $admin = CentralUser::factory()->create();

    $this->postJson("/api/central/v1/tickets/{$ticket->id}/assign", ['assigned_to' => $admin->id])
        ->assertSuccessful();

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => $admin->id]);
});

it('adds a reply to a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $ticket = Ticket::factory()->create();

    $this->postJson("/api/central/v1/tickets/{$ticket->id}/replies", [
        'content' => 'We are working on this issue.',
    ])->assertSuccessful();

    $this->assertDatabaseHas('ticket_replies', ['ticket_id' => $ticket->id]);
});

it('requires authentication for tickets', function () {
    $this->getJson('/api/central/v1/tickets')->assertStatus(401);
});

it('validates required fields when creating a ticket', function () {
    $this->actingAs(ticketAuthUser(), 'central-api');

    $this->postJson('/api/central/v1/tickets', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id', 'subject', 'description']);
});
