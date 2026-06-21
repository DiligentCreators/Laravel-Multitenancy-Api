<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Central\DunningService;

beforeEach(function () {
    $this->service = app(DunningService::class);
    $this->tenant = Tenant::factory()->create();
    $this->invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'amount' => 100,
        'total_amount' => 100,
        'status' => 'overdue',
    ]);
    $this->payment = Payment::factory()->create([
        'invoice_id' => $this->invoice->id,
        'tenant_id' => $this->tenant->id,
        'amount' => 100,
        'status' => 'failed',
    ]);
});

it('handles first failed payment correctly', function () {
    $result = $this->service->handleFailedPayment($this->payment);

    expect($result['should_retry'])->toBeTrue()
        ->and($result['attempt_number'])->toBe(1)
        ->and($result['next_attempt_at'])->not->toBeNull();

    $this->payment->refresh();
    $data = $this->payment->data;
    expect($data['failed_attempts'])->toBe(1)
        ->and($data['dunning_level'])->toBe(1);
});

it('escalates after max attempts', function () {
    $data = $this->payment->data ?? [];
    $data['failed_attempts'] = 5;
    $data['dunning_level'] = 5;
    $this->payment->data = $data;
    $this->payment->save();

    $result = $this->service->handleFailedPayment($this->payment);

    expect($result['should_retry'])->toBeFalse()
        ->and($result['next_attempt_at'])->toBeNull()
        ->and($result['attempt_number'])->toBe(6);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe('overdue');
});

it('sends dunning notification at correct level', function () {
    $result = $this->service->sendDunningNotification($this->invoice, 1);

    expect($result['dunning_level'])->toBe(1)
        ->and($result['sent_at'])->not->toBeNull();

    $this->invoice->refresh();
    $data = $this->invoice->data;
    expect($data['dunning_notifications'][0]['template'])->toBe('payment_reminder_1')
        ->and($data['dunning_notifications'][0]['level'])->toBe(1);
});

it('uses correct template names for each dunning level', function () {
    $templates = [
        1 => 'payment_reminder_1',
        2 => 'payment_reminder_2',
        3 => 'payment_reminder_3',
        4 => 'final_notice',
        5 => 'suspension_notice',
    ];

    foreach ($templates as $level => $expectedTemplate) {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'overdue',
        ]);
        $result = $this->service->sendDunningNotification($invoice, $level);

        $invoice->refresh();
        $notifications = $invoice->data['dunning_notifications'];
        $last = end($notifications);
        expect($last['template'])->toBe($expectedTemplate);
    }
});

it('retries payment correctly and schedules next attempt', function () {
    $result = $this->service->retryPayment($this->payment);

    expect($result['should_retry'])->toBeTrue()
        ->and($result['next_attempt_at'])->not->toBeNull();
});

it('does not retry already paid invoice', function () {
    $this->invoice->update(['paid_at' => now(), 'status' => 'paid']);

    $result = $this->service->retryPayment($this->payment);

    expect($result['should_retry'])->toBeFalse();
});

it('escalates to manual correctly', function () {
    $this->service->escalateToManual($this->invoice);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe('overdue');

    $data = $this->invoice->data;
    expect($data['dunning_escalated'])->toBeTrue();
});
