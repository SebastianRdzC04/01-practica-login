#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

if [ -f .env.docker ]; then
    cp .env.docker .env
elif [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

chown -R sail:sail storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

usermod -a -G sail www-data 2>/dev/null || true

if [ ! -d vendor ]; then
    gosu sail composer install --no-interaction
fi

if [ ! -d node_modules ]; then
    gosu sail npm install
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    gosu sail php artisan key:generate --force
fi

echo "Waiting for MariaDB at ${DB_HOST:-mariadb}:${DB_PORT:-3306}..."
MAX_TRIES=30; TRY=0
until mysqladmin ping -h"${DB_HOST:-mariadb}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-laravel}" -p"${DB_PASSWORD:-laravel}" --silent; do
    TRY=$((TRY+1)); if [ $TRY -ge $MAX_TRIES ]; then echo "MariaDB not ready after $MAX_TRIES tries"; break; fi
    sleep 2
done

echo "Waiting for MongoDB at ${MONGO_HOST:-mongo}:${MONGO_PORT:-27017}..."
TRY=0
until gosu sail php -r '$uri = getenv("MONGO_URI") ?: "mongodb://mongo:27017"; $manager = new MongoDB\Driver\Manager($uri); $manager->executeCommand("admin", new MongoDB\Driver\Command(["ping" => 1]));'; do
    TRY=$((TRY+1)); if [ $TRY -ge $MAX_TRIES ]; then echo "MongoDB not ready after $MAX_TRIES tries"; break; fi
    sleep 2
done

rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/packages.php bootstrap/cache/services.php

gosu sail php artisan config:clear
gosu sail php artisan migrate --force
gosu sail php artisan db:seed --force

if [ "${APP_ENV:-local}" = "production" ]; then
    gosu sail npm run build
else
    gosu sail npm run dev -- --host 0.0.0.0 > /tmp/vite.log 2>&1 &
fi

# Start php-fpm in background
php-fpm -D

# Start nginx in foreground
exec nginx -g "daemon off;"
