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

# ── Test AI service can import cleanly ──────────────────────────────────────
echo "[Entrypoint] Testing AI service Python imports..."
cd /app/ai-service
if /app/ai-service-venv/bin/python -c "
import sys
print('Python:', sys.version)
print('Testing faster_whisper...')
from faster_whisper import WhisperModel
print('faster_whisper OK')
print('Testing base model load...')
m = WhisperModel('base', device='cpu', compute_type='int8')
print('base model OK')
print('Testing fastapi & app.main...')
from app.main import app
print('app.main OK')
" 2>&1; then
    echo "[Entrypoint] AI service imports OK"
else
    echo "[ERROR] AI service import FAILED - check logs above"
    echo "[Entrypoint] Continuing anyway, supervisord will retry..."
fi
cd /app/backend

# ── Start Supervisor in background ──────────────────────────────────────────
echo "[Entrypoint] Starting Supervisor on PORT ${PORT:-8000}..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf &
SUPERVISOR_PID=$!

# ── Wait for AI service to be ready (up to 3 minutes) ──────────────────────
echo "[Entrypoint] Waiting for AI service to be ready on port 8001..."
AI_READY=0
for i in $(seq 1 36); do
    if curl -sf http://127.0.0.1:8001/health > /dev/null 2>&1; then
        echo "[Entrypoint] AI service is ready! (attempt $i)"
        AI_READY=1
        break
    fi
    echo "[Entrypoint] AI service not ready yet (attempt $i/36), waiting 5s..."
    sleep 5
done

if [ "$AI_READY" -eq 0 ]; then
    echo "[WARNING] AI service did not become ready within 3 minutes."
    echo "[WARNING] Transcription jobs will fail until it starts."
fi

# ── Keep container running ──────────────────────────────────────────────────
wait $SUPERVISOR_PID
