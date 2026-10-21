#!/bin/sh
set -e

cd /var/www/html

: "${PORT:=10000}"

# Fail fast instead of serving a broken app
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Add it in the Render environment settings." >&2
    exit 1
fi

# Apache listens on the port Render provides
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan migrate --force --no-interaction

# Caches are built at start-up, when the real environment variables exist
php artisan config:cache
php artisan route:cache || echo "route:cache skipped (a route still uses a closure)"
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground