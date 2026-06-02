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

if [ ! -d vendor ]; then
    gosu sail composer install
fi

if [ ! -d node_modules ]; then
    gosu sail npm install
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    gosu sail php artisan key:generate --force
fi

echo "Waiting for MariaDB at ${DB_HOST:-mariadb}:${DB_PORT:-3306}..."
until mysqladmin ping -h"${DB_HOST:-mariadb}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-laravel}" -p"${DB_PASSWORD:-secret}" --silent; do
    sleep 2
done

echo "Waiting for MongoDB at ${MONGO_HOST:-mongo}:${MONGO_PORT:-27017}..."
until gosu sail php -r '$uri = getenv("MONGO_URI") ?: "mongodb://mongo:27017"; $manager = new MongoDB\Driver\Manager($uri); $manager->executeCommand("admin", new MongoDB\Driver\Command(["ping" => 1]));'; do
    sleep 2
done

rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/packages.php bootstrap/cache/services.php

gosu sail php artisan config:clear
gosu sail php artisan migrate --force
gosu sail php artisan db:seed --force

# Build or run dev server depending on APP_ENV
if [ "${APP_ENV:-local}" = "production" ]; then
    gosu sail npm run build
else
    gosu sail npm run dev -- --host 0.0.0.0 > /tmp/vite.log 2>&1 &
fi

exec gosu sail php artisan serve --host=0.0.0.0 --port=8000
