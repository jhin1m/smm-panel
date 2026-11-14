# syntax=docker/dockerfile:1
# 1 Dockerfile cho app (nginx + php-fpm), queue, scheduler. Stage `cli` cũng là image chạy test (compose.dev.yml).

# --- PHP CLI 8.4 + extension Filament cần (intl) ---
FROM serversideup/php:8.4-cli AS cli
USER root
RUN install-php-extensions intl bcmath
USER www-data
WORKDIR /var/www/html

# --- Composer vendor (không dev) + asset Filament (filament:upgrade trong post-autoload-dump) ---
FROM cli AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# --- Build asset Vite: cần vendor/filament vì theme admin import CSS từ đó ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /var/www/html /app
RUN npm run build

# --- Runtime ---
FROM serversideup/php:8.4-fpm-nginx AS runtime
USER root
RUN install-php-extensions intl bcmath
USER www-data
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    PHP_FPM_PM_CONTROL=dynamic \
    PHP_FPM_PM_MAX_CHILDREN=20 \
    PHP_FPM_PM_START_SERVERS=4 \
    PHP_FPM_PM_MIN_SPARE_SERVERS=2 \
    PHP_FPM_PM_MAX_SPARE_SERVERS=6

# Phiên bản chạy thật nằm trong image (config('app.version') đọc file này); CI truyền tag release, build tay là "dev".
ARG APP_VERSION=dev
RUN printf '%s\n' "$APP_VERSION" > /var/www/html/VERSION

# Thư mục trao đổi với container updater (volume `updates` thừa hưởng bố cục này khi tạo lần đầu): gốc thuộc root (chỉ updater
# ghi status/heartbeat), app chỉ ghi được inbox/request.json. Image updater có cùng bố cục, nên container nào mount trước cũng vậy.
USER root
RUN mkdir -p /updates/inbox && chown www-data:www-data /updates/inbox
USER www-data
