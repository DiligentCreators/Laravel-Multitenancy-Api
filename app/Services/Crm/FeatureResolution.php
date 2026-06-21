<?php

namespace App\Services\Crm;

class FeatureResolution
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $deniedBy = null,
        public readonly ?string $reason = null,
        public readonly ?int $limit = null,
        public readonly ?int $usage = null,
        public readonly bool $isOverage = false,
    ) {}
}
