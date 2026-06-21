<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->integer('version');
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['email_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_template_versions');
    }
};
