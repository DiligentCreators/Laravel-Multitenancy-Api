<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'description' => $this->description,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'extension' => $this->extension,
            'version' => $this->version,
            'status' => $this->status?->value,
            'is_locked' => $this->is_locked,
            'expires_at' => $this->expires_at,
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'folder' => new DocumentFolderResource($this->whenLoaded('folder')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'shares' => DocumentShareResource::collection($this->whenLoaded('shares')),
            'versions_count' => $this->whenCounted('versions'),
        ];
    }
}
