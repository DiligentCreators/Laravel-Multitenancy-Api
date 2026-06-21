<?php

namespace App\Services\Crm;

use App\Models\Crm\DocumentFolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DocumentFolderService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'parent_id', 'sort_order', 'owner_id',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return DocumentFolder::query()
            ->with(['parent', 'children'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = DocumentFolder::search($search)->keys();
            $query->whereIn((new DocumentFolder)->getQualifiedKeyName(), $ids);
        }

        if ($parentId = $filters['parent_id'] ?? null) {
            if ($parentId === 'null' || $parentId === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'sort_order';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): DocumentFolder
    {
        return DocumentFolder::with(['parent', 'children', 'documents'])
            ->findOrFail($id);
    }

    public function create(array $data): DocumentFolder
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $folder = DocumentFolder::create($data);

        return $folder;
    }

    public function update(DocumentFolder $folder, array $data): DocumentFolder
    {
        $data['updated_by'] = Auth::id();
        $folder->update($data);
        $folder->refresh();

        return $folder;
    }

    public function delete(DocumentFolder $folder): void
    {
        $folder->delete();
    }

    public function restore(int $id): void
    {
        DocumentFolder::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        DocumentFolder::withTrashed()->findOrFail($id)->forceDelete();
    }
}
