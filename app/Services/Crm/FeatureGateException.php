<?php

namespace App\Services\Crm;

class FeatureGateException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $deniedBy = null,
    ) {
        parent::__construct($message);
    }
}
