<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reopening a closed day needs the owner's approval code on the phone; keep who, when, why,
     * how many times, and the variance before the latest reopen.
     */
    public function up(): void
    {
        Schema::table('day_sessions', function (Blueprint $table) {
            $table->unsignedInteger('reopen_count')->default(0)->after('closed_at');
            $table->timestamp('reopened_at')->nullable()->after('reopen_count');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable()->after('reopened_by');
            $table->bigInteger('reopen_previous_variance')->nullable()->after('reopen_reason');
        });
    }

    public function down(): void
    {
        Schema::table('day_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopen_count', 'reopened_at', 'reopen_reason', 'reopen_previous_variance']);
        });
    }
};
