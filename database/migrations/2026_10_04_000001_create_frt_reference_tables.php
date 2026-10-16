<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 8)->unique();
            $table->string('name')->nullable();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('personnel');
        Schema::dropIfExists('settings');
    }
};
