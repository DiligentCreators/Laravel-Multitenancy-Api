<?php

it('returns healthy status', function () {
    $response = $this->getJson('/api/health');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['healthy', 'message'],
                'cache' => ['healthy', 'message'],
            ],
        ]);

    expect($response->json('status'))->toBe('healthy');
    expect($response->json('checks.database.healthy'))->toBeTrue();
});
