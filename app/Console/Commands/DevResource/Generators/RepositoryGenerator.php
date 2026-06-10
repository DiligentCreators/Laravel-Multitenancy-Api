<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class RepositoryGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'repository';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        return app_path(sprintf(
            'Repositories/%s/%sRepository.php',
            ucfirst($context->context),
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return sprintf(
            'App\\Repositories\\%s',
            ucfirst($context->context),
        );
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sRepository', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
