<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ModuleController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Module::class);

        $modules = Module::orderBy('name')->paginate(request('per_page', 15));

        return $this->api->success(
            'Modules retrieved successfully',
            $modules->through(fn ($module) => [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'version' => $module->version,
                'is_enabled' => $module->is_enabled,
                'dependencies' => $module->dependencies,
                'created_at' => $module->created_at,
                'updated_at' => $module->updated_at,
            ]),
        );
    }

    public function show(Module $module): JsonResponse
    {
        Gate::authorize('view', $module);

        return $this->api->success(
            'Module retrieved successfully',
            [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'version' => $module->version,
                'is_enabled' => $module->is_enabled,
                'dependencies' => $module->dependencies,
                'created_at' => $module->created_at,
                'updated_at' => $module->updated_at,
            ],
        );
    }

    public function enable(Module $module): JsonResponse
    {
        Gate::authorize('update', $module);

        $module->enable();

        return $this->api->success('Module has been enabled successfully');
    }

    public function disable(Module $module): JsonResponse
    {
        Gate::authorize('update', $module);

        $module->disable();

        return $this->api->success('Module has been disabled successfully');
    }

    public function enableForTenant(Request $request, Module $module): JsonResponse
    {
        Gate::authorize('update', $module);

        $validated = $request->validate(['tenant_id' => 'required|exists:tenants,id']);
        $module->enableForTenant(Tenant::findOrFail($validated['tenant_id']));

        return $this->api->success('Module enabled for tenant successfully');
    }

    public function disableForTenant(Request $request, Module $module): JsonResponse
    {
        Gate::authorize('update', $module);

        $validated = $request->validate(['tenant_id' => 'required|exists:tenants,id']);
        $module->disableForTenant(Tenant::findOrFail($validated['tenant_id']));

        return $this->api->success('Module disabled for tenant successfully');
    }

    public function seed(): JsonResponse
    {
        Gate::authorize('create', Module::class);

        $defaultModules = [
            ['name' => 'CRM Core', 'slug' => 'crm-core', 'description' => 'Core CRM functionality including contacts, companies, and leads', 'version' => '1.0.0'],
            ['name' => 'Solar', 'slug' => 'solar', 'description' => 'Solar industry module with site surveys and installation management', 'version' => '1.0.0'],
            ['name' => 'Agency', 'slug' => 'agency', 'description' => 'Agency management module with campaigns and retainers', 'version' => '1.0.0'],
            ['name' => 'Real Estate', 'slug' => 'real-estate', 'description' => 'Real estate module with properties and listings', 'version' => '1.0.0'],
        ];

        foreach ($defaultModules as $module) {
            Module::firstOrCreate(
                ['slug' => $module['slug']],
                $module,
            );
        }

        return $this->api->success('Default modules seeded successfully');
    }
}
