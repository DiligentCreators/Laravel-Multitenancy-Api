<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Contracts;

use App\Console\Commands\DevResource\ResourceContext;

interface ResourceGenerator
{
    public function generate(ResourceContext $context): void;
}
