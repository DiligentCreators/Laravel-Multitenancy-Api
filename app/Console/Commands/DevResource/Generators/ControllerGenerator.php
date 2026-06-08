<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class ControllerGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'controller.api';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        $parts = [
            'Http',
            'Controllers',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
        ];

        if ($context->path) {
            $parts[] = $context->path;
        }

        $parts[] = sprintf('%sController.php', $context->name);

        return app_path(implode('/', $parts));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        $parts = [
            'App',
            'Http',
            'Controllers',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
        ];

        if ($context->path) {
            $parts[] = $context->path;
        }

        return implode('\\', $parts);
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sController', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }
}
