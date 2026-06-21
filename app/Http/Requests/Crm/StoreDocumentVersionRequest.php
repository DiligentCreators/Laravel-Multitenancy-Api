<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\DocumentStorageService;

class StoreDocumentVersionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $storage = app(DocumentStorageService::class);

        if ($this->hasFile('file')) {
            return [
                'file' => [
                    'required',
                    'file',
                    'max:'.($storage->getMaxFileSize() / 1024),
                    'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpeg,jpg,png,gif,webp,svg',
                ],
            ];
        }

        return [
            'file_name' => ['required', 'string', 'max:255'],
            'file_path' => ['required', 'string', 'max:500'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
