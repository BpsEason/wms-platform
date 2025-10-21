<?php
// Controller: laravel/app/Modules/Inventory/Http/Controllers/InboundController.php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InboundOrder;
use App\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InboundController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 創建 Inbound Order (收貨單) (Ability: inbound-create)
     */
    public function store(Request $request)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('inbound-create')) {
             return response()->json(['message' => '無權限創建入庫單'], 403);
        }

        $validated = $request->validate([
            'reference_no' => 'required|string|unique:inbound_orders,reference_no',
            'supplier_code' => 'required|string|exists:suppliers,code',
            'expected_arrival_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_expected' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $supplier = Supplier::where('code', $validated['supplier_code'])->first();

            $inbound = InboundOrder::create([
                'reference_no' => $validated['reference_no'],
                'supplier_id' => $supplier->id,
                'user_id' => $request->user()->id,
                'status' => 'PENDING',
                'expected_arrival_date' => $validated['expected_arrival_date'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $inbound->items()->create([
                    'product_id' => $item['product_id'],
                    'qty_expected' => $item['qty_expected'],
                ]);
            }

            return response()->json(['message' => '入庫單創建成功', 'data' => $inbound->load('items')], 201);
        });
    }

    /**
     * 執行 Putaway (上架) 操作 (Ability: inbound-putaway)
     * 涉及庫存變動，為核心業務。
     */
    public function putaway(Request $request, InboundOrder $inbound)
    {
        // V2: 二次授權檢查
        if (!$request->user()->tokenCan('inbound-putaway')) {
             return response()->json(['message' => '無權限執行上架操作'], 403);
        }
        
        $validated = $request->validate([
            'item_id' => 'required|exists:inbound_items,id',
            'location_id' => 'required|exists:locations,id',
            'qty_received' => 'required|integer|min:1',
        ]);

        $item = $inbound->items()->findOrFail($validated['item_id']);

        if ($item->qty_received + $validated['qty_received'] > $item->qty_expected) {
             return response()->json(['message' => '接收數量超過預計數量'], 400);
        }

        try {
            // V2: 使用修正後的 changeStock 服務方法
            $inventory = $this->inventoryService->changeStock(
                $item->product_id,
                $validated['location_id'],
                (float) $validated['qty_received'],
                $request->user()->id,
                'PUTAWAY', // 交易類型
                ['inbound_id' => $inbound->id, 'inbound_item_id' => $item->id]
            );

            // 更新 InboundItem 實際接收數量
            $item->qty_received += $validated['qty_received'];
            $item->save();

            // 檢查是否所有項目都已完成接收，然後更新 InboundOrder 狀態 (簡化邏輯)
            if ($inbound->items()->whereColumn('qty_received', '<', 'qty_expected')->count() === 0) {
                 $inbound->status = 'PUTAWAY_COMPLETE';
                 $inbound->save();
            }

            return response()->json([
                'message' => '上架成功',
                'inventory' => $inventory->load(['product', 'location'])
            ]);

        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
