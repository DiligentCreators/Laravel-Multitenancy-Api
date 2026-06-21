<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'invoice_id', 'tenant_id', 'amount', 'currency',
        'gateway', 'transaction_id', 'status', 'paid_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Payment $payment,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->payment
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Payment::search($search)->keys();
                $query->whereIn((new Payment)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
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
            ->with(['tenant', 'invoice'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Payment
    {
        return $this->payment
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = $this->payment->create($data);

            if ($payment->status === 'completed' && $payment->invoice) {
                $payment->invoice->update([
                    'status' => 'paid',
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            }

            return $payment;
        });
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment;
    }

    public function markCompleted(Payment $payment, string $transactionId): Payment
    {
        return DB::transaction(function () use ($payment, $transactionId) {
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ]);

            if ($payment->invoice) {
                $payment->invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            return $payment->fresh();
        });
    }

    public function markFailed(Payment $payment): Payment
    {
        $payment->update(['status' => 'failed']);

        return $payment->fresh();
    }

    public function markRefunded(Payment $payment): Payment
    {
        $payment->update(['status' => 'refunded']);

        return $payment->fresh();
    }
}
