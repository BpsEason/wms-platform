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
        // 修正遺漏的 context 欄位，用於記錄交易的額外資訊
        if (!Schema::hasColumn('inventory_transactions', 'context')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                // 將 context 設為 JSON 或 TEXT，且可為空
                $table->json('context')->nullable()->after('type')->comment('額外的交易上下文信息，如訂單ID、操作來源等');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('inventory_transactions', 'context')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropColumn('context');
            });
        }
    }
};
