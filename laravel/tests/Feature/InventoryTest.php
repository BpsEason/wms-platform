<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Location;
use App\Models\InventoryTransaction;
// use App\Models\StockLevel; // 替換為 inventories 庫存表檢查
use App\Models\InboundOrder; // 模擬 WMS 流程所需
use App\Models\InboundItem;
use App\Models\OutboundOrder;
use App\Models\OutboundItem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 測試 WMS 核心庫存流程的整合性。
 * 模擬從主檔創建、入庫、上架、到出貨的全流程。
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase; // 確保每次測試都使用乾淨的資料庫

    protected $adminUser;
    protected $productData;
    protected $locationData;

    /**
     * 在每次測試運行前設定環境。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. 建立一個具有所有必要權限的 Admin 用戶
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        // 假設所有的 WMS 流程都需要這些權限
        $this->adminUser->abilities()->sync([
            'system-admin',
            'inventory-query',
            'inventory-adjust',
        ]);

        // **認證修改：移除 /api/auth/login 呼叫，改用 Laravel 內建的 actingAs 模擬登入狀態**
        $this->actingAs($this->adminUser, 'sanctum');

        // 3. 預設測試資料
        $this->productData = [
            'sku' => 'PROD-A' . Str::random(3),
            'name' => '測試商品 A',
            'unit' => 'EA',
        ];

        $this->locationData = [
            'code' => 'LOC-A' . Str::random(3),
            'name' => '儲位 A',
            'is_active' => true,
        ];
    }

    /**
     * @test
     * 測試從創建主檔到出貨的完整 WMS 流程。
     */
    public function test_full_wms_inventory_flow()
    {
        $quantity = 100;
        $outbound_qty = 30;
        $user = $this->adminUser; // 當前操作用戶

        // =================================================================
        // 1. 創建主檔 (Product & Location) - 使用 Factory 建立
        // =================================================================

        // 創建 Product (無需 API 呼叫)
        $product = Product::factory()->create($this->productData);
        $productId = $product->id;

        // 創建 Location (無需 API 呼叫)
        $location = Location::factory()->create($this->locationData);
        $locationId = $location->id;

        // 確認主檔已建立 (保留斷言)
        $this->assertDatabaseHas('products', ['id' => $productId, 'sku' => $this->productData['sku']]);
        $this->assertDatabaseHas('locations', ['id' => $locationId, 'code' => $this->locationData['code']]);

        // =================================================================
        // 2. 入庫流程 (Receive -> Putaway) - 使用 WMS 路由和 Order 資料
        // =================================================================

        // 步驟 2a: 創建入庫單 (InboundOrder) 和項目 (InboundItem)
        $inboundOrder = InboundOrder::factory()->create(['status' => 'RECEIVED']);
        $inboundItem = InboundItem::factory()->create([
            'inbound_order_id' => $inboundOrder->id,
            'product_id' => $productId,
            'received_quantity' => $quantity,
            'putaway_quantity' => 0, // 尚未上架
        ]);
        
        // 假設 RECEIVE 交易已在 InboundItem 狀態變為 RECEIVED 時產生
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $productId,
            'location_id' => null, // 尚未上架
            'type' => 'RECEIVE',
            'quantity_change' => $quantity, // 交易量為正
        ]);

        // 步驟 2b: 執行上架 (Putaway) - 路由變更: /api/wms/inbound/{inbound}/putaway
        $this->postJson("/api/wms/inbound/{$inboundOrder->id}/putaway", [
            'item_id' => $inboundItem->id, // 傳遞要上架的 Inbound Item ID
            'location_id' => $locationId,
            'quantity' => $quantity,
            'user_id' => $user->id,
        ])
        ->assertStatus(200)
        ->assertJson(['message' => '庫存已成功上架。']);

        // 確認交易記錄 (PUTAWAY 類型)
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => 'PUTAWAY',
            'quantity_change' => $quantity, // 欄位名稱修正，數量為正
        ]);

        // 確認 Inventories 庫存 (現有庫存量應為 $quantity) - 替換 stock_levels
        $this->assertDatabaseHas('inventories', [
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $quantity, // inventories 表使用 quantity 欄位
        ]);

        // =================================================================
        // 3. 出庫流程 (Picking -> Shipment) - 使用 WMS 路由和 Order 資料
        // =================================================================
        $initialInventory = $quantity;
        
        // 步驟 3a: 創建出庫單 (OutboundOrder) 和項目 (OutboundItem)
        $outboundOrder = OutboundOrder::factory()->create(['status' => 'PICKING_ALLOCATED']);
        $outboundItem = OutboundItem::factory()->create([
            'outbound_order_id' => $outboundOrder->id,
            'product_id' => $productId,
            'allocated_quantity' => $outbound_qty,
            'picked_quantity' => 0,
        ]);
        
        // 步驟 3b: 執行揀貨掃描 (Scan Pick/Shipment) - 路由變更: /api/wms/picking/{picking}/scan
        // 假設此端點完成了揀貨、庫存扣減並產生 SHIPMENT 交易
        $this->postJson("/api/wms/picking/{$outboundOrder->id}/scan", [
            'item_id' => $outboundItem->id, 
            'location_id' => $locationId,
            'scanned_quantity' => $outbound_qty,
            'user_id' => $user->id,
        ])
        ->assertStatus(200)
        ->assertJson(['message' => '揀貨掃描完成，庫存已扣除。']);

        // =================================================================
        // 4. 最終斷言 (Final Assertions)
        // =================================================================

        $finalInventory = $initialInventory - $outbound_qty;

        // 4.1. 確認 Inventories 已經扣除出貨數量
        $this->assertDatabaseHas('inventories', [
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $finalInventory,
        ]);

        // 4.2. 確認 Inventories 記錄數量
        $this->assertDatabaseCount('inventories', 1);

        // 4.3. 確認 InventoryTransaction 記錄 (共 3 筆: RECEIVE, PUTAWAY, SHIPMENT)
        $this->assertDatabaseCount('inventory_transactions', 3);

        // 4.4. 確認 SHIPMENT 交易記錄 (數量為負數)
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => 'SHIPMENT',
            'quantity_change' => -$outbound_qty, // 欄位名稱修正，數量為負
        ]);
    }
}
