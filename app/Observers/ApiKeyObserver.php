<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ApiKey;

class ApiKeyObserver
{
    public function creating(ApiKey $apiKey): void {}

    public function created(ApiKey $apiKey): void {}

    public function updating(ApiKey $apiKey): void {}

    public function updated(ApiKey $apiKey): void {}

    public function saving(ApiKey $apiKey): void {}

    public function saved(ApiKey $apiKey): void {}

    public function deleting(ApiKey $apiKey): void {}

    public function deleted(ApiKey $apiKey): void {}

    public function restored(ApiKey $apiKey): void {}

    public function forceDeleted(ApiKey $apiKey): void {}
}
