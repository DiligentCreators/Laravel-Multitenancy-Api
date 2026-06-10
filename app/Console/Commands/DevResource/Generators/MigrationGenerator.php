<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;
use Illuminate\Support\Str;

class MigrationGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'migration';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        $timestamp = now()->format('Y_m_d_His');
        $table = Str::plural(Str::snake($context->name));

        return database_path(sprintf(
            'migrations/%s_create_%s_table.php',
            $timestamp,
            $table,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return '';
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return '';
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        return [];
    }

    public function generate(ResourceContext $context): void
    {
        $path = $this->resolvePath($context);

        if (file_exists($path) && ! $context->force) {
            return;
        }

        $stub = $this->loadStub();
        $placeholders = $this->commonPlaceholders($context);
        $content = $this->replacePlaceholders($stub, $placeholders);
        $this->writeFile($path, $content);
    }
}
