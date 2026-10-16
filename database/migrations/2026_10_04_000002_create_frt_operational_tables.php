<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational records are created on the phones (offline) and synced up.
 * Every row is keyed by a device-generated UUID. Money is stored in centavos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('facebook')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->timestamp('client_updated_at');
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('day_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('business_date');
            $table->string('status')->default('open');

            $table->unsignedInteger('rice_rate');
            $table->unsignedInteger('tahop_buy_rate');
            $table->unsignedInteger('tahop_sell_rate');
            $table->unsignedInteger('fuel_price');
            $table->unsignedBigInteger('opening_fund')->default(0);
            $table->decimal('labor_pct', 5, 2);
            $table->decimal('fuel_pct', 5, 2);
            $table->json('operators');

            $table->json('cash_count')->nullable();
            $table->bigInteger('cash_counted')->nullable();
            $table->bigInteger('cash_on_hand')->nullable();
            $table->bigInteger('tahop_in_kind')->nullable();
            $table->bigInteger('total_collected')->nullable();
            $table->bigInteger('total_sales')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();

            $table->timestamp('client_updated_at');
            $table->timestamps();

            $table->index(['business_date', 'device_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_no')->unique();
            $table->date('business_date');
            $table->string('type');
            $table->string('customer_name')->nullable();
            $table->string('location')->nullable();

            $table->json('sacks')->nullable();
            $table->unsignedInteger('sack_count')->default(0);
            $table->decimal('total_kg', 10, 2)->default(0);
            $table->unsignedInteger('rate');
            $table->bigInteger('gross');

            $table->decimal('tahop_kg', 10, 2)->default(0);
            $table->unsignedInteger('tahop_rate')->default(0);
            $table->bigInteger('tahop_offset')->default(0);
            $table->bigInteger('adjustment')->default(0);
            $table->bigInteger('amount_due');
            $table->bigInteger('cash');
            $table->bigInteger('change');

            $table->bigInteger('labor')->default(0);
            $table->bigInteger('fuel')->default(0);
            $table->bigInteger('net')->default(0);

            $table->string('status')->default('valid');
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('recorded_at');

            $table->timestamp('client_updated_at');
            $table->timestamps();

            $table->index(['business_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('day_sessions');
        Schema::dropIfExists('customers');
    }
};
