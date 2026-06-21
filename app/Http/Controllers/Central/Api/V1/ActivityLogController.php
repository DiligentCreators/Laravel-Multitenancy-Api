<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CentralUser::class);

        $query = Activity::query()
            ->when(request()->filled('user_id'), fn ($q) => $q->where('causer_id', request('user_id'))->where('causer_type', CentralUser::class))
            ->when(request()->filled('tenant_id'), fn ($q) => $q->where('subject_type', Tenant::class)->where('subject_id', request('tenant_id')))
            ->when(request()->filled('event'), fn ($q) => $q->where('event', request('event')))
            ->when(request()->filled('from'), fn ($q) => $q->where('created_at', '>=', request('from')))
            ->when(request()->filled('to'), fn ($q) => $q->where('created_at', '<=', request('to')))
            ->when(request()->filled('search'), fn ($q) => $q->where('description', 'like', '%'.request()->string('search').'%'))
            ->latest();

        $perPage = min((int) request('per_page', 15), 100);
        $logs = $query->paginate($perPage)->withQueryString();

        return $this->api->success(
            'Activity logs retrieved successfully',
            $logs->through(fn ($log) => [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'event' => $log->event,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'causer_type' => $log->causer_type,
                'causer_id' => $log->causer_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at,
            ]),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('viewAny', CentralUser::class);

        $log = Activity::findOrFail($id);

        return $this->api->success(
            'Activity log retrieved successfully',
            [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'event' => $log->event,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'causer_type' => $log->causer_type,
                'causer_id' => $log->causer_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at,
            ],
        );
    }
}
