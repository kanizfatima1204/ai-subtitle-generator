FROM php:8.2-cli-bookworm

# Environment configuration
ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONUNBUFFERED=1 \
    PORT=8000 \
    PHP_CLI_SERVER_WORKERS=4 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Install system dependencies (PHP libraries, Python 3.11, ffmpeg, supervisor, curl, git, unzip)
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    python3-venv \
    python3-dev \
    ffmpeg \
    supervisor \
    curl \
    git \
    unzip \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        mbstring \
        zip \
        pcntl \
        bcmath \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js (v20 LTS) for asset building
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# 1. Setup Python Virtual Environment for AI Service
RUN python3 -m venv /app/ai-service-venv
ENV PATH="/app/ai-service-venv/bin:$PATH"

# Install lightweight CPU PyTorch and faster-whisper dependencies
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir torch torchaudio --index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir \
        fastapi \
        "uvicorn[standard]" \
        python-multipart \
        faster-whisper \
        pydantic \
        httpx \
        python-dotenv

# Copy entire repository into the container
COPY . /app

# 2. Setup PHP Backend
WORKDIR /app/backend

# Configure PHP runtime settings (uploads, memory limits, timeouts)
RUN echo "upload_max_filesize = 256M\npost_max_size = 256M\nmemory_limit = 512M\nmax_execution_time = 3600" > /usr/local/etc/php/conf.d/custom.ini

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend assets with Vite and clean up node_modules to keep image lean
RUN npm install && npm run build && rm -rf node_modules

# Prepare storage directories and database
RUN mkdir -p /app/backend/storage/framework/cache/data \
             /app/backend/storage/framework/sessions \
             /app/backend/storage/framework/views \
             /app/backend/storage/logs \
             /app/backend/storage/app/public \
             /app/backend/database \
             /app/ai-service/storage/uploads \
    && touch /app/backend/database/database.sqlite \
    && chmod -R 777 /app/backend/storage /app/backend/database /app/ai-service/storage

WORKDIR /app

# Setup Entrypoint and Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

EXPOSE 8000

CMD ["/app/entrypoint.sh"]
