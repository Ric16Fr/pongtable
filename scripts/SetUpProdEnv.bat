call cp .env.example .env
call composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
call bun install --frozen-lockfile
call bun run build
call php artisan key:generate
call php artisan migrate --force
echo "All completed"
