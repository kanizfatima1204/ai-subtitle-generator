#!/bin/bash
set -e

echo "===================================================="
echo "   Starting AI Subtitle Generator on Railway        "
echo "===================================================="

cd /app/backend

# Create .env if missing
if [ ! -f .env ]; then
    echo "[Entrypoint] Creating .env from .env.example..."
    cp .env.example .env
fi

# Ensure SQLite database directory & file exist
mkdir -p /app/backend/database
if [ ! -f /app/backend/database/database.sqlite ]; then
    touch /app/backend/database/database.sqlite
fi

# Ensure required storage directories exist
mkdir -p /app/backend/storage/framework/cache/data \
         /app/backend/storage/framework/sessions \
         /app/backend/storage/framework/views \
         /app/backend/storage/logs \
         /app/backend/storage/app/public \
         /app/ai-service/storage/uploads

# Fix permissions
chmod -R 777 /app/backend/storage /app/backend/database /app/ai-service/storage

# Generate Laravel Application Key if missing
if [ -z "$APP_KEY" ] && ! grep -q "APP_KEY=base64:" .env; then
    echo "[Entrypoint] Generating Laravel APP_KEY..."
    php artisan key:generate --force
fi

# Run database migrations and seed default data
echo "[Entrypoint] Running database migrations..."
php artisan migrate --force

echo "[Entrypoint] Running database seeders..."
php artisan db:seed --force

# Create public storage symlink
echo "[Entrypoint] Linking public storage..."
php artisan storage:link || true

echo "[Entrypoint] All checks passed. Starting Supervisor on PORT ${PORT:-8000}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
