#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

if [ -f .env.testing.docker ]; then
    cp .env.testing.docker .env.testing
fi

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

chown -R sail:sail storage bootstrap/cache

if [ ! -d vendor ]; then
    gosu sail composer install
fi

if ! php -m | grep -qi '^mongodb$'; then
    echo 'La extension mongodb no esta habilitada en PHP.' >&2
    exit 1
fi

echo "Waiting for MariaDB at ${DB_HOST:-mariadb-test}:${DB_PORT:-3306}..."
MAX_TRIES=30; TRY=0
until mysqladmin ping -h"${DB_HOST:-mariadb-test}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-laravel}" -p"${DB_PASSWORD:-laravel}" --silent; do
    TRY=$((TRY+1)); if [ $TRY -ge $MAX_TRIES ]; then echo "MariaDB not ready after $MAX_TRIES tries"; break; fi
    sleep 2
done

echo "Waiting for MongoDB at ${MONGO_HOST:-mongo-test}:${MONGO_PORT:-27017}..."
TRY=0
until php -r '$uri = getenv("MONGO_URI") ?: "mongodb://mongo-test:27017"; $manager = new MongoDB\Driver\Manager($uri); $manager->executeCommand("admin", new MongoDB\Driver\Command(["ping" => 1]));'; do
    TRY=$((TRY+1)); if [ $TRY -ge $MAX_TRIES ]; then echo "MongoDB not ready after $MAX_TRIES tries"; break; fi
    sleep 2
done

rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/packages.php bootstrap/cache/services.php

gosu sail php artisan config:clear
gosu sail php artisan test
