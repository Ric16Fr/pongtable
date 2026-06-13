# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Build the frontend assets with bun (Vite + Tailwind v4).
# ---------------------------------------------------------------------------
FROM oven/bun:1 AS assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY . .
RUN bun run build

# ---------------------------------------------------------------------------
# Stage 2 — Production runtime: FrankenPHP (Caddy + PHP 8.5, single binary).
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.5 AS app

# PHP extensions Laravel + SQLite need in production.
RUN install-php-extensions pdo_sqlite intl zip opcache pcntl

# Composer binary (only used at build time).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    # ":80" serves plain HTTP. Set to a domain to let FrankenPHP fetch TLS automatically.
    SERVER_NAME=:80

# Install PHP dependencies first so this layer caches across code changes.
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

# Application source + the assets built in stage 1.
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi

COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /data \
    && chmod -R ug+rw storage bootstrap/cache

EXPOSE 80 443

# The base image's CMD (frankenphp run) is inherited; the entrypoint runs
# migrations + cache warmup, then exec's it.
ENTRYPOINT ["entrypoint"]
