<?php

namespace App\Services\Crm;

use App\Models\Crm\Document;
use App\Models\Crm\DocumentShare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentShareService
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return DocumentShare::query()
            ->with(['document'])
            ->orderBy('created_at', 'desc');
    }

    public function paginate(int $documentId, int $perPage = 25)
    {
        return $this->query()
            ->where('document_id', $documentId)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): DocumentShare
    {
        return DocumentShare::with(['document', 'creator'])
            ->findOrFail($id);
    }

    public function create(Document $document, array $data): DocumentShare
    {
        return DB::transaction(function () use ($document, $data) {
            $data['document_id'] = $document->id;
            $data['share_token'] = $data['share_token'] ?? (string) Str::uuid();
            $data['created_by'] = Auth::id();

            if (! empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
                $data['password_protected'] = true;
            }

            $share = DocumentShare::create($data);

            $this->eventDispatcher->record($document, 'document.shared', 'Document Shared', $document->name, [
                'share_token' => $share->share_token,
                'expires_at' => $share->expires_at,
            ], Auth::id());

            return $share;
        });
    }

    public function delete(DocumentShare $share): void
    {
        $share->delete();
    }

    public function access(DocumentShare $share, ?string $password = null): ?Document
    {
        if ($share->expires_at && $share->expires_at->isPast()) {
            return null;
        }

        if ($share->password_protected) {
            if (! $password || ! password_verify($password, $share->password)) {
                return null;
            }
        }

        DB::transaction(function () use ($share) {
            $share->increment('access_count');
            $share->update(['last_accessed_at' => now()]);
        });

        return $share->document;
    }

    public function findByToken(string $token): ?DocumentShare
    {
        return DocumentShare::where('share_token', $token)->first();
    }
}
