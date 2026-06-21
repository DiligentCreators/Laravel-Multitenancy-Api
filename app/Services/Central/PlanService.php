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
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'monthly_price', 'yearly_price',
        'trial_days', 'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Plan $plan,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->plan
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Plan::search($search)->keys();
                $query->whereIn((new Plan)->getQualifiedKeyName(), $ids);
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy($sort, $direction);
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->with('features')
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
            ->with('features')
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
