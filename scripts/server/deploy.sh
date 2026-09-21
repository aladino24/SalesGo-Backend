#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/salesgo-api"
APP_URL="${APP_URL:-http://127.0.0.1}"

if [[ ! -d "$APP_DIR" ]]; then
  echo "Direktori aplikasi tidak ada: $APP_DIR" >&2
  exit 1
fi

cd "$APP_DIR"

if [[ -f .env ]]; then
  cp .env .env.backup.$(date +%s) || true
fi

composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader
php artisan storage:link || true
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan queue:restart || true
php artisan optimize:clear
php artisan optimize

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR/storage" -type d -exec chmod 775 {} +
find "$APP_DIR/storage" -type f -exec chmod 664 {} +
find "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} +
find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} +

systemctl reload php8.2-fpm
systemctl reload nginx
supervisorctl reread
supervisorctl update
supervisorctl restart salesgo-api-worker:*

echo "Deployment selesai."
