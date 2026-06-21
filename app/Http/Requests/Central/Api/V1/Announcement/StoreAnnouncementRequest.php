<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Announcement;

use App\Http\Requests\BaseFormRequest;

class StoreAnnouncementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'audience_type' => ['nullable', 'string', 'in:all,plan,tenant'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['required', 'string'],
        ];
    }
}
