# syntax=docker/dockerfile:1
# Laravel 11 + Vite/Vue + Inertia — untuk Coolify (PostgreSQL di layanan terpisah).
# Set di Coolify: APP_KEY, APP_URL, DB_*, dll. Migrasi otomatis saat container start.

# Ziggy diimpor dari vendor/ di resources/js/app.js; .dockerignore mengabaikan vendor,
# jadi kita pasang paket Composer sekali di sini dan menyalin hanya tightenco/ziggy ke stage Vite.
FROM composer:2 AS ziggy_vendor
WORKDIR /app
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs

# --- Frontend (Vite)
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit
COPY . .
COPY --from=ziggy_vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

# --- PHP + Nginx + Supervisor (PHP 8.4 — selaras dengan runtime produksi)
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zip unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependensi Composer (cache layer)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

COPY --from=frontend /app/public/build ./public/build

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak 2>/dev/null || true

COPY docker/php-fpm/zz-overrides.conf /usr/local/etc/php-fpm.d/zz-overrides.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV RUN_MIGRATIONS=true
ENV DB_WAIT_TIMEOUT=120

EXPOSE 8081

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8081/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
