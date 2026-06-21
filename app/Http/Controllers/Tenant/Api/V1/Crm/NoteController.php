<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreNoteRequest;
use App\Http\Requests\Crm\UpdateNoteRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\NoteResource;
use App\Models\Crm\Note;
use App\Services\ApiResponseService;
use App\Services\Crm\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly NoteService $noteService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Note::class);

        $perPage = min((int) request('per_page', 25), 100);
        $notes = $this->noteService->paginateWithFilters(request()->only([
            'search', 'is_pinned', 'owner_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Notes retrieved successfully', NoteResource::collection($notes));
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        Gate::authorize('create', Note::class);

        $note = $this->noteService->create($request->validated());

        return $this->api->success('Note created successfully', new NoteResource($note), 201);
    }

    public function show(Note $note): JsonResponse
    {
        Gate::authorize('view', $note);

        $note = $this->noteService->find($note->id);

        return $this->api->success('Note retrieved successfully', new NoteResource($note));
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        Gate::authorize('update', $note);

        $note = $this->noteService->update($note, $request->validated());

        return $this->api->success('Note updated successfully', new NoteResource($note));
    }

    public function destroy(Note $note): JsonResponse
    {
        Gate::authorize('delete', $note);

        $this->noteService->delete($note);

        return $this->api->success('Note deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Note::class);

        $this->noteService->restore($id);

        return $this->api->success('Note restored successfully');
    }

    public function byEntity(string $type, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Note::class);

        $perPage = min((int) request('per_page', 25), 100);
        $notes = $this->noteService->getForEntityPaginated($type, $id, $perPage);

        return $this->api->success('Notes retrieved successfully', NoteResource::collection($notes));
    }
}
