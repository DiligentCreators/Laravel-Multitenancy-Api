<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreDocumentFolderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:crm_document_folders,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
