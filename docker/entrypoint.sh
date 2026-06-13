#!/bin/sh
set -e

# Recreate runtime directories — named volumes mount in empty and would
# otherwise hide the structure baked into the image.
mkdir -p \
    /data \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

[ -f /data/database.sqlite ] || touch /data/database.sqlite

# Warm the caches with the runtime environment. route:cache is intentionally
# skipped: routes/web.php contains a closure route, which cannot be cached.
php artisan config:cache
php artisan view:cache

php artisan migrate --force

# Hand off to the image's default command (frankenphp run).
exec "$@"
