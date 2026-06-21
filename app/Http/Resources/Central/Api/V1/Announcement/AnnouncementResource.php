<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Announcement;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_active' => $this->is_active,
            'audience_type' => $this->audience_type,
            'audience_ids' => $this->audience_ids,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
