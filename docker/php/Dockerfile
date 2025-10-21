# Dockerfile: docker/php/Dockerfile
# 基於 PHP 8.3 FPM 映像，用於執行 Laravel 應用

# 使用 PHP 8.3 FPM
FROM php:8.3-fpm

# 設定非 root 使用者 ID
ARG UID=1000

# 安裝系統依賴
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    unzip \
    libfreetype-dev \
    libonig-dev \
    # 權限修正與網路檢查工具
    sudo \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# 安裝並啟用 PHP 擴展 (新增 bcmath, pcntl, opcache)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd opcache bcmath pcntl

# 安裝 Redis 擴展 (phpredis)
RUN pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

# 安裝 Composer (使用官方映像複製)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 建立非 root 使用者，並給予 sudo 權限以處理資料卷權限問題
RUN useradd -u ${UID} -G www-data -ms /bin/bash appuser

# 設定工作目錄
WORKDIR /var/www/html

# 複製 entrypoint.sh 並設定為執行檔
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 容器啟動命令：暴露 9000 Port
EXPOSE 9000
# 容器啟動時先執行 entrypoint 處理 DB 等待、權限修正、依賴安裝
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
