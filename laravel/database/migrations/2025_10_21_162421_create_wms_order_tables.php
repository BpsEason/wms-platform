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
        // 供應商 (用於 Inbound Order 驗證)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        // Inbound Orders
        Schema::create('inbound_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique()->comment('外部參考單號');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users'); // 創建人
            $table->string('status')->default('PENDING')->comment('PENDING, RECEIVED, PUTAWAY_COMPLETE');
            $table->date('expected_arrival_date')->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('qty_expected')->comment('預計數量');
            $table->integer('qty_received')->default(0)->comment('實際接收數量');
            $table->timestamps();
            $table->unique(['inbound_order_id', 'product_id']);
        });
        
        // Outbound Orders (通常對應客戶訂單)
        Schema::create('outbound_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique()->comment('客戶訂單編號');
            $table->foreignId('user_id')->constrained('users'); // 創建人
            $table->string('status')->default('PENDING')->comment('PENDING, READY_TO_SHIP, SHIPPED');
            $table->timestamps();
        });

        Schema::create('outbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('qty_requested')->comment('客戶請求數量');
            $table->integer('qty_shipped')->default(0)->comment('實際出貨數量'); // 補齊欄位
            $table->timestamps();
            $table->unique(['outbound_order_id', 'product_id']);
        });
        
        // Picking Orders (揀貨單，連結 Outbound)
        Schema::create('picking_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_id')->unique()->constrained('outbound_orders')->onDelete('cascade'); // 確保唯一性
            $table->foreignId('user_id')->constrained('users')->nullable(); // 揀貨人員 (可為空)
            $table->string('status')->default('PENDING')->comment('PENDING, IN_PROGRESS, PICKED_COMPLETE');
            $table->timestamps();
        });

        Schema::create('picking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_location_id')->constrained('locations');
            $table->integer('qty_to_pick')->comment('預計揀貨數量');
            $table->integer('qty_picked')->default(0)->comment('實際揀貨數量');
            $table->timestamps();
            $table->unique(['picking_order_id', 'product_id', 'source_location_id'], 'picking_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picking_items');
        Schema::dropIfExists('picking_orders');
        Schema::dropIfExists('outbound_items');
        Schema::dropIfExists('outbound_orders');
        Schema::dropIfExists('inbound_items');
        Schema::dropIfExists('inbound_orders');
        Schema::dropIfExists('suppliers');
    }
};
