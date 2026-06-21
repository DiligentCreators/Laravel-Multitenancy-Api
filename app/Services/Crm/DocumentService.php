<?php

namespace App\Services\Crm;

use App\Models\Crm\Document;
use App\Models\Crm\DocumentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'folder_id', 'name', 'file_name', 'mime_type',
        'file_size', 'extension', 'version', 'status', 'is_locked',
        'expires_at', 'owner_id', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly DocumentStorageService $storageService,
    ) {}

    public function query(): Builder
    {
        return Document::query()
            ->with(['folder', 'versions'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Document::search($search)->keys();
            $query->whereIn((new Document)->getQualifiedKeyName(), $ids);
        }

        if ($folderId = $filters['folder_id'] ?? null) {
            $query->where('folder_id', $folderId);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($mimeType = $filters['mime_type'] ?? null) {
            $query->where('mime_type', 'like', "{$mimeType}%");
        }

        if ($extension = $filters['extension'] ?? null) {
            $query->where('extension', $extension);
        }

        if ($isLocked = $filters['is_locked'] ?? null) {
            $query->where('is_locked', filter_var($isLocked, FILTER_VALIDATE_BOOLEAN));
        }

        if ($documentableType = $filters['documentable_type'] ?? null) {
            $query->where('documentable_type', $documentableType);
            if ($documentableId = $filters['documentable_id'] ?? null) {
                $query->where('documentable_id', $documentableId);
            }
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        if ($fromDate = $filters['from_date'] ?? null) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate = $filters['to_date'] ?? null) {
            $query->where('created_at', '<=', $toDate);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Document
    {
        return Document::with(['folder', 'versions', 'shares', 'documentable'])
            ->findOrFail($id);
    }

    public function recordDownload(Document $document): void
    {
        $this->eventDispatcher->record($document, 'document.downloaded', 'Document Downloaded', $document->name, [
            'file_name' => $document->file_name,
            'file_size' => $document->file_size,
            'mime_type' => $document->mime_type,
        ], Auth::id());
    }

    public function recordVersionDownload(Document $document, DocumentVersion $version): void
    {
        $this->eventDispatcher->record($document, 'document.version_downloaded', 'Document Version Downloaded', "v{$version->version}: {$document->name}", [
            'version' => $version->version,
            'file_name' => $version->file_name,
            'file_size' => $version->file_size,
            'mime_type' => $version->mime_type,
        ], Auth::id());
    }

    public function create(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            $file = $data['file'] ?? null;

            if ($file instanceof UploadedFile) {
                $storageData = $this->storageService->store($file, tenant()->id);
                unset($data['file']);
                $data = array_merge($data, $storageData);
            }

            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $document = Document::create($data);

            $this->eventDispatcher->record($document, 'document.created', 'Document Created', $document->name, [
                'folder_id' => $document->folder_id,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
            ], Auth::id());

            return $document;
        });
    }

    public function update(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $data['updated_by'] = Auth::id();
            $document->update($data);
            $document->refresh();

            $this->eventDispatcher->record($document, 'document.updated', 'Document Updated', $document->name, null, Auth::id());

            return $document;
        });
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $this->eventDispatcher->record($document, 'document.deleted', 'Document Deleted', $document->name, null, Auth::id());
            $this->storageService->delete($document->file_path, $document->file_size);
            $document->delete();
        });
    }

    public function restore(int $id): void
    {
        Document::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $document = Document::withTrashed()->findOrFail($id);

            foreach ($document->versions as $version) {
                $this->storageService->delete($version->file_path, $version->file_size);
                $version->forceDelete();
            }

            $this->storageService->delete($document->file_path, $document->file_size);

            $document->forceDelete();
        });
    }

    public function createVersion(Document $document, array $data): DocumentVersion
    {
        return DB::transaction(function () use ($document, $data) {
            $file = $data['file'] ?? null;

            if ($file instanceof UploadedFile) {
                $storageData = $this->storageService->store($file, tenant()->id);
                unset($data['file']);
                $data = array_merge($data, $storageData);
            }

            $latestVersion = $document->versions()->max('version') ?? $document->version;
            $nextVersion = $this->bumpVersion($latestVersion);

            $data['document_id'] = $document->id;
            $data['version'] = $nextVersion;
            $data['uploaded_by'] = Auth::id();

            $version = DocumentVersion::create($data);

            $document->update([
                'version' => $nextVersion,
                'file_name' => $data['file_name'],
                'file_path' => $data['file_path'],
                'mime_type' => $data['mime_type'],
                'file_size' => $data['file_size'],
                'updated_by' => Auth::id(),
            ]);

            $this->eventDispatcher->record($document, 'document.version_created', 'Document Version Created', "v{$nextVersion}: {$document->name}", [
                'version' => $nextVersion,
                'file_size' => $data['file_size'],
            ], Auth::id());

            return $version;
        });
    }

    private function bumpVersion(string $current): string
    {
        $parts = explode('.', $current);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);

        return $major.'.'.($minor + 1);
    }
}
