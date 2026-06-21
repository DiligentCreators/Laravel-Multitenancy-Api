<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TicketService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'ticket_number', 'tenant_id', 'subject', 'priority',
        'status', 'assigned_to', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Ticket $ticket,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->ticket
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Ticket::search($search)->keys();
                $query->whereIn((new Ticket)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn (Builder $query) => $query->where('priority', $request->input('priority')))
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
            ->with(['tenant', 'assignedTo'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Ticket
    {
        return $this->ticket
            ->query()
            ->withTrashed()
            ->with(['tenant', 'assignedTo', 'replies.user'])
            ->findOrFail($id);
    }

    public function create(array $data): Ticket
    {
        $data['ticket_number'] ??= $this->generateTicketNumber();

        return $this->ticket->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket;
    }

    public function assign(Ticket $ticket, int $userId): Ticket
    {
        $ticket->update([
            'assigned_to' => $userId,
            'status' => 'in_progress',
        ]);

        return $ticket->fresh();
    }

    public function addReply(Ticket $ticket, array $data): TicketReply
    {
        return $ticket->replies()->create($data);
    }

    private function generateTicketNumber(): string
    {
        $prefix = 'TKT-';
        $last = $this->ticket->query()->withTrashed()
            ->where('ticket_number', 'like', "{$prefix}%")
            ->latest()
            ->first();

        $sequence = $last ? (int) substr($last->ticket_number, 4) + 1 : 1;

        return sprintf('%s%06d', $prefix, $sequence);
    }
}
