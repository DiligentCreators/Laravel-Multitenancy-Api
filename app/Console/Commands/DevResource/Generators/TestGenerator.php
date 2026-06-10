<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class TestGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'test';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        $type = $context->context === 'central' ? 'Feature' : 'Feature';

        return base_path(sprintf(
            'tests/%s/%s/%sTest.php',
            $type,
            ucfirst($context->context),
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        $type = 'Feature';

        return sprintf(
            'Tests\\%s\\%s',
            $type,
            ucfirst($context->context),
        );
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sTest', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
