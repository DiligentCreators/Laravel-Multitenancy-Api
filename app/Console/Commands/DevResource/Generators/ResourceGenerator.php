<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class ResourceGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'resource';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        $parts = [
            'Http',
            'Resources',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
            $context->name,
        ];

        $parts[] = sprintf('%sResource.php', $context->name);

        return app_path(implode('/', $parts));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        $parts = [
            'App',
            'Http',
            'Resources',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
            $context->name,
        ];

        return implode('\\', $parts);
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return sprintf('%sResource', $context->name);
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }

    public function generate(ResourceContext $context): void
    {
        $namespace = $this->resolveNamespace($context);
        $basePath = dirname($this->resolvePath($context));
        $basePlaceholders = array_merge(
            $this->commonPlaceholders($context),
            ['namespace' => $namespace],
        );

        foreach ([
            'resource' => sprintf('%sResource', $context->name),
            'list.resource' => sprintf('List%sResource', $context->name),
        ] as $stubKey => $className) {
            $path = sprintf('%s/%s.php', $basePath, $className);

            if (file_exists($path) && ! $context->force) {
                continue;
            }

            $stub = $this->loadStubFromKey($stubKey);
            $content = $this->replacePlaceholders($stub, array_merge($basePlaceholders, [
                'class' => $className,
            ]));

            $this->writeFile($path, $content);
        }
    }

    private function loadStubFromKey(string $key): string
    {
        return file_get_contents(base_path(sprintf('stubs/dev-resource/%s.stub', $key)));
    }
}
