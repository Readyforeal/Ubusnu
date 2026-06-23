#!/usr/bin/env bash
set -euo pipefail

# Ensure data + backup dirs are writable; create the SQLite file if missing
mkdir -p /var/www/data /var/www/backups
touch /var/www/data/database.sqlite
chown -R www-data:www-data /var/www/data /var/www/backups storage bootstrap/cache

# Run pending migrations
php artisan migrate --force

# Warm caches (idempotent)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Start the Laravel scheduler in the background (one tick per minute)
( while true; do php artisan schedule:run --no-interaction >/dev/null 2>&1; sleep 60; done ) &

# Hand off to FrankenPHP
exec frankenphp run --config /etc/frankenphp/Caddyfile
