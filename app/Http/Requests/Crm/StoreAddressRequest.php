<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crm\Address;
use App\Services\Crm\MorphableEntityResolver;

class StoreAddressRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'type' => ['required', 'string', 'in:'.implode(',', Address::TYPES)],
            'addressable_type' => $resolver->getValidationRule(),
            'addressable_id' => ['required', 'integer'],
            'country' => ['nullable', 'string', 'max:2'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
