<?php
// Controller: laravel/app/Modules/Inventory/Http/Controllers/OutboundController.php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OutboundOrder;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutboundController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 創建 Outbound Order (出庫單/客戶訂單) (Ability: outbound-create)
     */
    public function store(Request $request)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('outbound-create')) {
             return response()->json(['message' => '無權限創建出庫單'], 403);
        }

        $validated = $request->validate([
            'reference_no' => 'required|string|unique:outbound_orders,reference_no',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_requested' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $outbound = OutboundOrder::create([
                'reference_no' => $validated['reference_no'],
                'user_id' => $request->user()->id,
                'status' => 'PENDING',
            ]);

            foreach ($validated['items'] as $item) {
                $outbound->items()->create([
                    'product_id' => $item['product_id'],
                    'qty_requested' => $item['qty_requested'],
                    // qty_shipped 預設為 0
                ]);
            }

            return response()->json(['message' => '出庫單創建成功', 'data' => $outbound->load('items')], 201);
        });
    }

    /**
     * 執行 Ship (出貨/結單) 操作 (Ability: outbound-ship)
     * 僅在揀貨完成後執行，更新 OutboundItem 的 qty_shipped，並將 OutboundOrder 狀態改為 SHIPPED。
     * (實際扣減庫存的動作應發生在 Picking 流程，這裡只負責最終結單)
     */
    public function ship(Request $request, OutboundOrder $outbound)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('outbound-ship')) {
             return response()->json(['message' => '無權限執行出貨操作'], 403);
        }
        
        if ($outbound->status === 'SHIPPED') {
            return response()->json(['message' => '此訂單已出貨'], 400);
        }

        // 簡化邏輯：檢查是否有對應的 PickingOrder 且已完成
        if (!$outbound->pickingOrder || $outbound->pickingOrder->status !== 'PICKED_COMPLETE') {
             return response()->json(['message' => '揀貨單尚未完成或不存在'], 400);
        }
        
        return DB::transaction(function () use ($outbound) {
            
            // 根據 Picking Items 匯總實際出庫量，並更新 Outbound Items 的 qty_shipped
            $pickingItems = $outbound->pickingOrder->items;

            foreach ($outbound->items as $outboundItem) {
                // 找出對應的 Picking 項目，計算實際揀貨總量
                $totalPicked = $pickingItems
                    ->where('product_id', $outboundItem->product_id)
                    ->sum('qty_picked');
                
                // 更新實際出貨量
                $outboundItem->qty_shipped = $totalPicked;
                $outboundItem->save();
            }

            $outbound->status = 'SHIPPED';
            $outbound->save();

            return response()->json(['message' => '出貨結單成功', 'data' => $outbound->load('items')]);
        });
    }
}
