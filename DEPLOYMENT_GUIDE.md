# 部署指南 (Deployment Guide)

本指南涵蓋在生產環境中啟用 WMS 應用程式的關鍵步驟，假設您已使用 Docker Compose 啟動了所有必要的服務 (Nginx, PHP-FPM, MySQL, Redis)。

## 1\. 環境變數配置 (.env)

在 `laravel` 目錄下，您必須配置生產環境專屬的 `.env` 檔案。

1.  **複製配置檔**

    ```bash
    cd [專案根目錄]
    cp laravel/.env.example laravel/.env
    ```

2.  **修改核心變數**

    請務必修改以下關鍵變數：

    | 變數 | 說明 | 生產環境建議值 |
    | :--- | :--- | :--- |
    | `APP_ENV` | 應用程式環境 | `production` |
    | `APP_DEBUG` | 是否開啟除錯模式 | `false` |
    | `APP_KEY` | **重要**：應用程式金鑰 | `base64:xxxxxxxxxxxxxxxxxx` |
    | `DB_HOST` | 資料庫服務位址 (通常是 Docker Compose 服務名稱) | `mysql` 或 `wms-mysql` |
    | `REDIS_HOST` | Redis 服務位址 | `redis` 或 `wms-redis` |
    | `QUEUE_CONNECTION` | 隊列驅動 (應設為 `redis`) | `redis` |

## 2\. 啟動 Docker 服務與初始化

使用 Docker Compose 啟動服務後，進入 PHP 容器 (例如 `wms-app`) 進行初始化。

1.  **一鍵啟動所有服務**

    ```bash
    docker-compose up -d --build
    ```

2.  **進入 PHP 容器**

    ```bash
    docker exec -it wms-app /bin/sh
    ```

3.  **執行初始化命令** (在容器內執行)

    * **生成 APP_KEY** (如果尚未手動填寫)

        ```bash
        php artisan key:generate
        ```

    * **執行資料庫遷移 (Migrate)**

        這將創建所有必要的資料表結構。生產環境中必須使用 `--force` 參數。

        ```bash
        php artisan migrate --force
        ```

    * **播種資料 (Seed)** (可選，用於初始化系統帳號/權限)

        ```bash
        php artisan db:seed --force
        ```

    * **優化配置** (推薦在生產環境中執行)

        ```bash
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        ```

## 3\. 啟動隊列工作者 (Queue Workers)

WMS 系統依賴隊列處理背景任務。您需要一個持久化的程序來監聽隊列。

**方法：使用 Supervisor 或專門的 Docker Worker 服務**

在簡單部署中，您可以透過 `docker exec` 啟動一個持久化的隊列監聽程序：

```bash
# 假設您已在 Dockerfile 中安裝 Supervisor
# 啟動 Supervisor 服務
supervisord -c /etc/supervisor/conf.d/supervisord.conf 

# 或者，直接在背景運行隊列工作者 (不推薦用於生產環境)
# 監聽 default 隊列，並限制每個任務最多運行 60 秒
php artisan queue:work --queue=default --tries=3 --daemon &
```

**⚠️ 注意：** 在實際生產環境中，建議在 Docker Compose 中定義一個獨立的 Worker 服務，或使用 Supervisor 來管理 `queue:work` 進程，以確保其穩定性和可靠性。
