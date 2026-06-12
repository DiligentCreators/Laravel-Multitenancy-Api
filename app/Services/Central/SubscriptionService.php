<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SubscriptionService
{
    public function __construct(
        protected Subscription $subscription,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->subscription
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

    public function find(int|string $id): Subscription
    {
        return $this->subscription
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Subscription
    {
        $billingCycle = $data['billing_cycle'];

        $startsAt = Carbon::parse($data['starts_at']);

        if ($billingCycle == SubscriptionBillingCycleEnum::YEARLY->value) {
            $data['ends_at'] = $startsAt->addYear();
        } else {
            $data['ends_at'] = $startsAt->addMonth();
        }

        return $this->subscription->create($data);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        if (isset($data['billing_cycle'], $data['starts_at'])) {
            $startsAt = Carbon::parse($data['starts_at']);

            $data['ends_at'] = $data['billing_cycle'] == SubscriptionBillingCycleEnum::YEARLY->value
                ? $startsAt->addYear()
                : $startsAt->addMonth();
        }

        $subscription->update($data);

        return $subscription;
    }
}
