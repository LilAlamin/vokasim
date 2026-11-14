# ==========================================
# Stage 1: Install Composer Dependencies
# ==========================================
FROM composer:2 AS composer_stage
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ==========================================
# Stage 2: Build Frontend Assets (Vite + Tailwind + Flux)
# ==========================================
FROM node:22-alpine AS frontend_stage
WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
# Copy vendor from Stage 1 because Flux UI requires flux.css inside vendor/
COPY --from=composer_stage /app/vendor ./vendor

RUN npm run build

# ==========================================
# Stage 3: Application Runtime (PHP 8.4 + Nginx)
# ==========================================
FROM php:8.4-fpm-alpine AS runtime

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions required by Laravel
RUN docker-php-ext-install \
    pdo_sqlite \
    bcmath \
    zip \
    intl \
    opcache \
    pcntl

# Configure Nginx & Supervisor
RUN mkdir -p /run/nginx /var/log/supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy composer vendor from Stage 1
COPY --from=composer_stage /app/vendor ./vendor

# Copy compiled frontend assets from Stage 2
COPY --from=frontend_stage /app/public/build ./public/build

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose HTTP port
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
