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
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
            $table->decimal('buy_commission_percent', 5, 2)->default(8.00)->after('registration_open');
            $table->decimal('sell_commission_percent', 5, 2)->default(8.00)->after('buy_commission_percent');
            $table->boolean('trade_suspended')->default(false)->after('global_announcement');
            $table->string('trade_suspended_message')->nullable()->after('trade_suspended');
            $table->string('allowed_trade_amounts')->nullable()->after('trade_suspended_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(8.00);
            $table->dropColumn([
                'buy_commission_percent',
                'sell_commission_percent',
                'trade_suspended',
                'trade_suspended_message',
                'allowed_trade_amounts'
            ]);
        });
    }
};
