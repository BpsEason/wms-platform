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
        Schema::table('users', function (Blueprint $table) {
            // 用於儲存額外的權限字串列表 (例如: 'inventory-query', 'system-admin')
            $table->json('abilities')->nullable()->after('email')->comment('用戶擁有的額外權限列表 (JSON 格式)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('abilities');
        });
    }
};
