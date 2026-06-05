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

# Generate self-signed SSL cert for HTTPS (WebAuthn requires secure context)
SSL_DIR=/etc/nginx/ssl
mkdir -p "$SSL_DIR"
if [ ! -f "$SSL_DIR/server.crt" ]; then
    SAN="DNS:localhost,IP:127.0.0.1,DNS:host.docker.internal"
    if [ -n "${APP_DOMAIN:-}" ]; then
        SAN="${SAN},DNS:${APP_DOMAIN},IP:${APP_DOMAIN}"
    fi
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$SSL_DIR/server.key" \
        -out "$SSL_DIR/server.crt" \
        -subj "/CN=local-dev" \
        -addext "subjectAltName=${SAN}" 2>/dev/null || \
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$SSL_DIR/server.key" \
        -out "$SSL_DIR/server.crt" \
        -subj "/CN=local-dev"
fi

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
