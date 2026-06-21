<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'data')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('data')->nullable()->after('paid_at');
            });
        }

        if (! Schema::hasColumn('invoices', 'data')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->json('data')->nullable()->after('paid_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('data');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
