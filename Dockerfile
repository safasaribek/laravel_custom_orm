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

# Composer bağımlılıkları
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

# Uygulama anahtarı oluştur (yoksa)
RUN php artisan key:generate --ansi || true

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
