<?php

use App\Models\CentralUser;
use App\Models\Feature;
use App\Models\Plan;
use Spatie\Permission\Models\Permission;

function authUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('plans.read');
    $user->givePermissionTo('plans.update');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'plans.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'plans.update', 'guard_name' => 'central-api']);
});

it('lists features of a plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();
    $plan->features()->attach($feature->id, ['value' => 'true']);

    $response = $this->getJson("/api/central/v1/plans/{$plan->id}/features");

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'message' => 'Plan features retrieved successfully',
        ])
        ->assertJsonCount(1, 'data');
});

it('returns empty list when plan has no features', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();

    $response = $this->getJson("/api/central/v1/plans/{$plan->id}/features");

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('attaches a feature to a plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();

    $response = $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => $feature->id,
        'value' => 'true',
    ]);

    $response->assertCreated()
        ->assertJson([
            'status' => 'success',
            'message' => 'Feature has been attached to plan successfully',
        ]);

    $this->assertDatabaseHas('plan_features', [
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
        'value' => 'true',
    ]);
});

it('prevents duplicate feature attachment', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();

    $plan->features()->attach($feature->id, ['value' => 'false']);

    $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => $feature->id,
        'value' => 'true',
    ]);

    $this->assertDatabaseCount('plan_features', 1);
});

it('updates an existing feature value on a plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();
    $plan->features()->attach($feature->id, ['value' => 'false']);

    $response = $this->putJson("/api/central/v1/plans/{$plan->id}/features/{$feature->id}", [
        'value' => '10',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'message' => 'Feature value has been updated successfully',
        ]);

    $this->assertDatabaseHas('plan_features', [
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
        'value' => '10',
    ]);
});

it('removes a feature from a plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();
    $plan->features()->attach($feature->id, ['value' => 'true']);

    $response = $this->deleteJson("/api/central/v1/plans/{$plan->id}/features/{$feature->id}");

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'message' => 'Feature has been removed from plan successfully',
        ]);

    $this->assertDatabaseMissing('plan_features', [
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
    ]);
});

it('validates required fields when attaching a feature', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();

    $response = $this->postJson("/api/central/v1/plans/{$plan->id}/features", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['feature_id', 'value']);
});

it('validates feature_id exists when attaching', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();

    $response = $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => 99999,
        'value' => 'true',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['feature_id']);
});

it('validates value is required when updating', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create();
    $plan->features()->attach($feature->id, ['value' => 'true']);

    $response = $this->putJson("/api/central/v1/plans/{$plan->id}/features/{$feature->id}", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('returns 404 when attaching to a trashed plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $plan->delete();
    $feature = Feature::factory()->create();

    $response = $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => $feature->id,
        'value' => 'true',
    ]);

    $response->assertStatus(404);
});

it('attaches multiple features to a plan', function () {
    $this->actingAs(authUser(), 'central-api');

    $plan = Plan::factory()->create();
    $featureA = Feature::factory()->create();
    $featureB = Feature::factory()->create();

    $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => $featureA->id,
        'value' => 'true',
    ]);

    $this->postJson("/api/central/v1/plans/{$plan->id}/features", [
        'feature_id' => $featureB->id,
        'value' => '25',
    ]);

    $this->assertDatabaseCount('plan_features', 2);

    $response = $this->getJson("/api/central/v1/plans/{$plan->id}/features");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('requires authentication', function () {
    $plan = Plan::factory()->create();

    $response = $this->getJson("/api/central/v1/plans/{$plan->id}/features");

    $response->assertStatus(401);
});
