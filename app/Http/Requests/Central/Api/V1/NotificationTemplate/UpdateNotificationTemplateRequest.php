<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\NotificationTemplate;

use App\Enums\Central\NotificationChannelEnum;
use App\Http\Requests\BaseFormRequest;

class UpdateNotificationTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:notification_templates,slug,'.$this->route('notification_template')?->id],
            'channel' => ['nullable', 'string', 'in:'.implode(',', NotificationChannelEnum::values())],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
