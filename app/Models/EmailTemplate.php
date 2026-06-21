<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\EmailTemplateObserver;
use App\Policies\EmailTemplatePolicy;
use Database\Factories\Central\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[UseFactory(EmailTemplateFactory::class)]
#[ObservedBy(EmailTemplateObserver::class)]
#[UsePolicy(EmailTemplatePolicy::class)]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject,
        ];
    }
}
