<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailTemplate */
class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject,
            'body' => $this->body,
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'versions_count' => $this->whenCounted('versions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
