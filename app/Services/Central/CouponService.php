<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CouponService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'code', 'type', 'amount', 'usage_limit', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Coupon $coupon,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->coupon
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Coupon::search($search)->keys();
                $query->whereIn((new Coupon)->getQualifiedKeyName(), $ids);
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
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Coupon
    {
        return $this->coupon
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Coupon
    {
        return $this->coupon->create($data);
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $coupon;
    }

    public function findByCode(string $code): ?Coupon
    {
        return $this->coupon->query()->where('code', $code)->first();
    }

    public function validateCoupon(string $code): array
    {
        $coupon = $this->findByCode($code);

        if (! $coupon) {
            return ['valid' => false, 'message' => 'Coupon not found.'];
        }

        if (! $coupon->isValid()) {
            return ['valid' => false, 'message' => 'Coupon is expired or inactive.'];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'message' => 'Coupon is valid.',
        ];
    }

    public function applyCoupon(string $code, float $amount): array
    {
        $validation = $this->validateCoupon($code);

        if (! $validation['valid']) {
            return $validation;
        }

        $coupon = $validation['coupon'];
        $discount = $coupon->apply($amount);

        $coupon->markUsed();

        return [
            'valid' => true,
            'code' => $coupon->code,
            'discount' => $discount,
            'total' => max(0, $amount - $discount),
        ];
    }
}
