<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SettingDefinition;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenantSettingController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function index(Tenant $tenant): JsonResponse
    {
        Gate::authorize('viewAny', TenantSetting::class);

        $definitions = SettingDefinition::where('is_active', true)->orderBy('group')->get();
        $settings = TenantSetting::where('tenant_id', $tenant->id)->get()->keyBy('setting_definition_id');

        $result = $definitions->map(function ($definition) use ($settings) {
            $existing = $settings->get($definition->id);

            return [
                'id' => $definition->id,
                'group' => $definition->group,
                'key' => $definition->key,
                'label' => $definition->label,
                'type' => $definition->type,
                'is_required' => $definition->is_required,
                'value' => $existing ? $existing->value : $definition->default_value,
            ];
        });

        return $this->api->success(
            'Tenant settings retrieved successfully',
            [
                'tenant_id' => $tenant->id,
                'settings' => $result->groupBy('group'),
            ],
        );
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('update', TenantSetting::class);

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.setting_definition_id' => 'required|exists:setting_definitions,id',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            TenantSetting::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'setting_definition_id' => $setting['setting_definition_id'],
                ],
                ['value' => $setting['value'] ?? ''],
            );
        }

        return $this->api->success('Tenant settings have been updated successfully');
    }
}
