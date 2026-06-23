# syntax=docker/dockerfile:1.7

# ----- Stage 1: frontend -----
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.* tailwind.config.* postcss.config.* ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ----- Stage 2: vendor -----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction

# ----- Stage 3: runtime -----
FROM dunglas/frankenphp:1-php8.4-bookworm

# Install required PHP extensions (most ship in the base; opcache + pdo_sqlite needed)
RUN install-php-extensions \
    pdo_sqlite \
    bcmath \
    intl \
    zip \
    opcache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# App source
COPY . .

# Composer vendor from stage 2
COPY --from=vendor /app/vendor ./vendor

# Built frontend assets from stage 1
COPY --from=frontend /app/public/build ./public/build

# Caddyfile + entrypoint
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Data + backup directories
RUN mkdir -p /var/www/data /var/www/backups \
    && chown -R www-data:www-data /var/www/data /var/www/backups storage bootstrap/cache

# Default env (overridden by mounted .env)
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/data/database.sqlite \
    LOG_CHANNEL=stderr

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fs http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
