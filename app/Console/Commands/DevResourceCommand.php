<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\DevResource\Contracts\ResourceGenerator;
use App\Console\Commands\DevResource\Generators\ControllerGenerator;
use App\Console\Commands\DevResource\Generators\FactoryGenerator;
use App\Console\Commands\DevResource\Generators\MigrationGenerator;
use App\Console\Commands\DevResource\Generators\ModelGenerator;
use App\Console\Commands\DevResource\Generators\ObserverGenerator;
use App\Console\Commands\DevResource\Generators\PolicyGenerator;
use App\Console\Commands\DevResource\Generators\RequestGenerator;
use App\Console\Commands\DevResource\Generators\ResourceGenerator as ResourceApiGenerator;
use App\Console\Commands\DevResource\ResourceContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class DevResourceCommand extends Command
{
    private const string STUBS_DIR = 'stubs/dev-resource';

    protected $signature = '
        dev:resource
        {name? : The resource class name (e.g., User)}
        {path? : Module path for organization (e.g., User)}
        {--context= : Context namespace (central|tenant)}
        {--v= : API version (e.g., v1)}
        {--force : Overwrite existing files}
        {--save-defaults : Save the selected generators as defaults}
    ';

    protected $description = 'Generate a full resource scaffold with code generators';

    private array $generators = [
        'controller',
        'model',
        'request',
        'resource',
        'migration',
        'factory',
        'policy',
        'observer',
        'service',
        'repository',
        'action',
        'dto',
        'enum',
        'test',
    ];

    private array $generatorMap = [
        'controller' => ControllerGenerator::class,
        'model' => ModelGenerator::class,
        'request' => RequestGenerator::class,
        'resource' => ResourceApiGenerator::class,
        'migration' => MigrationGenerator::class,
        'factory' => FactoryGenerator::class,
        'policy' => PolicyGenerator::class,
        'observer' => ObserverGenerator::class,
    ];

    public function handle(): int
    {
        $name = $this->argument('name');
        $path = $this->argument('path') ?? '';
        $context = $this->option('context');
        $version = $this->option('v');
        $force = (bool) $this->option('force');
        $saveDefaults = (bool) $this->option('save-defaults');

        $interactive = $name === null;

        if ($interactive) {
            $name = text(
                label: 'Resource Name',
                placeholder: 'TradingAccount',
                default: $name ?? '',
                required: true,
                validate: fn (string $value) => preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value)
                    ? null
                    : 'The resource name must be a valid class name (e.g., TradingAccount).',
                transform: fn (string $value) => trim($value),
            );

            $context = select(
                label: 'Context',
                options: ['central' => 'Central', 'tenant' => 'Tenant'],
                default: $context ?: config('dev-resource.default_context', 'central'),
                scroll: 10,
            );

            $version = select(
                label: 'API Version',
                options: ['v1' => 'V1', 'v2' => 'V2'],
                default: $version ?: 'v1',
                scroll: 10,
            );

            $path = text(
                label: 'Module Path',
                placeholder: 'Trading',
                default: $path,
                hint: 'Optional subdirectory within the version namespace',
                validate: fn (string $value) => $value === '' || preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value)
                    ? null
                    : 'The module path must be a valid namespace segment (e.g., Trading).',
                transform: fn (string $value) => trim($value),
            );
        }

        $defaultGenerators = $this->resolveDefaultGenerators($context ?? 'central');

        $selectedGenerators = $defaultGenerators;

        if ($interactive) {
            $selectedGenerators = multiselect(
                label: 'Select what to generate',
                options: $this->generators,
                default: $defaultGenerators,
                scroll: 15,
            );

            $confirmed = confirm(
                label: 'Confirm?',
                default: true,
                yes: 'Generate',
                no: 'Cancel',
            );

            if (! $confirmed) {
                $this->warn('Generation cancelled.');

                return static::SUCCESS;
            }
        }

        if (empty($selectedGenerators)) {
            $this->warn('No generators selected. Nothing to generate.');

            return static::SUCCESS;
        }

        $contextValue = $context ?: config('dev-resource.default_context', 'central');
        $versionValue = $version ?: 'v1';
        $resourceContext = new ResourceContext(
            name: $name,
            path: $path,
            context: Str::lower($contextValue),
            version: Str::lower($versionValue),
            generators: $selectedGenerators,
            force: $force,
        );

        $this->generateResources($resourceContext);

        if ($saveDefaults) {
            $this->saveDefaultGenerators($selectedGenerators, $resourceContext->context);
            $this->info('Default generators saved to config/dev-resource.php');
        }

        return static::SUCCESS;
    }

    private function resolveDefaultGenerators(string $context): array
    {
        $configContext = $context === 'tenant' ? 'tenant_default_generators' : 'default_generators';

        return config("dev-resource.{$configContext}", config('dev-resource.default_generators', [
            'controller',
            'model',
            'request',
            'resource',
            'migration',
            'factory',
            'policy',
        ]));
    }

    private function generateResources(ResourceContext $context): void
    {
        foreach ($context->generators as $key) {
            if (! isset($this->generatorMap[$key])) {
                $this->warn(sprintf('Unknown generator: %s. Skipping.', $key));

                continue;
            }

            $class = $this->generatorMap[$key];
            $generator = app($class);

            if (! $generator instanceof ResourceGenerator) {
                $this->warn(sprintf('Generator %s must implement ResourceGenerator. Skipping.', $class));

                continue;
            }

            $generator->generate($context);
            $this->info(sprintf('Generated: %s', $key));
        }
    }

    private function saveDefaultGenerators(array $generators, string $context): void
    {
        $path = config_path('dev-resource.php');
        $contextKey = $context === 'tenant' ? 'tenant_default_generators' : 'default_generators';

        $config = file_exists($path) ? require $path : [];
        $config[$contextKey] = array_values($generators);

        $items = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $quoted = array_map(fn (string $v) => sprintf("        '%s'", $v), $value);
                $items[] = sprintf("    '%s' => [\n%s,\n    ]", $key, implode(",\n", $quoted));
            } else {
                $items[] = sprintf("    '%s' => '%s'", $key, $value);
            }
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n".implode(",\n\n", $items).",\n];\n";

        file_put_contents($path, $content);
    }
}
