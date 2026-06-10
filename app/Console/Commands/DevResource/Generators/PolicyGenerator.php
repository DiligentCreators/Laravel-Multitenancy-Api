<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class PolicyGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'policy';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        return app_path(sprintf(
            'Policies/%sPolicy.php',
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return 'App\\Policies';
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sPolicy', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
