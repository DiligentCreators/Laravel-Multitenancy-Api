<?php

namespace App\Services\Crm;

use App\Models\Crm\Document;
use App\Models\Crm\DocumentFolder;
use Illuminate\Support\Facades\Auth;

class DocumentActionService
{
    public function lock(Document $document): Document
    {
        $document->update(['is_locked' => true, 'updated_by' => Auth::id()]);
        $document->refresh();

        return $document;
    }

    public function unlock(Document $document): Document
    {
        $document->update(['is_locked' => false, 'updated_by' => Auth::id()]);
        $document->refresh();

        return $document;
    }

    public function move(Document $document, ?int $folderId): Document
    {
        $document->update(['folder_id' => $folderId, 'updated_by' => Auth::id()]);
        $document->refresh();

        return $document;
    }

    public function publish(Document $document): Document
    {
        $document->update(['status' => 'published', 'updated_by' => Auth::id()]);
        $document->refresh();

        return $document;
    }

    public function archive(Document $document): Document
    {
        $document->update(['status' => 'archived', 'updated_by' => Auth::id()]);
        $document->refresh();

        return $document;
    }

    public function moveFolder(DocumentFolder $folder, ?int $parentId): DocumentFolder
    {
        $folder->update(['parent_id' => $parentId, 'updated_by' => Auth::id()]);
        $folder->refresh();

        return $folder;
    }
}
