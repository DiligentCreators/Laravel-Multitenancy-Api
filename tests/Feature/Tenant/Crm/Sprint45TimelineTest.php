<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Crm\Lead;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\TimelineEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function s45Tenant(): Tenant
{
    $domain = 's45-test-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function s45User(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function seedS45Permissions(): void
{
    foreach (['people.view', 'people.create', 'people.update', 'people.delete'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'tenant-api']);
    }
    foreach (['organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'tenant-api']);
    }
    foreach (['leads.view', 'leads.create', 'leads.update', 'leads.delete', 'lead-stage.move'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'tenant-api']);
    }
}

beforeEach(function () {
    seedS45Permissions();
    $this->tenant = s45Tenant();
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
    tenancy()->end();
});

afterEach(function () {
    tenancy()->end();
});

describe('person timeline events', function () {
    beforeEach(function () {
        tenancy()->initialize($this->tenant);
        $this->user = s45User($this->tenant, ['people.view', 'people.create', 'people.update', 'people.delete']);
        $this->actingAs($this->user, 'tenant-api');
    });

    afterEach(function () {
        tenancy()->end();
    });

    it('creates timeline entry on person create', function () {
        $this->postJson('/api/tenant/v1/crm/people', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ])->assertCreated();

        $entry = TimelineEntry::where('event_type', 'person.created')->first();
        expect($entry)->not->toBeNull();
        expect($entry->title)->toBe('Person Created');
    });

    it('creates timeline entry on person update', function () {
        $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $this->putJson("/api/tenant/v1/crm/people/{$person->id}", [
            'first_name' => 'Jane',
        ])->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'person.updated')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on person delete', function () {
        $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $this->deleteJson("/api/tenant/v1/crm/people/{$person->id}")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'person.deleted')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on person restore', function () {
        $person = Person::create(['owner_id' => $this->user->id, 'first_name' => 'John', 'last_name' => 'Doe']);
        $person->delete();

        $this->postJson("/api/tenant/v1/crm/people/{$person->id}/restore")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'person.restored')->first();
        expect($entry)->not->toBeNull();
    });
});

describe('organization timeline events', function () {
    beforeEach(function () {
        tenancy()->initialize($this->tenant);
        $this->user = s45User($this->tenant, ['organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete']);
        $this->actingAs($this->user, 'tenant-api');
    });

    afterEach(function () {
        tenancy()->end();
    });

    it('creates timeline entry on organization create', function () {
        $this->postJson('/api/tenant/v1/crm/organizations', [
            'name' => 'Acme Corp',
        ])->assertCreated();

        $entry = TimelineEntry::where('event_type', 'organization.created')->first();
        expect($entry)->not->toBeNull();
        expect($entry->title)->toBe('Organization Created');
    });

    it('creates timeline entry on organization update', function () {
        $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);

        $this->putJson("/api/tenant/v1/crm/organizations/{$org->id}", [
            'name' => 'Acme Corp Updated',
        ])->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'organization.updated')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on organization delete', function () {
        $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);

        $this->deleteJson("/api/tenant/v1/crm/organizations/{$org->id}")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'organization.deleted')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on organization restore', function () {
        $org = Organization::create(['owner_id' => $this->user->id, 'name' => 'Acme Corp']);
        $org->delete();

        $this->postJson("/api/tenant/v1/crm/organizations/{$org->id}/restore")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'organization.restored')->first();
        expect($entry)->not->toBeNull();
    });
});

describe('lead timeline events', function () {
    beforeEach(function () {
        tenancy()->initialize($this->tenant);
        $this->user = s45User($this->tenant, ['leads.view', 'leads.create', 'leads.update', 'leads.delete', 'lead-stage.move']);
        $this->actingAs($this->user, 'tenant-api');
        $this->pipeline = Pipeline::create(['name' => 'Sales Pipeline', 'sort_order' => 1]);
        $this->stageNew = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'sort_order' => 1, 'probability' => 10]);
        $this->stageQualified = PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Qualified', 'sort_order' => 2, 'probability' => 50]);
    });

    afterEach(function () {
        tenancy()->end();
    });

    it('creates timeline entry on lead create', function () {
        $this->postJson('/api/tenant/v1/crm/leads', [
            'title' => 'Big Deal',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
        ])->assertCreated();

        $entry = TimelineEntry::where('event_type', 'lead.created')->first();
        expect($entry)->not->toBeNull();
        expect($entry->title)->toBe('Lead Created');
    });

    it('creates timeline entry on lead update', function () {
        $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

        $this->putJson("/api/tenant/v1/crm/leads/{$lead->id}", [
            'title' => 'Updated Deal',
        ])->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'lead.updated')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on lead delete', function () {
        $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

        $this->deleteJson("/api/tenant/v1/crm/leads/{$lead->id}")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'lead.deleted')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on lead restore', function () {
        $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);
        $lead->delete();

        $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/restore")->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'lead.restored')->first();
        expect($entry)->not->toBeNull();
    });

    it('creates timeline entry on lead stage move', function () {
        $lead = Lead::create(['owner_id' => $this->user->id, 'title' => 'Big Deal', 'pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->stageNew->id]);

        $this->postJson("/api/tenant/v1/crm/leads/{$lead->id}/move-stage", [
            'pipeline_stage_id' => $this->stageQualified->id,
        ])->assertSuccessful();

        $entry = TimelineEntry::where('event_type', 'lead.stage_moved')->first();
        expect($entry)->not->toBeNull();
    });
});
