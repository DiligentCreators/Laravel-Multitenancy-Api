<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\Contracts\ResourceGenerator;
use App\Console\Commands\DevResource\ResourceContext;
use Illuminate\Support\Str;

class RequestGenerator implements ResourceGenerator
{
    private function resolveBasePath(ResourceContext $context): string
    {
        $parts = [
            'Http',
            'Requests',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
        ];

        if ($context->path) {
            $parts[] = $context->path;
        }

        return app_path(implode('/', $parts));
    }

    private function resolveNamespace(ResourceContext $context): string
    {
        $parts = [
            'App',
            'Http',
            'Requests',
            ucfirst($context->context),
            'Api',
            ucfirst($context->version),
        ];

        if ($context->path) {
            $parts[] = $context->path;
        }

        return implode('\\', $parts);
    }

    private function loadStub(string $key): string
    {
        return file_get_contents(base_path(sprintf('stubs/dev-resource/%s.stub', $key)));
    }

    private function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace(sprintf('{{ %s }}', $key), $value, $content);
        }

        return $content;
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function commonPlaceholders(ResourceContext $context): array
    {
        $contextStudly = ucfirst($context->context);
        $contextLower = $context->context;
        $model = $context->name;

        return [
            'context' => $contextStudly,
            'contextLower' => $contextLower,
            'version' => ucfirst($context->version),
            'versionLower' => $context->version,
            'path' => $context->path ?: $context->name,
            'model' => $model,
            'modelVariable' => lcfirst($model),
            'modelPlural' => lcfirst(Str::plural($model)),
            'modelNamespace' => sprintf('App\\Models\\%s', $model),
        ];
    }

    public function generate(ResourceContext $context): void
    {
        $namespace = $this->resolveNamespace($context);
        $basePath = $this->resolveBasePath($context);
        $basePlaceholders = $this->commonPlaceholders($context);

        foreach (['store', 'update'] as $type) {
            $className = sprintf('%s%sRequest', ucfirst($type), $context->name);
            $path = sprintf('%s/%s.php', $basePath, $className);

            if (file_exists($path) && ! $context->force) {
                continue;
            }

            $stub = $this->loadStub(sprintf('%s.request', $type));
            $content = $this->replacePlaceholders($stub, array_merge($basePlaceholders, [
                'namespace' => $namespace,
                'class' => $className,
            ]));

            $this->writeFile($path, $content);
        }
    }
}
