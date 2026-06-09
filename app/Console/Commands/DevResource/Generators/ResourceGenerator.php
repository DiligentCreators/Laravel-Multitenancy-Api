<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\Contracts\ResourceGenerator as ResourceGeneratorContract;
use App\Console\Commands\DevResource\ResourceContext;
use Illuminate\Support\Str;

class ResourceGenerator implements ResourceGeneratorContract
{
    private function resolveBasePath(ResourceContext $context): string
    {
        $parts = [
            'Http',
            'Resources',
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
            'Resources',
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
        $model = $context->name;

        return [
            'context' => $contextStudly,
            'contextLower' => $context->context,
            'path' => $context->path,
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

        // Single resource: {Model}Resource.php
        $singleClass = sprintf('%sResource', $context->name);
        $singlePath = sprintf('%s/%s.php', $basePath, $singleClass);

        if (! file_exists($singlePath) || $context->force) {
            $stub = $this->loadStub('resource');
            $content = $this->replacePlaceholders($stub, array_merge($basePlaceholders, [
                'namespace' => $namespace,
                'class' => $singleClass,
            ]));
            $this->writeFile($singlePath, $content);
        }

        // List resource: List{Model}Resource.php
        $listClass = sprintf('List%sResource', $context->name);
        $listPath = sprintf('%s/%s.php', $basePath, $listClass);

        if (! file_exists($listPath) || $context->force) {
            $stub = $this->loadStub('list.resource');
            $content = $this->replacePlaceholders($stub, array_merge($basePlaceholders, [
                'namespace' => $namespace,
                'class' => $listClass,
            ]));
            $this->writeFile($listPath, $content);
        }
    }
}
