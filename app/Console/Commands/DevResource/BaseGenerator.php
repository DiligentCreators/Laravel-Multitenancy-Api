<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource;

use App\Console\Commands\DevResource\Contracts\ResourceGenerator;
use Illuminate\Support\Str;

abstract class BaseGenerator implements ResourceGenerator
{
    abstract protected function stubKey(): string;

    abstract protected function resolvePath(ResourceContext $context): string;

    abstract protected function resolveNamespace(ResourceContext $context): string;

    abstract protected function resolveClass(ResourceContext $context): string;

    abstract protected function extraPlaceholders(ResourceContext $context): array;

    protected function commonPlaceholders(ResourceContext $context): array
    {
        $name = $context->name;
        $contextStudly = ucfirst($context->context);
        $contextLower = $context->context;
        $versionStudly = ucfirst($context->version);
        $versionLower = $context->version;
        $path = $context->path;
        $model = $name;
        $table = Str::plural(Str::snake($name));

        return [
            'name' => $name,
            'nameVariable' => lcfirst($name),
            'nameSnake' => Str::snake($name),
            'nameSnakePlural' => $table,
            'nameKebab' => Str::kebab($name),
            'nameKebabPlural' => Str::kebab(Str::plural($name)),
            'context' => $contextStudly,
            'contextLower' => $contextLower,
            'version' => $versionStudly,
            'versionLower' => $versionLower,
            'path' => $path,
            'pathSegment' => $path ? '\\'.$path : '',
            'model' => $model,
            'modelVariable' => lcfirst($model),
            'modelPlural' => lcfirst(Str::plural($model)),
            'modelNamespace' => sprintf('App\\Models\\%s', $model),
            'table' => $table,
            'qualifiedTable' => sprintf('%s_%s', $contextLower, $table),
            'userModel' => $contextLower === 'central' ? 'CentralUser' : 'User',
            'userModelNamespace' => $contextLower === 'central' ? 'App\\Models\\CentralUser' : 'App\\Models\\User',
            'userModelVariable' => $contextLower === 'central' ? 'centralUser' : 'user',
            'tenantTraitImport' => $contextLower === 'tenant' ? "\nuse App\\Models\\Traits\\BelongsToTenant;" : '',
            'tenantTraitUsage' => $contextLower === 'tenant' ? 'use BelongsToTenant;' : '',
            'tenantMigrationFields' => $contextLower === 'tenant' ? "            \$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();" : '',
        ];
    }

    public function generate(ResourceContext $context): void
    {
        $path = $this->resolvePath($context);

        if (file_exists($path) && ! $context->force) {
            return;
        }

        $stub = $this->loadStub();
        $placeholders = array_merge(
            $this->commonPlaceholders($context),
            [
                'namespace' => $this->resolveNamespace($context),
                'class' => $this->resolveClass($context),
            ],
            $this->extraPlaceholders($context),
        );

        $content = $this->replacePlaceholders($stub, $placeholders);
        $this->writeFile(path: $path, content: $content);
    }

    protected function loadStub(): string
    {
        $path = base_path(sprintf('stubs/dev-resource/%s.stub', $this->stubKey()));

        return file_get_contents($path);
    }

    protected function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace(sprintf('{{ %s }}', $key), $value, $content);
        }

        return $content;
    }

    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
    }

    protected function makeDirectory(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
