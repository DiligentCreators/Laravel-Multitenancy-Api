<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class MoveDocumentFolderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:crm_document_folders,id'],
        ];
    }
}
