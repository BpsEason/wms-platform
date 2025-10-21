<?php
// Controller: laravel/app/Modules/Inventory/Http/Controllers/PickingController.php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OutboundOrder;
use App\Models\PickingOrder;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PickingController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 創建 Picking Order (揀貨單) (Ability: picking-create)
     * (實際應包含複雜的庫存分配邏輯，這裡僅創建空單)
     */
    public function store(Request $request)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('picking-create')) {
             return response()->json(['message' => '無權限創建揀貨單'], 403);
        }
        
        $validated = $request->validate([
            'outbound_id' => 'required|exists:outbound_orders,id|unique:picking_orders,outbound_id',
        ]);

        $outbound = OutboundOrder::findOrFail($validated['outbound_id']);
        
        // 假設從 Outbound Order 創建 Picking Order
        return DB::transaction(function () use ($outbound, $request) {
            
            $picking = PickingOrder::create([
                'outbound_id' => $outbound->id,
                'user_id' => $request->user()->id, // 假設創建人為當前用戶
                'status' => 'PENDING'
            ]);
            
            // 簡化邏輯：為每個 Outbound Item 創建一個 Picking Item
            // 實際應從 Inventory 查詢並分配儲位，這裡用虛擬儲位 1
            foreach ($outbound->items as $outboundItem) {
                $picking->items()->create([
                    'product_id' => $outboundItem->product_id,
                    'source_location_id' => 1, // 假設虛擬儲位 1 作為出庫儲位
                    'qty_to_pick' => $outboundItem->qty_requested,
                    'qty_picked' => 0,
                ]);
            }
            
            $outbound->status = 'READY_TO_SHIP'; // 標記為準備出貨
            $outbound->save();

            return response()->json(['message' => '揀貨單創建成功', 'data' => $picking->load(['outbound', 'items'])], 201);
        });
    }

    /**
     * 執行 Picking Scan (揀貨確認) 操作 (Ability: picking-scan)
     * 涉及庫存變動，為核心業務 (從庫存扣減)。
     */
    public function scan(Request $request, PickingOrder $picking)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('picking-scan')) {
             return response()->json(['message' => '無權限執行揀貨掃描'], 403);
        }
        
        $validated = $request->validate([
            'item_id' => 'required|exists:picking_items,id',
            'qty_picked' => 'required|integer|min:1',
        ]);

        $item = $picking->items()->findOrFail($validated['item_id']);

        if ($item->qty_picked + $validated['qty_picked'] > $item->qty_to_pick) {
             return response()->json(['message' => '揀貨數量超過應揀數量'], 400);
        }
        
        try {
            // V2: 使用修正後的 changeStock 服務方法
            // 庫存扣減：數量為負數
            $inventory = $this->inventoryService->changeStock(
                $item->product_id,
                $item->source_location_id,
                (float) -$validated['qty_picked'], // 負數：扣庫存
                $request->user()->id,
                'ISSUE', // 交易類型：出庫/發貨
                ['picking_id' => $picking->id, 'picking_item_id' => $item->id, 'outbound_id' => $picking->outbound_id]
            );

            // 更新 PickingItem 實際揀貨數量
            $item->qty_picked += $validated['qty_picked'];
            $item->save();

            // 檢查是否所有項目都已完成揀貨，然後更新 PickingOrder 狀態 (簡化邏輯)
            if ($picking->items()->whereColumn('qty_picked', '<', 'qty_to_pick')->count() === 0) {
                 $picking->status = 'PICKED_COMPLETE';
                 $picking->save();
            }

            return response()->json([
                'message' => '揀貨掃描成功，庫存已扣減',
                'inventory' => $inventory->load(['product', 'location'])
            ]);

        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
