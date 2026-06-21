<?php

use App\Jobs\Central\BillingAutomationJob;
use App\Jobs\Central\DailySubscriptionCheckJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('subscriptions:expire')->daily();

Schedule::job(new DailySubscriptionCheckJob)->daily();
Schedule::job(new BillingAutomationJob)->daily();

Schedule::command('monitor:queue-health')->daily()->description('Monitor queue health metrics');
Schedule::command('monitor:job-failures')->daily()->description('Monitor job failure metrics');
Schedule::command('monitor:storage-usage')->daily()->description('Monitor storage usage metrics');
