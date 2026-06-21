<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\NotificationTemplate;

use App\Enums\Central\NotificationChannelEnum;
use App\Http\Requests\BaseFormRequest;

class StoreNotificationTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:notification_templates,slug'],
            'channel' => ['required', 'string', 'in:'.implode(',', NotificationChannelEnum::values())],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
