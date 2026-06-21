<?php

use App\Jobs\ExecuteWorkflowJob;
use App\Jobs\RecordTimelineEntryJob;
use App\Jobs\TriggerWorkflowJob;
use App\Models\Crm\Organization;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

it('RecordTimelineEntryJob retryUntil returns now plus 5 minutes', function () {
    $job = new RecordTimelineEntryJob('tenant-1', 'organization', 1, 'created', 'Title');

    $result = $job->retryUntil();

    expect($result)->toBeInstanceOf(Carbon::class);
    expect($result->diffInMinutes(now()))->toBeLessThanOrEqual(1);
});

it('RecordTimelineEntryJob backoff returns expected array', function () {
    $job = new RecordTimelineEntryJob('tenant-1', 'organization', 1, 'created', 'Title');

    expect($job->backoff())->toBe([2, 5, 10, 30]);
});

it('RecordTimelineEntryJob failed logs error and cleans up tenancy', function () {
    tenancy()->initialize(Tenant::factory()->create());
    Log::spy();
    $exception = new Exception('Test failure');
    $job = new RecordTimelineEntryJob('tenant-1', 'organization', 1, 'created', 'Title');

    $job->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(fn ($message) => $message === 'RecordTimelineEntryJob failed');
});

it('TriggerWorkflowJob retryUntil returns now plus 5 minutes', function () {
    $job = new TriggerWorkflowJob('tenant-1', 'test.event', Organization::class, 1);

    $result = $job->retryUntil();

    expect($result)->toBeInstanceOf(Carbon::class);
    expect($result->diffInMinutes(now()))->toBeLessThanOrEqual(1);
});

it('TriggerWorkflowJob backoff returns expected array', function () {
    $job = new TriggerWorkflowJob('tenant-1', 'test.event', Organization::class, 1);

    expect($job->backoff())->toBe([2, 5, 10, 30]);
});

it('TriggerWorkflowJob failed logs error and cleans up tenancy', function () {
    tenancy()->initialize(Tenant::factory()->create());
    Log::spy();
    $exception = new Exception('Trigger failure');
    $job = new TriggerWorkflowJob('tenant-1', 'test.event', Organization::class, 1);

    $job->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(fn ($message) => $message === 'TriggerWorkflowJob failed');
});

it('ExecuteWorkflowJob retryUntil returns now plus 5 minutes', function () {
    $job = new ExecuteWorkflowJob('tenant-1', 1, Organization::class, 1);

    $result = $job->retryUntil();

    expect($result)->toBeInstanceOf(Carbon::class);
    expect($result->diffInMinutes(now()))->toBeLessThanOrEqual(1);
});

it('ExecuteWorkflowJob backoff returns expected array', function () {
    $job = new ExecuteWorkflowJob('tenant-1', 1, Organization::class, 1);

    expect($job->backoff())->toBe([2, 5, 10, 30]);
});

it('ExecuteWorkflowJob failed logs error and cleans up tenancy', function () {
    tenancy()->initialize(Tenant::factory()->create());
    Log::spy();
    $exception = new Exception('Execute failure');
    $job = new ExecuteWorkflowJob('tenant-1', 1, Organization::class, 1);

    $job->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(fn ($message) => $message === 'ExecuteWorkflowJob failed');
});

it('defines correct static properties', function () {
    $job = new RecordTimelineEntryJob('tenant-1', 'organization', 1, 'created', 'Title');

    expect($job->tries)->toBe(0);
    expect($job->maxExceptions)->toBe(3);
    expect($job->timeout)->toBe(60);
});
