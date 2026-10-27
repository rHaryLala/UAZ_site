#!/bin/sh
# Prépare l'application au démarrage du conteneur (migrations optionnelles, caches), puis lance la commande demandée.
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction
fi

php artisan storage:link --no-interaction 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
