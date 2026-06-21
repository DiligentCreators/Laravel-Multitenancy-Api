<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends BaseFormRequest
{
    public function rules(): array
    {
        /** @var MorphableEntityResolver $resolver */
        $resolver = app(MorphableEntityResolver::class);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:scheduled,confirmed,cancelled'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
            'eventable_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($resolver::ALLOWED_TYPES))],
            'eventable_id' => ['required_with:eventable_type', 'integer', 'min:1'],
            'recurring' => ['nullable', 'array'],
            'recurring.frequency' => ['required_with:recurring', 'string', 'in:daily,weekly,monthly,yearly'],
            'recurring.interval' => ['nullable', 'integer', 'min:1'],
            'recurring.ends_at' => ['nullable', 'date'],
            'recurring.occurrences_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
