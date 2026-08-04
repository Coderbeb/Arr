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
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('mobile_number', 15)->unique();
                $table->string('email', 255)->unique()->nullable();
                $table->string('full_name', 100);
                $table->date('date_of_birth');
                $table->string('password_hash', 255);
                $table->string('upi_id', 100)->nullable();
                $table->string('upi_app', 20)->nullable();
                $table->text('upi_qr_image_url')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('language', 10)->default('en');
                $table->string('role', 20)->default('user');
                $table->string('status', 20)->default('active');
                $table->decimal('wallet_balance', 14, 2)->default(0.00);
                $table->decimal('escrow_balance', 14, 2)->default(0.00);
                $table->integer('total_trades')->default(0);
                $table->integer('reputation_score')->default(100);
                $table->integer('strike_count')->default(0);
                $table->boolean('is_verified')->default(false);
                $table->integer('failed_dob_attempts')->default(0);
                $table->timestamp('dob_lockout_until')->nullable();
                $table->integer('consecutive_cancels')->default(0);
                $table->timestamp('buy_ban_until')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('last_login')->nullable();

                $table->index('mobile_number');
                $table->index('role');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
