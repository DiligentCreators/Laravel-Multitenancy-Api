<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\DocumentShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentShare */
class DocumentShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'share_token' => $this->share_token,
            'expires_at' => $this->expires_at,
            'password_protected' => $this->password_protected,
            'access_count' => $this->access_count,
            'last_accessed_at' => $this->last_accessed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
