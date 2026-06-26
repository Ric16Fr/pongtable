cp .env.example .env
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
bun install --frozen-lockfile
bun run build
php artisan key:generate
php artisan migrate --force
echo "All completed"
