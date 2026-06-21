<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_taggables');
    }
};
