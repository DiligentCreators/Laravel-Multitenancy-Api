<?php

use App\Models\CentralUser;
use App\Models\Invoice;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->user = CentralUser::factory()->create();
    if (! Role::where('name', 'superadmin')->where('guard_name', 'central-api')->exists()) {
        Role::create(['name' => 'superadmin', 'guard_name' => 'central-api']);
    }
    $this->user->assignRole('superadmin');
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->tenant = Tenant::factory()->create();
    $this->invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'amount' => 100,
        'total_amount' => 100,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
});

it('downloads invoice PDF for paid invoice', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/invoices/{$this->invoice->id}/pdf/download");

    $response->assertOk();
});

it('rejects PDF download for unpaid invoice', function () {
    $this->invoice->update(['paid_at' => null, 'status' => 'overdue']);

    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/invoices/{$this->invoice->id}/pdf/download");

    $response->assertStatus(400);
});

it('generates invoice PDF', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/central/v1/invoices/{$this->invoice->id}/pdf/generate");

    $response->assertOk()
        ->assertJsonPath('data.path', fn ($path) => str_contains($path, '.pdf'));
});

it('streams invoice PDF', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/central/v1/invoices/{$this->invoice->id}/pdf/stream");

    $response->assertOk();
});
