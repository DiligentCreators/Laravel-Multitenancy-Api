<?php

use App\Models\CentralUser;
use App\Models\SmsTemplate;
use Spatie\Permission\Models\Permission;

function smsTemplateAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('sms-templates.list');
    $user->givePermissionTo('sms-templates.create');
    $user->givePermissionTo('sms-templates.read');
    $user->givePermissionTo('sms-templates.update');
    $user->givePermissionTo('sms-templates.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'sms-templates.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'sms-templates.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'sms-templates.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'sms-templates.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'sms-templates.delete', 'guard_name' => 'central-api']);
});

it('lists sms templates', function () {
    SmsTemplate::factory()->count(3)->create();

    $this->actingAs(smsTemplateAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/sms-templates');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an sms template', function () {
    $this->actingAs(smsTemplateAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/sms-templates', [
        'name' => 'OTP',
        'slug' => 'otp',
        'message' => 'Your OTP is {{otp_code}}',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('sms_templates', ['slug' => 'otp']);
});

it('shows an sms template', function () {
    $this->actingAs(smsTemplateAuthUser(), 'central-api');

    $template = SmsTemplate::factory()->create();

    $response = $this->getJson("/api/central/v1/sms-templates/{$template->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an sms template', function () {
    $this->actingAs(smsTemplateAuthUser(), 'central-api');

    $template = SmsTemplate::factory()->create();

    $response = $this->putJson("/api/central/v1/sms-templates/{$template->id}", [
        'message' => 'Updated message',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('sms_templates', ['id' => $template->id, 'message' => 'Updated message']);
});

it('deletes an sms template', function () {
    $this->actingAs(smsTemplateAuthUser(), 'central-api');

    $template = SmsTemplate::factory()->create();

    $response = $this->deleteJson("/api/central/v1/sms-templates/{$template->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('sms_templates', ['id' => $template->id]);
});

it('requires authentication for sms templates', function () {
    $this->getJson('/api/central/v1/sms-templates')->assertStatus(401);
});
