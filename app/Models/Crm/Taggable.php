<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Taggable extends MorphPivot
{
    protected $table = 'crm_taggables';

    protected $fillable = [
        'tag_id',
        'taggable_type',
        'taggable_id',
    ];
}
