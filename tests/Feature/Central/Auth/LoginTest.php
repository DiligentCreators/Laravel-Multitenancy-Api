<?php

use App\Models\CentralUser;

it('logs in with valid credentials', function () {
    CentralUser::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/central/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'token',
                'type',
                'user' => ['id', 'name', 'email'],
            ],
        ]);
});

it('fails with incorrect password', function () {
    CentralUser::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/central/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'error',
            'message' => 'The provided credentials are incorrect.',
        ]);
});

it('fails with non-existent email', function () {
    $response = $this->postJson('/api/central/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('fails with missing email and password', function () {
    $response = $this->postJson('/api/central/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('fails with invalid email format', function () {
    $response = $this->postJson('/api/central/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
