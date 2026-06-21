<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Ticket\AddTicketReplyRequest;
use App\Http\Requests\Central\Api\V1\Ticket\AssignTicketRequest;
use App\Http\Requests\Central\Api\V1\Ticket\StoreTicketRequest;
use App\Http\Requests\Central\Api\V1\Ticket\UpdateTicketRequest;
use App\Http\Resources\Central\Api\V1\Ticket\ListTicketResource;
use App\Http\Resources\Central\Api\V1\Ticket\TicketResource;
use App\Models\Ticket;
use App\Services\ApiResponseService;
use App\Services\Central\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TicketService $ticketService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $tickets = $this->ticketService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'tickets retrieved successfully',
            ListTicketResource::collection($tickets),
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        Gate::authorize('create', Ticket::class);

        $ticket = $this->ticketService->create($request->validated());

        return $this->api->success(
            'Ticket has been created successfully',
            new TicketResource($ticket),
            201,
        );
    }

    public function show(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        if ($ticket->trashed()) {
            return $this->api->notFound('Ticket has been deleted.');
        }

        return $this->api->success(
            'Ticket retrieved successfully',
            new TicketResource($ticket),
        );
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        if ($ticket->trashed()) {
            return $this->api->notFound('Cannot update a deleted ticket.');
        }

        $this->ticketService->update($ticket, $request->validated());

        return $this->api->success(
            'Ticket has been updated successfully',
            new TicketResource($ticket),
        );
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        if ($ticket->trashed()) {
            return $this->api->notFound('Ticket is already deleted.');
        }

        $ticket->delete();

        return $this->api->success(
            'Ticket has been deleted successfully',
            null,
            200,
        );
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $this->ticketService->assign($ticket, (int) $request->input('assigned_to'));

        return $this->api->success(
            'Ticket has been assigned successfully',
            new TicketResource($ticket->fresh()),
        );
    }

    public function addReply(AddTicketReplyRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $data = array_merge($request->validated(), [
            'central_user_id' => auth()->id(),
        ]);

        $reply = $this->ticketService->addReply($ticket, $data);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return $this->api->success(
            'Reply has been added successfully',
            $reply->load('user'),
            201,
        );
    }

    public function restore(Ticket $ticket): JsonResponse
    {
        Gate::authorize('restore', $ticket);

        if (! $ticket->trashed()) {
            return $this->api->notFound('Ticket is not deleted.');
        }

        $ticket->restore();

        return $this->api->success(
            'Ticket has been restored successfully',
            new TicketResource($ticket),
        );
    }

    public function forceDelete(Ticket $ticket): JsonResponse
    {
        Gate::authorize('forceDelete', $ticket);

        if (! $ticket->trashed()) {
            return $this->api->error('Ticket must be deleted before force deleting.', 400);
        }

        $ticket->forceDelete();

        return $this->api->success(
            'Ticket has been force deleted successfully',
            null,
            200,
        );
    }
}
