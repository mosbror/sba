#!/usr/bin/env sh
set -eu

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan view:cache

php-fpm -D
exec nginx -g 'daemon off;'
