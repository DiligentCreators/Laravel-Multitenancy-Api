<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Note;
use App\Models\Crm\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function noteTenant(): Tenant
{
    $domain = 'note-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function noteUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedNotePermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "notes.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedNotePermissions();
    $this->tenant = noteTenant();
    $this->user = noteUser($this->tenant, ['notes.view', 'notes.create', 'notes.update', 'notes.delete']);
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

it('creates a note', function () {
    $this->postJson('/api/tenant/v1/crm/notes', [
        'noteable_type' => Organization::class,
        'noteable_id' => $this->organization->id,
        'content' => 'Important note about this organization',
        'is_pinned' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('lists notes', function () {
    Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Note 1']);
    Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Note 2']);

    $this->getJson('/api/tenant/v1/crm/notes')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a note', function () {
    $note = Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Important note']);

    $this->getJson("/api/tenant/v1/crm/notes/{$note->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a note', function () {
    $note = Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Important note']);

    $this->putJson("/api/tenant/v1/crm/notes/{$note->id}", [
        'content' => 'Updated content',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a note', function () {
    $note = Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Important note']);

    $this->deleteJson("/api/tenant/v1/crm/notes/{$note->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('searches notes by content', function () {
    Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Important meeting notes']);
    Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Random thoughts']);

    $this->getJson('/api/tenant/v1/crm/notes?search=meeting')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('restores a soft-deleted note', function () {
    $note = Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $this->organization->id, 'content' => 'Important note']);
    $note->delete();

    $this->postJson("/api/tenant/v1/crm/notes/{$note->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures note tenant isolation', function () {
    $tenant2 = noteTenant();
    tenancy()->initialize($tenant2);
    $org2 = Organization::create(['tenant_id' => $tenant2->id, 'name' => 'Other Corp']);
    $note2 = Note::create(['owner_id' => $this->user->id, 'noteable_type' => Organization::class, 'noteable_id' => $org2->id, 'content' => 'Other']);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/notes/{$note2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for notes', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/notes')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for notes', function () {
    $guest = noteUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/notes')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent note', function () {
    $this->getJson('/api/tenant/v1/crm/notes/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating note without content', function () {
    $this->postJson('/api/tenant/v1/crm/notes', [
        'noteable_type' => Organization::class,
        'noteable_id' => $this->organization->id,
    ])->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});
