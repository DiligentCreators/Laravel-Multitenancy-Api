<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\NotificationTemplate;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationTemplate */
class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'channel' => $this->channel,
            'title' => $this->title,
            'message' => $this->message,
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
