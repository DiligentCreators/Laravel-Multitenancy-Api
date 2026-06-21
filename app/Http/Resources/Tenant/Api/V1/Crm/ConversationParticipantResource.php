<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\ConversationParticipant;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConversationParticipant */
class ConversationParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'id' => $this->id,
            'participant_type' => $resolver->getMorphKey($this->participant_type) ?? $this->participant_type,
            'participant_id' => $this->participant_id,
            'is_primary' => $this->is_primary,
        ];
    }
}
