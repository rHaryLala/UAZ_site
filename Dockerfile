# syntax=docker/dockerfile:1
#
# Image PHP-FPM du site UAZ (optionnelle : le déploiement de référence est un VPS, voir docs/DEPLOIEMENT.md).
# Trois étapes : dépendances PHP → build des assets (Vite) → image d'exécution. Node n'existe pas dans l'image finale.

# --- 1. Dépendances PHP --------------------------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- 2. Assets (Tailwind, Alpine, GSAP…) ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Le build lit vendor/livewire (Alpine CSP) et les vues Blade pour Tailwind.
COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# --- 3. Exécution --------------------------------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp libavif freetype oniguruma mariadb-client \
    && apk add --no-cache --virtual .build icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev libavif-dev freetype-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql pdo_sqlite bcmath exif opcache pcntl \
    && apk del .build

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-uaz.ini
WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
