#!/bin/bash

# Bagisto Docker Initialization Script

set -e

echo "🚀 Initializing Bagisto application..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
until php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; do
    echo "Database not ready, waiting 5 seconds..."
    sleep 5
done

echo "✅ Database connection established!"

# Generate application key if not exists
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache config for better performance
echo "🔧 Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Create storage link if it doesn't exist
if [ ! -L public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link
fi

# Seed database if in development or if SEED_DATABASE is set
if [ "$APP_ENV" = "local" ] || [ "$SEED_DATABASE" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed
fi

# Cache configuration for production
if [ "$APP_ENV" = "production" ]; then
    echo "⚡ Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Index products in Elasticsearch if configured
if [ "$SCOUT_DRIVER" = "elasticsearch" ]; then
    echo "🔍 Indexing products in Elasticsearch..."
    php artisan scout:import "Webkul\Product\Models\Product" || echo "⚠️ Elasticsearch indexing failed, continuing..."
fi

# Set proper permissions
echo "🔒 Setting proper permissions..."
chown -R www:www /var/www/html/storage
chown -R www:www /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

echo "✅ Bagisto initialization completed!"

# Start the main process
exec "$@"
