<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateDocumentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:crm_document_folders,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['sometimes', 'required', 'string', 'max:255'],
            'file_path' => ['sometimes', 'required', 'string', 'max:500'],
            'mime_type' => ['sometimes', 'required', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'extension' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'is_locked' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'documentable_type' => ['nullable', 'string', 'max:255'],
            'documentable_id' => ['nullable', 'integer', 'min:1'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
