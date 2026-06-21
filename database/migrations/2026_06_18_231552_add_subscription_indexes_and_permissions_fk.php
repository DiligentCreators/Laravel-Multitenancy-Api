<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'ends_at'], 'subscriptions_tenant_status_ends_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('ends_at');
            $table->timestamp('cancelled_at')->nullable()->after('trial_ends_at');
            $table->timestamp('suspended_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_tenant_status_ends_index');
            $table->dropColumn(['trial_ends_at', 'cancelled_at', 'suspended_at']);
        });
    }
};
