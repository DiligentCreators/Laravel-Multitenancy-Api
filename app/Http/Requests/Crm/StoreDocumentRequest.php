<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\DocumentStorageService;

class StoreDocumentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $storage = app(DocumentStorageService::class);

        $rules = [
            'folder_id' => ['nullable', 'integer', 'exists:crm_document_folders,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'is_locked' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'documentable_type' => ['nullable', 'string', 'max:255'],
            'documentable_id' => ['nullable', 'integer', 'min:1'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($this->hasFile('file')) {
            $rules['file'] = [
                'required',
                'file',
                'max:'.($storage->getMaxFileSize() / 1024),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpeg,jpg,png,gif,webp,svg',
            ];

            return $rules;
        }

        $rules['file_name'] = ['required', 'string', 'max:255'];
        $rules['file_path'] = ['required', 'string', 'max:500'];
        $rules['mime_type'] = ['required', 'string', 'max:100'];
        $rules['file_size'] = ['nullable', 'integer', 'min:0'];
        $rules['extension'] = ['nullable', 'string', 'max:20'];

        return $rules;
    }
}
