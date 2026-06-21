<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\SmsTemplate;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SmsTemplate */
class ListSmsTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
