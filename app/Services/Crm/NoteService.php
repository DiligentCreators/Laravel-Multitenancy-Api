<?php

namespace App\Services\Crm;

use App\Models\Crm\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoteService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'is_pinned', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Note::query()->with(['noteable'])->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Note::search($search)->keys();
            $query->whereIn((new Note)->getQualifiedKeyName(), $ids);
        }

        if (isset($filters['is_pinned'])) {
            $query->where('is_pinned', filter_var($filters['is_pinned'], FILTER_VALIDATE_BOOLEAN));
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Note
    {
        return Note::with(['noteable'])->findOrFail($id);
    }

    public function create(array $data): Note
    {
        return DB::transaction(function () use ($data) {
            $note = Note::create($data);

            $this->eventDispatcher->record($note, 'note.created', 'Note Added', null, [
                'note_id' => $note->id,
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ], Auth::id());

            return $note;
        });
    }

    public function update(Note $note, array $data): Note
    {
        $note->update($data);

        return $note;
    }

    public function delete(Note $note): void
    {
        $note->delete();
    }

    public function restore(int $id): void
    {
        Note::withTrashed()->findOrFail($id)->restore();
    }

    public function getForEntity(string $type, int $id): Collection
    {
        return Note::where('noteable_type', $type)
            ->where('noteable_id', $id)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForEntityPaginated(string $type, int $id, int $perPage = 25): LengthAwarePaginator
    {
        return Note::where('noteable_type', $type)
            ->where('noteable_id', $id)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100));
    }
}
