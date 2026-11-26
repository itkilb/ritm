#!/bin/sh

set -eu

mkdir -p \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage

# Laravel expects a readable .env path even when configuration comes from
# container environment variables. Keep an empty file instead of baking one.
if [ ! -f .env ]; then
    : > .env
    chown www-data:www-data .env
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Copy .env.example to .env and set APP_KEY (openssl rand -base64 32)." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    php /usr/local/bin/wait-for-db.php
fi

# Apiato boots Passport even though product authentication uses Sanctum
# sessions. Generate the OAuth key pair once and keep it readable by PHP-FPM.
if [ ! -s storage/oauth-private.key ] || [ ! -s storage/oauth-public.key ]; then
    php artisan passport:keys --force
    chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
    chmod 600 storage/oauth-private.key
    chmod 660 storage/oauth-public.key
fi

exec "$@"
