<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\Contracts\ResourceGenerator;
use App\Console\Commands\DevResource\ResourceContext;
use Illuminate\Support\Str;

class PermissionConfigGenerator implements ResourceGenerator
{
    public function generate(ResourceContext $context): void
    {
        $configFile = $context->context === 'central'
            ? config_path('central-permissions.php')
            : config_path('tenant-permissions.php');

        $moduleKey = Str::kebab(Str::plural($context->name));

        $actions = ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete'];

        $config = file_exists($configFile) ? require $configFile : [];

        if (isset($config[$moduleKey]) && ! $context->force) {
            return;
        }

        $config[$moduleKey] = $actions;

        $this->writeConfig($configFile, $config);
    }

    private function writeConfig(string $path, array $config): void
    {
        $lines = ['<?php', '', 'return ['];

        foreach ($config as $key => $actions) {
            $lines[] = sprintf("    '%s' => [", $key);
            foreach ($actions as $action) {
                $lines[] = sprintf("        '%s',", $action);
            }
            $lines[] = '    ],';
        }

        $lines[] = '];';
        $lines[] = '';

        file_put_contents($path, implode("\n", $lines));
    }
}
