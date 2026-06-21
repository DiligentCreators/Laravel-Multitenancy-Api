<?php

use App\Models\CentralUser;
use App\Models\EmailTemplate;
use Spatie\Permission\Models\Permission;

function emailTemplateAuthUser(): CentralUser
{
    $user = CentralUser::factory()->create();
    $user->givePermissionTo('email-templates.list');
    $user->givePermissionTo('email-templates.create');
    $user->givePermissionTo('email-templates.read');
    $user->givePermissionTo('email-templates.update');
    $user->givePermissionTo('email-templates.delete');

    return $user;
}

beforeEach(function () {
    Permission::create(['name' => 'email-templates.list', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'email-templates.create', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'email-templates.read', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'email-templates.update', 'guard_name' => 'central-api']);
    Permission::create(['name' => 'email-templates.delete', 'guard_name' => 'central-api']);
});

it('lists email templates', function () {
    EmailTemplate::factory()->count(3)->create();

    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $response = $this->getJson('/api/central/v1/email-templates');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $response = $this->postJson('/api/central/v1/email-templates', [
        'name' => 'Welcome',
        'slug' => 'welcome',
        'subject' => 'Welcome {{user_name}}',
        'body' => 'Hello {{user_name}}, welcome to our platform!',
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('email_templates', ['slug' => 'welcome']);
});

it('shows an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $template = EmailTemplate::factory()->create();

    $response = $this->getJson("/api/central/v1/email-templates/{$template->id}");

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('updates an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $template = EmailTemplate::factory()->create();

    $response = $this->putJson("/api/central/v1/email-templates/{$template->id}", [
        'subject' => 'Updated Subject',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('email_templates', ['id' => $template->id, 'subject' => 'Updated Subject']);
});

it('deletes an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $template = EmailTemplate::factory()->create();

    $response = $this->deleteJson("/api/central/v1/email-templates/{$template->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
});

it('previews an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello {{user_name}}',
        'body' => 'Welcome {{user_name}}!',
    ]);

    $response = $this->postJson("/api/central/v1/email-templates/{$template->id}/preview", [
        'variables' => ['user_name' => 'John'],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);
});

it('duplicates an email template', function () {
    $this->actingAs(emailTemplateAuthUser(), 'central-api');

    $template = EmailTemplate::factory()->create();

    $response = $this->postJson("/api/central/v1/email-templates/{$template->id}/duplicate");

    $response->assertStatus(201);
});

it('requires authentication for email templates', function () {
    $this->getJson('/api/central/v1/email-templates')->assertStatus(401);
});
