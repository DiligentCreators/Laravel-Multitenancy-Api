<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class InvoiceService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'invoice_number', 'tenant_id', 'subscription_id', 'amount',
        'tax_amount', 'discount_amount', 'total_amount', 'currency',
        'status', 'due_date', 'paid_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Invoice $invoice,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->invoice
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Invoice::search($search)->keys();
                $query->whereIn((new Invoice)->getQualifiedKeyName(), $ids);
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
            ->with(['tenant', 'subscription'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Invoice
    {
        return $this->invoice
            ->query()
            ->withTrashed()
            ->with(['tenant', 'subscription', 'payments'])
            ->findOrFail($id);
    }

    public function create(array $data): Invoice
    {
        $data['invoice_number'] ??= $this->generateInvoiceNumber();
        $data['total_amount'] = ($data['amount'] ?? 0) + ($data['tax_amount'] ?? 0) - ($data['discount_amount'] ?? 0);

        return $this->invoice->create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if (isset($data['amount']) || isset($data['tax_amount']) || isset($data['discount_amount'])) {
            $data['total_amount'] = ($data['amount'] ?? $invoice->amount)
                + ($data['tax_amount'] ?? $invoice->tax_amount)
                - ($data['discount_amount'] ?? $invoice->discount_amount);
        }

        $invoice->update($data);

        return $invoice;
    }

    public function markPaid(Invoice $invoice, ?string $paidAt = null): Invoice
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => $paidAt ?: now(),
        ]);

        return $invoice->fresh();
    }

    public function markOverdue(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'overdue']);

        return $invoice->fresh();
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $date = now()->format('Ymd');
        $last = $this->invoice->query()->withTrashed()
            ->where('invoice_number', 'like', "{$prefix}{$date}-%")
            ->latest()
            ->first();

        $sequence = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return sprintf('%s%s-%04d', $prefix, $date, $sequence);
    }
}
