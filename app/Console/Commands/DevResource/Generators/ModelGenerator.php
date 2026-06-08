<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource\Generators;

use App\Console\Commands\DevResource\BaseGenerator;
use App\Console\Commands\DevResource\ResourceContext;

class ModelGenerator extends BaseGenerator
{
    protected function stubKey(): string
    {
        return 'model';
    }

    protected function resolvePath(ResourceContext $context): string
    {
        return app_path(sprintf(
            'Models/%s/%s.php',
            ucfirst($context->context),
            $context->name,
        ));
    }

    protected function resolveNamespace(ResourceContext $context): string
    {
        return sprintf(
            'App\\Models\\%s',
            ucfirst($context->context),
        );
    }

    protected function resolveClass(ResourceContext $context): string
    {
        return $context->name;
    }

    protected function extraPlaceholders(ResourceContext $context): array
    {
        $studlyContext = ucfirst($context->context);
        $model = $context->name;

        $hasObserver = in_array('observer', $context->generators, true);
        $hasPolicy = in_array('policy', $context->generators, true);

        $observerNamespace = sprintf('App\\Observers\\%s\\%sObserver', $studlyContext, $model);
        $policyNamespace = sprintf('App\\Policies\\%s\\%sPolicy', $studlyContext, $model);

        return [
            'observedByImport' => $hasObserver
                ? "\nuse Illuminate\\Database\\Eloquent\\Attributes\\ObservedBy;"
                : '',
            'observedByAttribute' => $hasObserver
                ? sprintf("#[ObservedBy(%sObserver::class)]\n", $model)
                : '',
            'observerImport' => $hasObserver
                ? "\nuse {$observerNamespace};"
                : '',
            'usePolicyImport' => $hasPolicy
                ? "\nuse Illuminate\\Database\\Eloquent\\Attributes\\UsePolicy;"
                : '',
            'usePolicyAttribute' => $hasPolicy
                ? sprintf("#[UsePolicy(%sPolicy::class)]\n", $model)
                : '',
            'policyImport' => $hasPolicy
                ? "\nuse {$policyNamespace};"
                : '',
        ];
    }
}
