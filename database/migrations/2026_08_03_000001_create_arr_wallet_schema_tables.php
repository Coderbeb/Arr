<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. platform_settings
        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->boolean('registration_open')->default(true);
                $table->decimal('commission_percent', 5, 2)->default(8.00);
                $table->decimal('max_daily_earning', 10, 2)->default(500.00);
                $table->decimal('max_weekly_earning', 10, 2)->default(2000.00);
                $table->integer('trade_accept_minutes')->default(2);
                $table->integer('payment_timer_minutes')->default(30);
                $table->integer('dispute_proof_minutes')->default(30);
                $table->uuid('updated_by')->nullable();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            });

            DB::table('platform_settings')->insert([
                'registration_open' => true,
                'commission_percent' => 8.00,
                'max_daily_earning' => 500.00,
                'max_weekly_earning' => 2000.00,
                'trade_accept_minutes' => 2,
                'payment_timer_minutes' => 30,
                'dispute_proof_minutes' => 30,
            ]);
        }

        // 2. trade_amounts
        if (!Schema::hasTable('trade_amounts')) {
            Schema::create('trade_amounts', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->decimal('amount', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->uuid('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });

            DB::table('trade_amounts')->insert([
                ['amount' => 1000.00, 'sort_order' => 1],
                ['amount' => 1200.00, 'sort_order' => 2],
                ['amount' => 1500.00, 'sort_order' => 3],
                ['amount' => 1800.00, 'sort_order' => 4],
                ['amount' => 2000.00, 'sort_order' => 5],
            ]);
        }

        // 3. orders
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('seller_id');
                $table->decimal('amount', 10, 2);
                $table->decimal('coin_amount', 10, 2);
                $table->decimal('commission_pct', 5, 2);
                $table->decimal('commission_amt', 10, 2);
                $table->string('seller_upi_id', 100);
                $table->string('seller_upi_app', 20);
                $table->text('seller_qr_url')->nullable();
                $table->string('status', 20)->default('open');
                $table->boolean('cancel_requested')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at');
                $table->timestamp('matched_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('status');
                $table->index('seller_id');
                $table->index('created_at');
            });
        }

        // 4. trades
        if (!Schema::hasTable('trades')) {
            Schema::create('trades', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('order_id');
                $table->uuid('buyer_id');
                $table->uuid('seller_id');
                $table->decimal('amount', 10, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->string('buyer_upi_app', 20)->nullable();
                $table->string('utr_number', 50)->nullable();
                $table->text('payment_screenshot_url')->nullable();
                $table->text('buyer_payment_screenshot_url')->nullable();
                $table->string('status', 30)->default('pending_payment');
                $table->timestamp('matched_at')->useCurrent();
                $table->timestamp('payment_deadline');
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('cancelled_reason')->nullable();

                $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
                $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');

                $table->index('buyer_id');
                $table->index('seller_id');
                $table->index('status');
                $table->index('order_id');
            });
        }

        // 5. disputes
        if (!Schema::hasTable('disputes')) {
            Schema::create('disputes', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('trade_id');
                $table->uuid('raised_by');
                $table->string('status', 30)->default('pending');

                $table->text('buyer_screenshot_url')->nullable();
                $table->text('buyer_screen_recording_url')->nullable();
                $table->text('buyer_bank_statement_url')->nullable();
                $table->string('buyer_utr_number', 50)->nullable();
                $table->text('buyer_upi_screenshot_url')->nullable();
                $table->integer('buyer_ai_score')->nullable();
                $table->jsonb('buyer_ai_breakdown')->nullable();
                $table->jsonb('buyer_proof_analysis')->nullable();
                $table->timestamp('buyer_proof_submitted_at')->nullable();

                $table->text('seller_screen_recording_url')->nullable();
                $table->text('seller_txn_screenshot_url')->nullable();
                $table->text('seller_profile_recording_url')->nullable();
                $table->integer('seller_ai_score')->nullable();
                $table->jsonb('seller_ai_breakdown')->nullable();
                $table->jsonb('seller_proof_analysis')->nullable();
                $table->timestamp('seller_proof_submitted_at')->nullable();

                $table->string('ai_recommendation', 30)->nullable();
                $table->integer('ai_confidence')->nullable();

                $table->uuid('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('proof_deadline');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('trade_id')->references('id')->on('trades')->onDelete('cascade');
                $table->foreign('raised_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

                $table->index('status');
                $table->index('trade_id');
            });
        }

        // 6. wallet_transactions
        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('user_id');
                $table->uuid('trade_id')->nullable();
                $table->string('type', 30);
                $table->decimal('amount', 10, 2);
                $table->decimal('balance_before', 14, 2);
                $table->decimal('balance_after', 14, 2);
                $table->text('description_en')->nullable();
                $table->text('description_hi')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('trade_id')->references('id')->on('trades')->onDelete('set null');

                $table->index('user_id');
                $table->index('created_at');
            });
        }

        // 7. bonus_milestones
        if (!Schema::hasTable('bonus_milestones')) {
            Schema::create('bonus_milestones', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->integer('trade_count')->unique();
                $table->decimal('bonus_amount', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });

            DB::table('bonus_milestones')->insert([
                ['trade_count' => 10, 'bonus_amount' => 50.00],
                ['trade_count' => 50, 'bonus_amount' => 200.00],
                ['trade_count' => 100, 'bonus_amount' => 500.00],
                ['trade_count' => 250, 'bonus_amount' => 1000.00],
            ]);
        }

        // 8. user_bonuses_claimed
        if (!Schema::hasTable('user_bonuses_claimed')) {
            Schema::create('user_bonuses_claimed', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('user_id');
                $table->uuid('milestone_id');
                $table->timestamp('claimed_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('milestone_id')->references('id')->on('bonus_milestones')->onDelete('cascade');
                $table->unique(['user_id', 'milestone_id']);
            });
        }

        // 9. utr_registry
        if (!Schema::hasTable('utr_registry')) {
            Schema::create('utr_registry', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->string('utr_number', 50)->unique();
                $table->uuid('trade_id');
                $table->uuid('user_id');
                $table->timestamp('used_at')->useCurrent();

                $table->foreign('trade_id')->references('id')->on('trades')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                $table->index('utr_number');
            });
        }

        // 10. earnings_tracker
        if (!Schema::hasTable('earnings_tracker')) {
            Schema::create('earnings_tracker', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('user_id');
                $table->date('date');
                $table->decimal('daily_earned', 10, 2)->default(0.00);
                $table->decimal('weekly_earned', 10, 2)->default(0.00);
                $table->date('week_start');

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['user_id', 'date']);
                $table->index(['user_id', 'date']);
            });
        }

        // 11. notifications
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('user_id');
                $table->string('type', 50);
                $table->text('title_en');
                $table->text('title_hi');
                $table->text('body_en');
                $table->text('body_hi');
                $table->boolean('is_read')->default(false);
                $table->uuid('trade_id')->nullable();
                $table->uuid('dispute_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('trade_id')->references('id')->on('trades')->onDelete('set null');
                $table->foreign('dispute_id')->references('id')->on('disputes')->onDelete('set null');

                $table->index(['user_id', 'is_read']);
            });
        }

        // 12. admin_audit_log
        if (!Schema::hasTable('admin_audit_log')) {
            Schema::create('admin_audit_log', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('admin_id');
                $table->string('action', 100);
                $table->string('target_type', 50)->nullable();
                $table->uuid('target_id')->nullable();
                $table->text('notes')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');

                $table->index('admin_id');
                $table->index('created_at');
            });
        }

        // 13. fraud_hashes
        if (!Schema::hasTable('fraud_hashes')) {
            Schema::create('fraud_hashes', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->string('file_hash', 64)->unique();
                $table->text('reason')->nullable();
                $table->uuid('flagged_by')->nullable();
                $table->uuid('dispute_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('dispute_id')->references('id')->on('disputes')->onDelete('set null');
            });
        }

        // 14. proof_files
        if (!Schema::hasTable('proof_files')) {
            Schema::create('proof_files', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('dispute_id')->nullable();
                $table->uuid('trade_id')->nullable();
                $table->uuid('uploaded_by');
                $table->string('file_type', 20);
                $table->text('file_url');
                $table->string('file_hash', 64)->nullable();
                $table->integer('file_size')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->jsonb('analysis')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('dispute_id')->references('id')->on('disputes')->onDelete('set null');
                $table->foreign('trade_id')->references('id')->on('trades')->onDelete('set null');
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proof_files');
        Schema::dropIfExists('fraud_hashes');
        Schema::dropIfExists('admin_audit_log');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('earnings_tracker');
        Schema::dropIfExists('utr_registry');
        Schema::dropIfExists('user_bonuses_claimed');
        Schema::dropIfExists('bonus_milestones');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('trades');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('trade_amounts');
        Schema::dropIfExists('platform_settings');
    }
};
