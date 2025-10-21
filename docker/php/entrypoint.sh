#!/bin/bash
set -e

# 確保 .env 中的 DB 變數可用，這裡使用 docker-compose 的環境變數
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
APP_USER="appuser"

# =================================================================
# 1. 等待 MySQL 可連線 (使用 nc -z 檢查)
# =================================================================
echo "🟡 正在等待資料庫 ($DB_HOST:$DB_PORT) 啟動..."
until nc -z $DB_HOST $DB_PORT 2>/dev/null; do
  echo "Still waiting for DB connection..."
  sleep 1
done
echo "🟢 資料庫已啟動並可連線。"

# =================================================================
# 2. 修正權限 (以 root 身份執行)
# =================================================================
echo "🛠 修正 storage 與 cache 目錄權限..."
# 將 storage/ 和 bootstrap/cache 的所有權限交給 appuser (UID 1000)
chown -R $APP_USER:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true


# =================================================================
# 3. 執行 Laravel 初始化 (切換至 appuser 身份)
# =================================================================
su $APP_USER -c "
  # 設定 Composer 權限，避免 'root' 警告
  export COMPOSER_ALLOW_SUPERUSER=1

  # 安裝 composer 套件（若 vendor 不存在）
  if [ ! -d /var/www/html/vendor ]; then
    echo '📦 執行 composer install...'
    /usr/bin/composer install --no-interaction --prefer-dist --optimize-autoloader
  fi

  # 產生 APP_KEY 若尚未存在
  if [ -f /var/www/html/.env ] && [ -z \"\$(grep APP_KEY /var/www/html/.env | cut -d'=' -f2)\" ]; then
    echo '🔑 產生 APP_KEY...'
    php /var/www/html/artisan key:generate
  fi
  
  # 執行快取生成 (config & route cache)
  echo '⚡️ Generating config and route cache...'
  php /var/www/html/artisan config:cache || true
  php /var/www/html/artisan route:cache || true

  # 執行資料庫遷移
  echo 'Migrating database...'
  php /var/www/html/artisan migrate --force
"

# =================================================================
# 4. 啟動 PHP-FPM (切換回 root 身份以確保服務啟動，但 php-fpm master process 會切換 user)
# =================================================================
echo "🚀 啟動 PHP-FPM..."
# 使用 exec 替換當前 shell 进程，以確保信號能傳遞給 php-fpm
exec php-fpm -F
