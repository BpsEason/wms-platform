# WMS Backend

**智能倉儲，高效管理**  
**一站式解決方案，實現庫存精準追蹤與流程自動化。**

| KPI 指標          | 預期價值          |
|-------------------|-------------------|
| TTV (Time to Value) | {TTV} 天         |
| 效能提升          | {X%}             |
| 人力成本節省      | {Y%}             |

WMS Backend 提供快速上線的倉儲管理系統，幫助企業優化庫存流程，降低運營成本並提升 ROI。系統整合入庫、出庫與揀貨功能，預計 3 個月內實現 20-30% 成本節省。技術上採用 Laravel 12.x 與 Vue3，確保資料一致性與可擴展性，支援 Docker 容器化部署與即時監控。

## 功能清單

- **庫存管理**：即時查詢與調整庫存，支援多儲位管理。
- **入庫流程**：創建入庫單與上架操作，自動更新庫存。
- **出庫流程**：生成出庫單與出貨結單，自動扣減庫存。
- **揀貨管理**：創建揀貨單與掃描確認，確保作業準確。
- **用戶認證**：Sanctum Token 認證，動態分配權限。
- **隊列處理**：Redis 驅動背景任務，如出貨通知。
- **管理後台**：商品、儲位、用戶 CRUD 操作。
- **前端介面**：Vue3 + Pinia 提供響應式 UI，支援 Token 刷新。

## 技術摘要

### 架構圖文字描述
```
Client (Vue3 + Pinia) <-> Nginx <-> PHP-FPM (Laravel 12.x) <-> MySQL (DB) / Redis (Queue/Cache)
  |                           |                     |              |
  V                           V                     V              V
Browser (Vite)         API Routes             Eloquent ORM   Queue Worker
```

- **前端**：Vue3 與 Pinia 負責狀態管理，Vite 構建，Axios 處理 API 請求。
- **後端**：Laravel 12.x 處理業務邏輯，Sanctum 負責認證，路由位於 `routes/api.php`。
- **資料庫**：MySQL 儲存商品、儲位與訂單，遷移檔案位於 `laravel/database/migrations/`。
- **隊列**：Redis 處理非同步任務，Job 定義於 `laravel/app/Jobs/`。

### InventoryService 並發防護程式碼片段
```php
<?php
namespace App\Modules\Inventory\Services;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService {
    public function changeStock(int $productId, int $locationId, float $quantityChange, int $userId, string $type, array $context = []): Inventory {
        return DB::transaction(function () use ($productId, $locationId, $quantityChange, $userId, $type, $context) {
            $inventory = Inventory::where('product_id', $productId)->where('location_id', $locationId)->lockForUpdate()->first();
            if (!$inventory) {
                try {
                    $inventory = Inventory::create(['product_id' => $productId, 'location_id' => $locationId, 'quantity' => 0.00]);
                } catch (\Exception $e) {
                    $inventory = Inventory::where('product_id', $productId)->where('location_id', $locationId)->lockForUpdate()->firstOrFail();
                }
            }
            $newQuantity = $inventory->quantity + $quantityChange;
            if ($newQuantity < 0) throw new InvalidArgumentException("庫存數量不足");
            $inventory->quantity = $newQuantity;
            $inventory->save();
            InventoryTransaction::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'user_id' => $userId,
                'quantity_change' => $quantityChange,
                'current_quantity' => $newQuantity,
                'type' => $type,
                'context' => json_encode($context),
            ]);
            return $inventory->load(['product', 'location']);
        });
    }
}
```

### 監控指標清單
- API 響應時間：< 200ms
- 隊列延遲：< 5 秒
- 資料庫連接數：< 100
- CPU/記憶體使用率：< 70%
- 錯誤率：< 0.1%
- 庫存交易 TPS：> 100

### SLA 建議值
- 可用性：99.9%（每月 downtime < 43 分鐘）
- 響應時間：95% API 呼叫 < 500ms
- 資料恢復：RPO < 1 小時，RTO < 4 小時

## 商業摘要

### ROI 計算範例表格
| 項目             | 成本（USD/月） | 收益（USD/月） | ROI 計算                  |
|------------------|----------------|----------------|---------------------------|
| 系統訂閱         | 200           | -              | -                         |
| 人力節省         | -             | 1000           | (收益 - 成本) / 成本 = 6x |
| 效能提升         | -             | 800            | 總 ROI: 9 個月回收        |
| 總計             | 200           | 1800           |                           |

假設每月節省 10 小時人工（時薪 $100）與 20% 庫存效率提升（價值 $800）。

### 收費模式範例與價格區間
- **SaaS 訂閱**：基礎版 $50-200/月（1000 筆交易），高級版 $300-800/月（無限交易）。利潤來源：訂閱與升級費用。
- **授權模式**：一次性 $5000-20000（私有部署），含 1 年維護。利潤來源：授權與續約費用。
- **增值服務**：客製化開發 $1000-5000/項目（API 整合、培訓）。利潤來源：項目與長期合約。

### 上線時間表（30 天）
- **第 1-7 天**：環境設置與需求確認
- **第 8-15 天**：資料遷移與功能測試
- **第 16-25 天**：用戶培訓與 Beta 測試
- **第 26-30 天**：正式上線與監控

## 快速上手

```bash
# 複製環境檔案
cd /path/to/wms-backend/laravel
cp .env.example .env

# 安裝依賴
composer install
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 啟動 Docker
cd /path/to/wms-backend
docker-compose up -d --build

# 執行遷移與 Seed
cd /path/to/wms-backend/laravel
php artisan migrate
php artisan db:seed

# 啟動 Worker
cd /path/to/wms-backend
docker-compose up -d worker

# 啟動前端
cd /path/to/wms-backend/frontend
npm install
npm run dev
```

預設管理員：`admin@wms.com` / `password`。前端訪問：`http://localhost:5173`。

## 測試與 CI

### 本地測試
```bash
cd /path/to/wms-backend/laravel
php artisan test
php artisan test --filter InventoryTest
```

### GitHub Actions CI
檔案：`.github/workflows/ci.yml`
```yaml
name: Laravel CI
on:
  push:
    branches: [ "main", "develop" ]
jobs:
  laravel-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: wms_test_db
          MYSQL_ROOT_PASSWORD: secret
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s
    steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: pdo_mysql, redis
    - run: composer install --prefer-dist --no-progress
      working-directory: ./laravel
    - run: cp .env.example .env && php artisan key:generate
      working-directory: ./laravel
    - run: php artisan migrate
      working-directory: ./laravel
    - run: vendor/bin/phpunit
      working-directory: ./laravel
```

## 驗收條件清單

- **功能**：
  - 登入/登出 API 正常（`/api/auth/login`, `/api/auth/logout`）。
  - 庫存查詢返回分頁數據（`/api/wms/`）。
  - 入庫與出庫流程更新庫存（`/api/wms/inbound/*`, `/api/wms/outbound/*`）。
  - 揀貨掃描確認（`/api/wms/picking/*`）。
- **安全**：
  - 所有 API 需 Token 與權限（`inventory-query` 等）。
  - 庫存異動使用行鎖防競態。
- **性能**：
  - API 響應 < 500ms。
  - 隊列任務 < 5 秒完成。
- **穩定性**：
  - Docker 容器無錯誤（`docker-compose logs`）。
  - 所有 PHPUnit 測試通過。
- **前端**：
  - 登入頁面正常（`http://localhost:5173/login`）。
  - 庫存頁面顯示正確數據（`http://localhost:5173/inventory`）。
