<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateVersion extends Model
{
    protected $fillable = [
        'email_template_id',
        'version',
        'subject',
        'body',
        'variables',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }
}
