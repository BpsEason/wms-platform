<?php
// Controller: app/Modules/Inventory/Http/Controllers/InventoryController.php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 查詢庫存列表 (需要 inventory-query 權限)
     */
    public function index(Request $request)
    {
        // 額外的 tokenCan 檢查可以放在這裡，增加安全性 (雖然 middleware 已經檢查)
        if (! $request->user()->tokenCan('inventory-query')) {
            return response()->json(['message' => 'Forbidden: Missing inventory-query ability'], 403);
        }

        $inventory = $this->inventoryService->listAllInventory($request->query('per_page', 15));

        return response()->json($inventory);
    }

    /**
     * 調整庫存數量 (需要 inventory-adjust 權限)
     */
    public function adjust(Request $request)
    {
        // 額外的 tokenCan 檢查可以放在這裡，增加安全性 (雖然 middleware 已經檢查)
        if (! $request->user()->tokenCan('inventory-adjust')) {
            return response()->json(['message' => 'Forbidden: Missing inventory-adjust ability'], 403);
        }
        
        $validated = $request->validate([
            'sku' => 'required|string|max:50',
            'location_code' => 'required|string|max:50',
            'quantity_change' => 'required|numeric|not_in:0', // 數量變動，不可為零
        ]);

        try {
            // 傳遞操作者 ID 給 Service
            $inventory = $this->inventoryService->adjustInventory(
                $validated['sku'],
                $validated['location_code'],
                $validated['quantity_change'],
                $request->user()->id // 獲取當前登入用戶 ID
            );

            $action = $validated['quantity_change'] > 0 ? '入庫' : '出庫';

            return response()->json([
                'message' => "庫存 {$action} 調整成功。",
                'inventory' => $inventory,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            // 捕捉其他可能的錯誤，如資料庫錯誤
             return response()->json(['message' => '系統錯誤: ' . $e->getMessage()], 500);
        }
    }
}
