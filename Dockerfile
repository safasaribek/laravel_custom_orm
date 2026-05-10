FROM php:8.4-cli

# Sistem bağımlılıkları
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring xml zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Composer bağımlılıkları (önce sadece composer.json kopyala — layer cache)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Uygulama dosyalarını kopyala
COPY . .

# Gerekli dizinleri oluştur ve izinleri ayarla
RUN mkdir -p bootstrap/cache storage/logs storage/framework/cache \
               storage/framework/sessions storage/framework/views \
    && chmod -R 775 storage bootstrap/cache

# .env yoksa .env.example'dan oluştur
RUN [ -f .env ] || cp .env.example .env

# APP_KEY oluştur
RUN php artisan key:generate --ansi --force

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
