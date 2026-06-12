<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PlanService
{
    public function __construct(
        protected Plan $plan,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->plan
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Plan
    {
        return $this->plan
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Plan
    {
        return $this->plan->create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan;
    }

    public function getFeatures(Plan $plan): Collection
    {
        return $plan->features()->get();
    }

    public function attachFeature(Plan $plan, array $data): Plan
    {
        $plan->features()->syncWithoutDetaching([
            $data['feature_id'] => [
                'value' => $data['value'],
            ],
        ]);

        return $plan->fresh('features');
    }

    public function updateFeatureValue(Plan $plan, Feature $feature, array $data): Plan
    {
        $plan->features()->updateExistingPivot(
            $feature->id,
            ['value' => $data['value']],
        );

        return $plan->fresh('features');
    }

    public function removeFeature(Plan $plan, Feature $feature): Plan
    {
        $plan->features()->detach($feature->id);

        return $plan->fresh('features');
    }
}
