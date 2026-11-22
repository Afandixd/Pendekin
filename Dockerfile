# syntax=docker/dockerfile:1
# composer.json meminta ^8.3; dependency Symfony di composer.lock meminta >=8.4.1.
FROM php:8.4-cli-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git unzip libzip-dev libonig-dev libsqlite3-dev libicu-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite mbstring zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

# Jenkins: docker build --target test -t pendekin:test .
# Test memakai SQLite in-memory sesuai phpunit.xml.
FROM php-base AS test
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress \
    && cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && php artisan test

# Build frontend Laravel/Vite jika dipakai.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install --no-audit --no-fund; fi
COPY . .
RUN npm run build

# Image yang dipush Jenkins ke Docker Hub dan dijalankan Compose.
FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /app/public/build /var/www/html/public/build

USER www-data
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=5s --start-period=45s --retries=5 \
    CMD ["php", "docker/healthcheck.php"]
# Untuk tugas kuliah gunakan server bawaan Laravel. Deploy hanya setelah MySQL healthy.
CMD ["sh", "-c", "php artisan migrate --force && exec php artisan serve --host=0.0.0.0 --port=8000"]
