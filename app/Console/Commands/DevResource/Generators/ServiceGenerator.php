<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class ServiceGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'service';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        return app_path(sprintf(
            'Services/%s/%sService.php',
            ucfirst($context->context),
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return sprintf(
            'App\\Services\\%s',
            ucfirst($context->context),
        );
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sService', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
