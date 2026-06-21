<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CentralUser::class);

        $query = Activity::query()
            ->whereHas('causer', fn ($q) => $q->whereNull('deleted_at'))
            ->when(request()->filled('user_id'), fn ($q) => $q->where('causer_id', request('user_id'))->where('causer_type', CentralUser::class))
            ->when(request()->filled('event'), fn ($q) => $q->where('event', request('event')))
            ->when(request()->filled('from'), fn ($q) => $q->where('created_at', '>=', request('from')))
            ->when(request()->filled('to'), fn ($q) => $q->where('created_at', '<=', request('to')))
            ->when(request()->filled('subject_type'), fn ($q) => $q->where('subject_type', request('subject_type')))
            ->when(request()->filled('subject_id'), fn ($q) => $q->where('subject_id', request('subject_id')))
            ->latest();

        $perPage = min((int) request('per_page', 15), 100);
        $logs = $query->paginate($perPage)->withQueryString();

        return $this->api->success(
            'Audit logs retrieved successfully',
            $logs->through(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'causer' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'before' => $log->properties?->get('old'),
                'after' => $log->properties?->get('attributes'),
                'created_at' => $log->created_at,
            ]),
        );
    }
}
