<?php

namespace App\Services\Crm;

use App\Models\Crm\DocumentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DocumentVersionService
{
    public function __construct(
        private readonly DocumentStorageService $storageService,
    ) {}

    public function query(): Builder
    {
        return DocumentVersion::query()
            ->with(['document', 'uploader'])
            ->orderBy('created_at', 'desc');
    }

    public function paginate(int $documentId, int $perPage = 25)
    {
        return $this->query()
            ->where('document_id', $documentId)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): DocumentVersion
    {
        return DocumentVersion::with(['document', 'uploader'])
            ->findOrFail($id);
    }

    public function delete(DocumentVersion $version): void
    {
        DB::transaction(function () use ($version) {
            $this->storageService->delete($version->file_path, $version->file_size);
            $version->delete();
        });
    }
}
