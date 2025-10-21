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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->comment('商品 ID');
            $table->foreignId('location_id')->constrained('locations')->comment('儲位 ID');
            $table->foreignId('user_id')->nullable()->constrained('users')->comment('操作者 ID');
            
            $table->decimal('quantity_change', 10, 2)->comment('異動數量 (正數為入庫, 負數為出庫)');
            $table->decimal('current_quantity', 10, 2)->comment('異動後的當前庫存數量');
            
            $table->string('type', 50)->comment('異動類型 (如: RECEIPT, ISSUE, ADJUST)');
            $table->string('reference_type', 100)->nullable()->comment('參考文件類型 (如: App\Models\Order)');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('參考文件 ID');
            
            $table->text('note')->nullable()->comment('備註');
            $table->timestamps();
            
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
