<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\SmsTemplatePolicy;
use Database\Factories\Central\SmsTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

#[UseFactory(SmsTemplateFactory::class)]
#[UsePolicy(SmsTemplatePolicy::class)]
class SmsTemplate extends Model
{
    /** @use HasFactory<SmsTemplateFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'message',
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

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
