<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class MoveDocumentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:crm_document_folders,id'],
        ];
    }
}
