<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\CalendarEvent;
use App\Models\Crm\RecurringEventPattern;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function calendarTenant(): Tenant
{
    $domain = 'calendar-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function calendarUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedCalendarPermissions(): void
{
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "calendar.{$action}", 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedCalendarPermissions();
    $this->tenant = calendarTenant();
    $this->user = calendarUser($this->tenant, ['calendar.view', 'calendar.create', 'calendar.update', 'calendar.delete']);
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
    $this->actingAs($this->user, 'tenant-api');
});

afterEach(function () {
    tenancy()->end();
});

// --- Happy Path ---

it('creates a calendar event', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [
        'title' => 'Team Meeting',
        'description' => 'Weekly sync',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('creates a calendar event with recurring pattern', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [
        'title' => 'Daily Standup',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->addMinutes(15)->format('Y-m-d H:i:s'),
        'recurring' => [
            'frequency' => 'daily',
            'interval' => 1,
            'occurrences_limit' => 5,
        ],
    ])->assertCreated()
        ->assertJson(['status' => 'success']);

    $events = CalendarEvent::count();
    expect($events)->toBe(6);
});

it('lists calendar events', function () {
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Event 1', 'starts_at' => now()->addDay()]);
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Event 2', 'starts_at' => now()->addDays(2)]);

    $this->getJson('/api/tenant/v1/crm/calendar-events')
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('shows a calendar event', function () {
    $event = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'My Event', 'starts_at' => now()->addDay()]);

    $this->getJson("/api/tenant/v1/crm/calendar-events/{$event->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates a calendar event', function () {
    $event = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Old Title', 'starts_at' => now()->addDay()]);

    $this->putJson("/api/tenant/v1/crm/calendar-events/{$event->id}", [
        'title' => 'Updated Title',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('deletes a calendar event', function () {
    $event = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'To Delete', 'starts_at' => now()->addDay()]);

    $this->deleteJson("/api/tenant/v1/crm/calendar-events/{$event->id}")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('searches calendar events', function () {
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Client Meeting', 'starts_at' => now()->addDay()]);
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Lunch Break', 'starts_at' => now()->addDay()]);

    $this->getJson('/api/tenant/v1/crm/calendar-events?search=Meeting')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters calendar events by status', function () {
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Scheduled', 'starts_at' => now()->addDay(), 'status' => 'scheduled']);
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Cancelled', 'starts_at' => now()->addDay(), 'status' => 'cancelled']);

    $this->getJson('/api/tenant/v1/crm/calendar-events?status=cancelled')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('filters calendar events by date range', function () {
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Today', 'starts_at' => now()]);
    CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Next Week', 'starts_at' => now()->addWeek()]);

    $this->getJson('/api/tenant/v1/crm/calendar-events?from_date='.now()->addDays(3)->format('Y-m-d'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('restores a soft-deleted calendar event', function () {
    $event = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'To Restore', 'starts_at' => now()->addDay()]);
    $event->delete();

    $this->postJson("/api/tenant/v1/crm/calendar-events/{$event->id}/restore")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('supports all_day events', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [
        'title' => 'All Day Event',
        'starts_at' => now()->addDay()->format('Y-m-d'),
        'all_day' => true,
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

it('supports events with location and color', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [
        'title' => 'Office Meeting',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'location' => 'Conference Room A',
        'color' => '#ff0000',
    ])->assertCreated()
        ->assertJson(['status' => 'success']);
});

// --- Tenant Isolation ---

it('ensures calendar event tenant isolation', function () {
    $tenant2 = calendarTenant();
    tenancy()->initialize($tenant2);
    $event2 = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Other Tenant Event', 'starts_at' => now()->addDay()]);
    tenancy()->end();

    tenancy()->initialize($this->tenant);

    $this->getJson("/api/tenant/v1/crm/calendar-events/{$event2->id}")
        ->assertStatus(404)
        ->assertJson(['status' => false]);
});

// --- Negative Tests ---

it('returns 401 when not authenticated for calendar events', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/tenant/v1/crm/calendar-events')
        ->assertStatus(401)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks view permission for calendar events', function () {
    $guest = calendarUser($this->tenant, []);
    $this->actingAs($guest, 'tenant-api');

    $this->getJson('/api/tenant/v1/crm/calendar-events')
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks create permission for calendar events', function () {
    $guest = calendarUser($this->tenant, ['calendar.view']);
    $this->actingAs($guest, 'tenant-api');

    $this->postJson('/api/tenant/v1/crm/calendar-events', ['title' => 'Test', 'starts_at' => now()->format('Y-m-d H:i:s')])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 403 when user lacks update permission for calendar events', function () {
    $guest = calendarUser($this->tenant, ['calendar.view', 'calendar.create']);
    $this->actingAs($guest, 'tenant-api');
    $event = CalendarEvent::create(['owner_id' => $this->user->id, 'title' => 'Mine', 'starts_at' => now()->addDay()]);

    $this->putJson("/api/tenant/v1/crm/calendar-events/{$event->id}", ['title' => 'Hacked'])
        ->assertStatus(403)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 404 for non-existent calendar event', function () {
    $this->getJson('/api/tenant/v1/crm/calendar-events/99999')
        ->assertStatus(404)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating calendar event without title', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('returns 422 when creating calendar event without starts_at', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', ['title' => 'No Date'])
        ->assertStatus(422)
        ->assertJson(['status' => false])
        ->assertJsonStructure(['status', 'message', 'errors']);
});

it('handles pagination for calendar events', function () {
    for ($i = 1; $i <= 30; $i++) {
        CalendarEvent::create(['owner_id' => $this->user->id, 'title' => "Event {$i}", 'starts_at' => now()->addDays($i)]);
    }

    $response = $this->getJson('/api/tenant/v1/crm/calendar-events?per_page=10')
        ->assertSuccessful();

    expect($response->json('meta.total'))->toBe(30);
    expect($response->json('meta.per_page'))->toBe(10);
});

it('creates calendar event and verifies recurring pattern', function () {
    $this->postJson('/api/tenant/v1/crm/calendar-events', [
        'title' => 'Weekly Review',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'recurring' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'occurrences_limit' => 4,
        ],
    ])->assertCreated();

    $pattern = RecurringEventPattern::first();
    expect($pattern)->not->toBeNull();
    expect($pattern->frequency->value)->toBe('weekly');
    expect($pattern->interval)->toBe(1);

    $events = CalendarEvent::where('recurring_event_pattern_id', $pattern->id)->count();
    expect($events)->toBe(5);
});
