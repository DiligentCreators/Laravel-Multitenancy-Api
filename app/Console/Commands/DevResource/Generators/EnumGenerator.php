<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class EnumGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'enum';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        return app_path(sprintf(
            'Enums/%s/%sEnum.php',
            ucfirst($context->context),
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return sprintf(
            'App\\Enums\\%s',
            ucfirst($context->context),
        );
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sEnum', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
