<?php
// Service: laravel/app/Modules/Inventory/Services/InventoryService.php (V2 - 修正並發競態問題)
// WMS 核心庫存服務：處理所有庫存的原子性變更與交易日誌記錄。
namespace App\Modules\Inventory\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Database\QueryException;

class InventoryService
{
    /**
     * 進行庫存變更的核心方法，包含事務與悲觀鎖 (lockForUpdate) 以保證原子性。
     * 處理首次創建時的並發競態 (try/create then relock 模式)。
     *
     * @param int $productId
     * @param int $locationId
     * @param float $quantityChange 庫存變動量 (正數為入庫/增，負數為出庫/減)
     * @param int $userId 執行操作的使用者 ID
     * @param string $type 交易類型 (e.g., RECEIPT, ISSUE, ADJUST, PUTAWAY)
     * @param array $context 額外的交易資訊 (如 Inbound/Outbound ID)
     * @return Inventory
     */
    public function changeStock(
        int $productId,
        int $locationId,
        float $quantityChange,
        int $userId,
        string $type,
        array $context = []
    ): Inventory {
        // 使用資料庫事務處理，確保操作的原子性
        return DB::transaction(function () use ($productId, $locationId, $quantityChange, $userId, $type, $context) {
            
            $inventory = null;

            try {
                // 1. 嘗試創建記錄。如果並發創建，其中一個會因為 Unique Index 失敗。
                $inventory = Inventory::create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => 0.00,
                ]);
                
                // 剛創建的行也需要鎖定
                $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
                
            } catch (QueryException $e) {
                // 捕獲 QueryException (通常是 Unique Constraint 違規)
                
                // 僅在確定是唯一約束違規時（通常為 SQLSTATE 23000，或 MySQL 1062）才執行重讀
                if ($e->getCode() == '23000' || $e->getCode() == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    
                    // 並發插入發生：重新讀取並鎖定已存在的行
                    // V2 修正: 避免依賴錯誤訊息字串，而是依賴捕獲後立即嘗試重讀並加鎖。
                    $inventory = Inventory::where('product_id', $productId)
                        ->where('location_id', $locationId)
                        ->lockForUpdate() // 關鍵：確保獲取到鎖
                        ->first();
                        
                    if (!$inventory) {
                         // 重試失敗，拋出原始錯誤
                         throw $e;
                    }
                } else {
                    // 其他 Query 錯誤，拋出
                    throw $e;
                }
            }


            // 2. 執行庫存變動邏輯
            $oldQuantity = $inventory->quantity;
            $newQuantity = $oldQuantity + $quantityChange;

            if ($newQuantity < 0) {
                // 如果庫存不足，回滾事務並拋出錯誤
                throw new InvalidArgumentException("庫存數量不足。當前庫存: {$oldQuantity}");
            }

            // 3. 更新庫存
            $inventory->quantity = $newQuantity;
            $inventory->save();

            // 4. 寫入交易日誌
            InventoryTransaction::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'user_id' => $userId,
                'quantity_change' => $quantityChange,
                'current_quantity' => $newQuantity,
                'type' => $type,
                'context' => json_encode($context), 
            ]);

            return $inventory;
        });
    }
}
