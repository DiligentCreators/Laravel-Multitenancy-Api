<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\NotificationTemplate;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationTemplate */
class ListNotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'channel' => $this->channel,
            'title' => $this->title,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
