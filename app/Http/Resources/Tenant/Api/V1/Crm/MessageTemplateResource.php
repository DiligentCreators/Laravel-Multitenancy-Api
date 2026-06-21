<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MessageTemplate */
class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'category' => $this->category,
            'body' => $this->body,
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
