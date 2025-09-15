# Multi-stage Dockerfile for Bagisto Laravel E-commerce Application

# Stage 1: Node.js for building frontend assets
FROM node:18-alpine AS node-builder

WORKDIR /var/www/html

# Copy package files
COPY package*.json ./

# Install Node.js dependencies
RUN npm ci --only=production

# Copy source code and build assets
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.3-fpm-alpine

# Arguments from docker-compose
ARG WWWGROUP=1000
ARG WWWUSER=1000

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    supervisor \
    nginx \
    mysql-client \
    shadow

# Install PHP extensions required by Bagisto
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        gd \
        xml \
        curl \
        fileinfo \
        intl \
        calendar \
        tokenizer \
        bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN groupadd --force -g $WWWGROUP www \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER www

# Copy existing application directory contents
COPY --chown=www:www . /var/www/html

# Copy built assets from Node.js stage
COPY --from=node-builder --chown=www:www /var/www/html/public/build /var/www/html/public/build

# Set proper permissions for composer cache
RUN mkdir -p /home/www/.composer \
    && chown -R www:www /home/www

# Switch to www user for composer install
USER www

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-scripts --no-suggest --no-interaction

# Switch back to root
USER root

# Create necessary directories and set permissions
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www:www /var/www/html/storage \
    && chown -R www:www /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Copy environment file if it doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configure PHP-FPM
RUN echo "listen = 127.0.0.1:9000" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start services using supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
