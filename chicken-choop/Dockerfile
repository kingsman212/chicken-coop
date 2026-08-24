# Stage 1: Build Frontend Assets
FROM node:20-alpine AS node_builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP-FPM Application (Production)
FROM php:8.2-fpm-alpine

# Install system dependencies & PHP extensions required by Laravel & MQTT
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    linux-headers \
    zip \
    unzip \
    git \
    curl \
    mysql-client \
    bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd pcntl bcmath sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy compiled assets from node stage
COPY --from=node_builder /app/public/build ./public/build

# Install PHP dependencies (production only, no dev packages)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set directory permissions dengan user www-data (bukan 777!)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Buat direktori shared untuk static assets (akan di-copy ke volume saat startup)
RUN mkdir -p /shared/public

# Copy entrypoint script and make it executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# PHP-FPM berjalan di port 9000
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]

# Default command: jalankan PHP-FPM (production web server)
CMD ["php-fpm"]

