<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'commentable_type' => $this->commentable_type,
            'commentable_id' => $this->commentable_id,
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
