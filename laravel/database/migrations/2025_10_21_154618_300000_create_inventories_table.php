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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->comment('商品 ID');
            $table->foreignId('location_id')->constrained('locations')->comment('儲位 ID');
            $table->decimal('quantity', 10, 2)->default(0.00)->comment('當前庫存數量');
            $table->timestamps();

            // 確保同一個儲位和同一個商品只有一筆庫存紀錄
            $table->unique(['product_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
